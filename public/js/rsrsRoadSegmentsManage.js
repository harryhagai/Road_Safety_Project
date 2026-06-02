// Dedicated segment management map/list workflow.

(function () {
    const DAR_ES_SALAAM_CENTER = [-6.7924, 39.2083];
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

    function initializeRoadSegmentManagementMap(mapRoot) {
        if (!mapRoot?.mapApi || !window.L) return;

        const map = mapRoot.mapApi.map;
        map.setView(DAR_ES_SALAAM_CENTER, Math.max(12, map.getZoom() || 12));

        const existingSegments = Array.isArray(window.roadSegmentManagePage?.existingSegments)
            ? window.roadSegmentManagePage.existingSegments
            : [];
        const segmentTypesWithRules = Array.isArray(window.roadSegmentManagePage?.segmentTypesWithRules)
            ? window.roadSegmentManagePage.segmentTypesWithRules
            : [];
        const updateUrlTemplate = String(window.roadSegmentManagePage?.updateUrlTemplate || '');
        const destroyUrlTemplate = String(window.roadSegmentManagePage?.destroyUrlTemplate || '');

        const statusTarget = document.getElementById('segmentManageStatus');
        const existingSegmentButtons = Array.from(document.querySelectorAll('[data-existing-segment-focus]'));
        const editForm = document.getElementById('editRoadSegmentForm');
        const editNameInput = document.getElementById('edit_segment_name');
        const editTypeSelect = document.getElementById('edit_segment_type_id');
        const editTypeRulesPreview = document.getElementById('editSegmentTypeRulesPreview');
        const editDescriptionInput = document.getElementById('edit_description');
        const editLengthInput = document.getElementById('edit_length_km');
        const deleteForm = document.getElementById('deleteRoadSegmentForm');
        const deleteNameTarget = document.getElementById('deleteRoadSegmentName');

        const existingSegmentsLayer = L.layerGroup().addTo(map);
        const existingSegmentLayers = new Map();
        const existingSegmentButtonsById = new Map();
        let activeExistingSegmentId = null;

        function segmentIdKey(segment) {
            if (segment?.id === null || segment?.id === undefined) return '';
            return String(segment.id);
        }

        function formatSegmentRules(segment) {
            const rules = Array.isArray(segment?.rules) ? segment.rules : [];
            if (rules.length === 0) return 'No rules';
            return rules
                .map((rule) => {
                    const name = escapeHtml(rule?.rule_name || 'Rule');
                    const value = String(rule?.rule_value || '').trim();
                    return value ? `${name} (${escapeHtml(value)})` : name;
                })
                .join(', ');
        }

        function renderRulesPreviewForEdit() {
            if (!editTypeSelect || !editTypeRulesPreview) return;
            const selectedId = Number(editTypeSelect.value);
            const selectedType = segmentTypesWithRules.find((item) => Number(item?.id) === selectedId);
            const rules = Array.isArray(selectedType?.default_rules) ? selectedType.default_rules : [];

            if (!selectedId) {
                editTypeRulesPreview.innerHTML = 'Select a segment type to preview default rules.';
                return;
            }

            if (!rules.length) {
                editTypeRulesPreview.innerHTML = 'No default rules for this segment type.';
                return;
            }

            editTypeRulesPreview.innerHTML = rules
                .map((rule, index) => {
                    const value = rule?.rule_value ? ` (${escapeHtml(rule.rule_value)})` : '';
                    return `${index + 1}. <strong>${escapeHtml(rule.rule_name || 'Rule')}</strong>${value}`;
                })
                .join('<br>');
        }

        function applyExistingSegmentVisual(segmentMeta, isActive) {
            if (!segmentMeta) return;
            segmentMeta.polyline?.setStyle({
                color: isActive ? '#111827' : segmentMeta.baseColor,
                weight: isActive ? 6 : 4,
                opacity: isActive ? 1 : 0.85,
            });
            if (segmentMeta.marker) {
                segmentMeta.marker.setIcon(createExistingSegmentIcon(segmentMeta.baseColor, isActive));
                segmentMeta.marker.setZIndexOffset(isActive ? 1200 : 650);
            }
        }

        function setStatus(segment) {
            if (!statusTarget) return;
            if (!segment) {
                statusTarget.textContent = 'Select a segment from the list to preview it on the map.';
                return;
            }
            const type = segment.segment_type || 'General segment';
            const length = Number(segment.length_km);
            const lengthText = Number.isFinite(length) && length > 0 ? `${length.toFixed(2)} km` : 'Length N/A';
            statusTarget.textContent = `${segment.segment_name || 'Road segment'} | ${type} | ${lengthText}`;
        }

        function highlightExistingSegment(segmentId) {
            const currentMeta = activeExistingSegmentId ? existingSegmentLayers.get(activeExistingSegmentId) : null;
            applyExistingSegmentVisual(currentMeta, false);
            existingSegmentButtons.forEach((button) => button.closest('.geo-segment-item')?.classList.remove('is-active'));

            activeExistingSegmentId = segmentId || null;
            if (!activeExistingSegmentId) {
                setStatus(null);
                return;
            }

            const nextMeta = existingSegmentLayers.get(activeExistingSegmentId);
            applyExistingSegmentVisual(nextMeta, true);
            nextMeta?.polyline?.bringToFront();
            existingSegmentButtonsById.get(activeExistingSegmentId)?.closest('.geo-segment-item')?.classList.add('is-active');
            setStatus(nextMeta?.segment);
        }

        function focusExistingSegment(segment) {
            const segmentId = segmentIdKey(segment);
            if (!segmentId) return;
            highlightExistingSegment(segmentId);
            const targetMeta = existingSegmentLayers.get(segmentId);
            if (!targetMeta) return;

            const bounds = targetMeta.polyline?.getBounds?.();
            if (bounds?.isValid()) {
                map.fitBounds(bounds, { padding: [24, 24], maxZoom: 18 });
            }
            targetMeta.polyline?.openPopup?.();
        }

        function drawExistingSegments() {
            existingSegmentsLayer.clearLayers();
            existingSegmentLayers.clear();
            const allPoints = [];

            existingSegments.forEach((segment) => {
                const coordinates = extractLineCoordinates(segment?.boundary_coordinates);
                if (coordinates.length < 2) return;

                const latLngs = coordinates.map((pair) => [pair[1], pair[0]]);
                const color = resolveSegmentColor(segment);
                const polyline = L.polyline(latLngs, {
                    color,
                    weight: 4,
                    opacity: 0.85,
                }).addTo(existingSegmentsLayer);

                const name = escapeHtml(segment?.segment_name || 'Unnamed segment');
                const type = escapeHtml(segment?.segment_type || 'General segment');
                const length = Number(segment?.length_km);
                const lengthText = Number.isFinite(length) ? `${length.toFixed(2)} km` : 'Length N/A';
                const detailsMarkup = [
                    `<strong>${name}</strong>`,
                    `Type: ${type}`,
                    `Rules: ${formatSegmentRules(segment)}`,
                    `Length: ${escapeHtml(lengthText)}`,
                ].join('<br>');

                polyline.bindPopup(detailsMarkup);
                polyline.bindTooltip(detailsMarkup, {
                    direction: 'top',
                    sticky: true,
                    opacity: 0.95,
                });

                const center = L.latLngBounds(latLngs).getCenter();
                const marker = L.marker(center, {
                    icon: createExistingSegmentIcon(color),
                    zIndexOffset: 650,
                }).addTo(existingSegmentsLayer);
                marker.bindPopup(detailsMarkup);

                const segmentId = segmentIdKey(segment);
                if (segmentId) {
                    const meta = { segment, polyline, marker, baseColor: color };
                    existingSegmentLayers.set(segmentId, meta);
                    polyline.on('click', () => highlightExistingSegment(segmentId));
                    marker.on('click', () => highlightExistingSegment(segmentId));
                }

                allPoints.push(...latLngs);
            });

            if (allPoints.length > 1) {
                map.fitBounds(L.latLngBounds(allPoints), { padding: [24, 24], maxZoom: 16 });
            }
        }

        function buildSegmentActionUrl(template, segmentId) {
            if (!template || !segmentId) return '';
            return template.replace('__SEGMENT_ID__', encodeURIComponent(segmentId));
        }

        function parseSegmentFromButton(button, attributeName) {
            try {
                return JSON.parse(button.getAttribute(attributeName) || '{}');
            } catch (error) {
                return {};
            }
        }

        function prepareEditModal(segment) {
            const segmentId = segmentIdKey(segment);
            if (!segmentId || !editForm) return;
            editForm.setAttribute('action', buildSegmentActionUrl(updateUrlTemplate, segmentId));
            if (editNameInput) editNameInput.value = String(segment?.segment_name || '');
            if (editTypeSelect) editTypeSelect.value = segment?.segment_type_id ? String(segment.segment_type_id) : '';
            if (editDescriptionInput) editDescriptionInput.value = String(segment?.description || '');
            if (editLengthInput) {
                const length = Number(segment?.length_km);
                editLengthInput.value = Number.isFinite(length) && length > 0 ? length.toFixed(2) : '';
            }
            renderRulesPreviewForEdit();
            focusExistingSegment(segment);
        }

        function prepareDeleteModal(segment) {
            const segmentId = segmentIdKey(segment);
            if (!segmentId || !deleteForm) return;
            deleteForm.setAttribute('action', buildSegmentActionUrl(destroyUrlTemplate, segmentId));
            if (deleteNameTarget) {
                deleteNameTarget.textContent = String(segment?.segment_name || 'this segment');
            }
            focusExistingSegment(segment);
        }

        function registerExistingSegmentButtons() {
            existingSegmentButtons.forEach((button) => {
                const segment = parseSegmentFromButton(button, 'data-existing-segment');
                const segmentId = segmentIdKey(segment);
                if (segmentId) {
                    existingSegmentButtonsById.set(segmentId, button);
                }
                button.addEventListener('click', function () {
                    focusExistingSegment(segment);
                });
            });
        }

        function registerSegmentActionTriggers() {
            document.querySelectorAll('[data-edit-segment-trigger]').forEach((button) => {
                button.addEventListener('click', function () {
                    prepareEditModal(parseSegmentFromButton(button, 'data-segment'));
                });
            });
            document.querySelectorAll('[data-delete-segment-trigger]').forEach((button) => {
                button.addEventListener('click', function () {
                    prepareDeleteModal(parseSegmentFromButton(button, 'data-segment'));
                });
            });
            editTypeSelect?.addEventListener('change', renderRulesPreviewForEdit);
        }

        registerExistingSegmentButtons();
        registerSegmentActionTriggers();
        drawExistingSegments();
        setStatus(null);
    }

    document.addEventListener('DOMContentLoaded', function () {
        const mapRoot = document.getElementById('roadSegmentManagementMap');
        if (!mapRoot || !window.L) return;

        if (mapRoot.mapApi) {
            initializeRoadSegmentManagementMap(mapRoot);
            return;
        }

        mapRoot.addEventListener('rsrs:map-ready', function () {
            initializeRoadSegmentManagementMap(mapRoot);
        }, { once: true });
    });
})();
