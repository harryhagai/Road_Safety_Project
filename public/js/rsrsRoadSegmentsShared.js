// Shared road-segment helpers used by builder and management pages.

(function () {
    const namespace = window.RsrsRoadSegments = window.RsrsRoadSegments || {};

    const DAR_ES_SALAAM_CENTER = [-6.7924, 39.2083];
    const MIN_POINTS_FOR_ROUTE = 2;
    const INTERVAL_METERS = 3;
    const SEARCH_MIN_CHARS = 2;
    const PRIMARY_SEGMENT_COLORS = [
        '#1d4ed8',
        '#111827',
        '#6b4f2a',
        '#15803d',
        '#2563eb',
        '#0f172a',
        '#7a5a2c',
    ];

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function createLocationPointIcon(index) {
        return L.divIcon({
            className: 'geo-point-marker',
            html: `
                <div class="geo-point-marker__pin">
                    <i class="bi bi-geo-alt-fill"></i>
                    <span class="geo-point-marker__index">${index + 1}</span>
                </div>
            `,
            iconSize: [28, 36],
            iconAnchor: [14, 34],
            popupAnchor: [0, -28],
        });
    }

    function toLngLat(points) {
        return points.map((point) => [Number(point.lng), Number(point.lat)]);
    }

    function normalizeLineCoordinates(rawCoordinates) {
        if (!Array.isArray(rawCoordinates)) {
            return [];
        }

        return rawCoordinates
            .map((pair) => {
                if (!Array.isArray(pair) || pair.length < 2) return null;
                const lng = Number(pair[0]);
                const lat = Number(pair[1]);
                if (!Number.isFinite(lat) || !Number.isFinite(lng)) return null;
                return [lng, lat];
            })
            .filter((pair) => Array.isArray(pair));
    }

    function extractLineCoordinates(boundaryCoordinates) {
        let root = boundaryCoordinates;
        if (typeof root === 'string') {
            try {
                root = JSON.parse(root);
            } catch (error) {
                return [];
            }
        }

        if (!root || typeof root !== 'object') {
            return [];
        }

        const directGeometry = root.geometry;
        const featureGeometry = root.features?.[0]?.geometry;
        const geometry = directGeometry || featureGeometry || root;
        const geometryType = String(geometry?.type || '').toLowerCase();
        const coordinates = geometry?.coordinates;

        if (geometryType === 'linestring') {
            return normalizeLineCoordinates(coordinates);
        }

        if (geometryType === 'multilinestring' && Array.isArray(coordinates)) {
            return normalizeLineCoordinates(coordinates.flat());
        }

        if (geometryType === 'polygon' && Array.isArray(coordinates)) {
            return normalizeLineCoordinates(coordinates[0] || []);
        }

        if (Array.isArray(coordinates)) {
            const normalized = normalizeLineCoordinates(coordinates);
            if (normalized.length >= 2) {
                return normalized;
            }
        }

        if (Array.isArray(root.coordinates)) {
            return normalizeLineCoordinates(root.coordinates);
        }

        return [];
    }

    function stableHash(value) {
        const normalized = String(value || '').toLowerCase().trim();
        let hash = 0;
        for (let index = 0; index < normalized.length; index += 1) {
            hash = ((hash << 5) - hash) + normalized.charCodeAt(index);
            hash |= 0;
        }
        return Math.abs(hash);
    }

    function resolveSegmentColor(segment) {
        const key = String(segment?.id ?? segment?.segment_name ?? 'segment');
        const index = stableHash(key) % PRIMARY_SEGMENT_COLORS.length;
        return PRIMARY_SEGMENT_COLORS[index];
    }

    function createExistingSegmentIcon(color, isActive = false) {
        return L.divIcon({
            className: `geo-existing-segment-marker${isActive ? ' is-active' : ''}`,
            html: `
                <span class="geo-existing-segment-marker__pin" style="--segment-type-color: ${escapeHtml(color)};">
                    <i class="bi bi-signpost-split-fill"></i>
                </span>
            `,
            iconSize: [30, 30],
            iconAnchor: [15, 15],
            popupAnchor: [0, -14],
            tooltipAnchor: [0, -14],
        });
    }

    function lineLengthKm(lineCoordinates) {
        if (!window.turf || !Array.isArray(lineCoordinates) || lineCoordinates.length < 2) {
            return 0;
        }

        return turf.length(turf.lineString(lineCoordinates), { units: 'kilometers' });
    }

    function distanceMeters(a, b) {
        if (!window.turf) return 0;
        return turf.distance(turf.point([a.lng, a.lat]), turf.point([b.lng, b.lat]), { units: 'kilometers' }) * 1000;
    }

    function pointToLineDistanceMeters(point, lineCoordinates) {
        if (!window.turf || !Array.isArray(lineCoordinates) || lineCoordinates.length < 2) {
            return 0;
        }

        return turf.pointToLineDistance(
            turf.point([point.lng, point.lat]),
            turf.lineString(lineCoordinates),
            { units: 'meters' }
        );
    }

    function densifyEveryMeters(lineCoordinates, everyMeters) {
        if (!window.turf || !Array.isArray(lineCoordinates) || lineCoordinates.length < 2) {
            return [];
        }

        const line = turf.lineString(lineCoordinates);
        const totalKm = turf.length(line, { units: 'kilometers' });
        const stepKm = everyMeters / 1000;

        if (totalKm <= 0 || stepKm <= 0) {
            return lineCoordinates;
        }

        const sampled = [];
        for (let distanceKm = 0; distanceKm <= totalKm; distanceKm += stepKm) {
            const along = turf.along(line, distanceKm, { units: 'kilometers' });
            sampled.push(along.geometry.coordinates);
        }

        const endPoint = lineCoordinates[lineCoordinates.length - 1];
        const last = sampled[sampled.length - 1];
        if (!last || Math.abs(last[0] - endPoint[0]) > 1e-7 || Math.abs(last[1] - endPoint[1]) > 1e-7) {
            sampled.push(endPoint);
        }

        return sampled;
    }

    function segmentIdKey(segment) {
        if (segment?.id === null || segment?.id === undefined) {
            return '';
        }
        return String(segment.id);
    }

    function formatSegmentRules(segment) {
        const rules = Array.isArray(segment?.rules) ? segment.rules : [];
        if (rules.length === 0) {
            return 'No rules';
        }

        return rules
            .map((rule) => {
                const ruleName = escapeHtml(rule?.rule_name || 'Rule');
                const ruleValue = String(rule?.rule_value || '').trim();
                return ruleValue ? `${ruleName} (${escapeHtml(ruleValue)})` : ruleName;
            })
            .join(', ');
    }

    function buildSegmentActionUrl(template, segmentId) {
        if (!template || !segmentId) return '';
        return template.replace('__SEGMENT_ID__', encodeURIComponent(segmentId));
    }

    function parseSegmentFromButton(button, attributeName = 'data-segment') {
        try {
            return JSON.parse(button.getAttribute(attributeName) || '{}');
        } catch (error) {
            return {};
        }
    }

    function renderSegmentTypeRulesPreviewForSelect(selectEl, previewEl, segmentTypesWithRules, emptyMessage, noRulesMessage) {
        if (!previewEl || !selectEl) return;

        const selectedId = Number(selectEl.value);
        const selectedType = segmentTypesWithRules.find((item) => Number(item?.id) === selectedId);
        const rules = Array.isArray(selectedType?.default_rules) ? selectedType.default_rules : [];

        if (!selectedId) {
            previewEl.innerHTML = emptyMessage;
            return;
        }

        if (rules.length === 0) {
            previewEl.innerHTML = noRulesMessage || 'No default rules for this segment type.';
            return;
        }

        previewEl.innerHTML = rules
            .map((rule, index) => {
                const value = rule?.rule_value ? ` (${escapeHtml(rule.rule_value)})` : '';
                const description = rule?.description ? ` - ${escapeHtml(rule.description)}` : '';
                return `${index + 1}. <strong>${escapeHtml(rule.rule_name || 'Rule')}</strong>${value}${description}`;
            })
            .join('<br>');
    }

    function initWhenMapReady(rootId, initializer) {
        document.addEventListener('DOMContentLoaded', function () {
            const mapRoot = document.getElementById(rootId);
            if (!mapRoot || !window.L) {
                return;
            }

            if (mapRoot.mapApi) {
                initializer(mapRoot);
                return;
            }

            mapRoot.addEventListener('rsrs:map-ready', function () {
                initializer(mapRoot);
            }, { once: true });
        });
    }

    Object.assign(namespace, {
        DAR_ES_SALAAM_CENTER,
        MIN_POINTS_FOR_ROUTE,
        INTERVAL_METERS,
        SEARCH_MIN_CHARS,
        escapeHtml,
        createLocationPointIcon,
        toLngLat,
        normalizeLineCoordinates,
        extractLineCoordinates,
        stableHash,
        resolveSegmentColor,
        createExistingSegmentIcon,
        lineLengthKm,
        distanceMeters,
        pointToLineDistanceMeters,
        densifyEveryMeters,
        segmentIdKey,
        formatSegmentRules,
        buildSegmentActionUrl,
        parseSegmentFromButton,
        renderSegmentTypeRulesPreviewForSelect,
        initWhenMapReady,
    });
})();
