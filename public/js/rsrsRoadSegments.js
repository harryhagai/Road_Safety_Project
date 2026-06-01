// Road-segment mapping flow with OSRM route fitting and 3-meter interpolation.

(function () {
    const DAR_ES_SALAAM_CENTER = [-6.7924, 39.2083];
    const MIN_POINTS_FOR_ROUTE = 2;
    const INTERVAL_METERS = 3;
    const SEARCH_MIN_CHARS = 2;
    const MAX_SEGMENT_DETOUR_FACTOR = 2.8;
    const MAX_MIDPOINT_DRIFT_METERS = 30;
    const PRIMARY_SEGMENT_COLORS = [
        '#1d4ed8', // blue
        '#111827', // black
        '#6b4f2a', // brown
        '#15803d', // green
        '#2563eb', // blue-alt
        '#0f172a', // black-alt
        '#7a5a2c', // brown-alt
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
        const hash = stableHash(key);
        const index = hash % PRIMARY_SEGMENT_COLORS.length;
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

    function joinMatchedCoordinates(matchings) {
        const merged = [];

        matchings.forEach((matching) => {
            const coords = matching?.geometry?.coordinates;
            if (!Array.isArray(coords) || coords.length === 0) return;

            coords.forEach((coord) => {
                if (!Array.isArray(coord) || coord.length < 2) return;
                const prev = merged[merged.length - 1];
                if (prev && Math.abs(prev[0] - coord[0]) < 1e-7 && Math.abs(prev[1] - coord[1]) < 1e-7) {
                    return;
                }
                merged.push(coord);
            });
        });

        return merged;
    }

    function mergeCoordinates(target, chunk) {
        chunk.forEach((coord) => {
            if (!Array.isArray(coord) || coord.length < 2) return;
            const prev = target[target.length - 1];
            if (prev && Math.abs(prev[0] - coord[0]) < 1e-7 && Math.abs(prev[1] - coord[1]) < 1e-7) {
                return;
            }
            target.push(coord);
        });
    }

    async function fetchPairMatch(start, end) {
        const profile = 'driving';
        const coordinates = `${start.lng},${start.lat};${end.lng},${end.lat}`;

        const tryRadii = [10, 20, 35];
        for (const radius of tryRadii) {
            const matchUrl = `https://router.project-osrm.org/match/v1/${profile}/${coordinates}?overview=full&geometries=geojson&tidy=true&gaps=ignore&radiuses=${radius};${radius}&annotations=false`;
            const matchResponse = await fetch(matchUrl, { method: 'GET' });
            const matchData = await matchResponse.json().catch(() => ({}));
            if (matchResponse.ok && matchData.code === 'Ok' && Array.isArray(matchData.matchings) && matchData.matchings.length > 0) {
                const matchedCoords = joinMatchedCoordinates(matchData.matchings);
                if (matchedCoords.length >= 2) {
                    return matchedCoords;
                }
            }
        }

        const routeUrl = `https://router.project-osrm.org/route/v1/${profile}/${coordinates}?overview=full&geometries=geojson&steps=false&continue_straight=true`;
        const routeResponse = await fetch(routeUrl, { method: 'GET' });
        const routeData = await routeResponse.json().catch(() => ({}));
        if (routeResponse.ok && routeData.code === 'Ok' && Array.isArray(routeData.routes) && routeData.routes[0]?.geometry?.coordinates) {
            return routeData.routes[0].geometry.coordinates;
        }

        // Hard fallback: keep the user-drawn segment if OSRM cannot honor this pair.
        return [[start.lng, start.lat], [end.lng, end.lat]];
    }

    function isSegmentGeometryReasonable(start, end, segmentCoords) {
        if (!Array.isArray(segmentCoords) || segmentCoords.length < 2) {
            return false;
        }

        const directMeters = Math.max(1, distanceMeters(start, end));
        const segmentMeters = lineLengthKm(segmentCoords) * 1000;
        const detourFactor = segmentMeters / directMeters;
        if (detourFactor > MAX_SEGMENT_DETOUR_FACTOR) {
            return false;
        }

        const midPoint = {
            lat: (start.lat + end.lat) / 2,
            lng: (start.lng + end.lng) / 2,
        };
        const midpointDrift = pointToLineDistanceMeters(midPoint, segmentCoords);
        return midpointDrift <= MAX_MIDPOINT_DRIFT_METERS;
    }

    async function snapPointToNearestRoad(point) {
        const profile = 'driving';
        const coords = `${point.lng},${point.lat}`;
        const radii = [12, 22, 35];

        for (const radius of radii) {
            const nearestUrl = `https://router.project-osrm.org/nearest/v1/${profile}/${coords}?number=1&radiuses=${radius}`;
            const response = await fetch(nearestUrl, { method: 'GET' });
            const data = await response.json().catch(() => ({}));

            if (!response.ok || data.code !== 'Ok' || !Array.isArray(data.waypoints) || !data.waypoints[0]?.location) {
                continue;
            }

            const snapped = data.waypoints[0].location;
            if (!Array.isArray(snapped) || snapped.length < 2) {
                continue;
            }

            const snappedPoint = { lng: Number(snapped[0]), lat: Number(snapped[1]) };
            if (!Number.isFinite(snappedPoint.lat) || !Number.isFinite(snappedPoint.lng)) {
                continue;
            }

            if (distanceMeters(point, snappedPoint) <= 45) {
                return snappedPoint;
            }
        }

        return point;
    }

    async function fetchOsrmMatchedOrRoute(points) {
        if (points.length < 2) {
            throw new Error('At least two points are required.');
        }

        const snappedPoints = [];
        for (const point of points) {
            // Snap each clicked point first so sub-road traces stay on local roads.
            snappedPoints.push(await snapPointToNearestRoad(point));
        }

        const merged = [];
        for (let index = 1; index < snappedPoints.length; index += 1) {
            const start = snappedPoints[index - 1];
            const end = snappedPoints[index];
            const segmentCoords = await fetchPairMatch(start, end);

            if (isSegmentGeometryReasonable(start, end, segmentCoords)) {
                mergeCoordinates(merged, segmentCoords);
            } else {
                // Reject weird jump-to-main-road geometry and keep local user intent.
                mergeCoordinates(merged, [[start.lng, start.lat], [end.lng, end.lat]]);
            }
        }

        if (merged.length < 2) {
            throw new Error('Could not build a road shape from selected points.');
        }

        return merged;
    }

    async function fetchDirectNominatim(query) {
        const url = `https://nominatim.openstreetmap.org/search?format=jsonv2&q=${encodeURIComponent(query)}&limit=6&addressdetails=0&countrycodes=tz`;
        const response = await fetch(url, {
            headers: {
                Accept: 'application/json',
            },
        });
        const payload = await response.json().catch(() => []);
        if (!response.ok || !Array.isArray(payload)) {
            return [];
        }

        return payload
            .map((item) => ({
                label: String(item.display_name || 'Unknown location').split(',')[0] || 'Unknown location',
                subtitle: String(item.display_name || ''),
                lat: Number(item.lat),
                lng: Number(item.lon),
                provider: 'nominatim-direct',
            }))
            .filter((item) => Number.isFinite(item.lat) && Number.isFinite(item.lng));
    }

    function initializeRoadSegmentMap(mapRoot) {
        if (!mapRoot?.mapApi || !window.L) {
            return;
        }

        const map = mapRoot.mapApi.map;
        map.setView(DAR_ES_SALAAM_CENTER, Math.max(12, map.getZoom() || 12));

        const selectedPoints = [];
        let osrmLineCoordinates = [];
        let interpolatedCoordinates = [];

        const pointCountTarget = document.getElementById('segmentPointCount');
        const generatedPointCountTarget = document.getElementById('generatedPointCount');
        const selectedCoordinatesPanel = document.getElementById('selectedCoordinatesPanel');
        const lengthTarget = document.getElementById('segmentLengthPreview');
        const boundaryInput = document.getElementById('boundary_coordinates');
        const coordinatesJsonString = document.getElementById('coordinates_json_string');
        const coordinatesJsonPreview = document.getElementById('coordinates_json_preview');
        const pointSummary = document.getElementById('segment_point_summary');
        const lengthInput = document.getElementById('length_km');
        const generateBtn = document.getElementById('generateRoadShapeBtn');
        const undoBtn = document.getElementById('undoSegmentPointBtn');
        const clearBtn = document.getElementById('clearSegmentPointsBtn');
        const openModalBtn = document.getElementById('openSegmentModalBtn');
        const form = document.getElementById('roadSegmentForm');
        const segmentTypeSelect = document.getElementById('segment_type_id');
        const segmentTypeRulesPreview = document.getElementById('segmentTypeRulesPreview');
        const locationSearchInput = document.getElementById('roadSegmentLocationSearch');
        const locationSearchResults = document.getElementById('roadSegmentLocationSearchResults');
        const locationSearchStatus = document.getElementById('roadSegmentLocationSearchStatus');
        const locationSearchClear = document.getElementById('roadSegmentLocationSearchClear');
        const existingSegmentButtons = Array.from(document.querySelectorAll('[data-existing-segment]'));

        const pickedLayer = L.layerGroup().addTo(map);
        const routeLayer = L.layerGroup().addTo(map);
        const dotsLayer = L.layerGroup().addTo(map);
        const existingSegmentsLayer = L.layerGroup().addTo(map);
        let searchController = null;
        let searchDebounce = null;
        let activeResults = [];
        let activeResultIndex = -1;
        const searchCache = new Map();
        const existingSegments = Array.isArray(window.roadSegmentPage?.existingSegments)
            ? window.roadSegmentPage.existingSegments
            : [];
        const existingSegmentLayers = new Map();
        const existingSegmentButtonsById = new Map();
        let activeExistingSegmentId = null;
        const segmentTypesWithRules = Array.isArray(window.roadSegmentPage?.segmentTypesWithRules)
            ? window.roadSegmentPage.segmentTypesWithRules
            : [];

        function renderSegmentTypeRulesPreview() {
            if (!segmentTypeRulesPreview || !segmentTypeSelect) return;

            const selectedId = Number(segmentTypeSelect.value);
            const selectedType = segmentTypesWithRules.find((item) => Number(item?.id) === selectedId);
            const rules = Array.isArray(selectedType?.default_rules) ? selectedType.default_rules : [];

            if (!selectedId) {
                segmentTypeRulesPreview.innerHTML = 'Select a segment type to preview default rules that will be auto-created.';
                return;
            }

            if (rules.length === 0) {
                segmentTypeRulesPreview.innerHTML = 'No default rules for this segment type. Segment will be saved without auto-generated rules.';
                return;
            }

            segmentTypeRulesPreview.innerHTML = rules
                .map((rule, index) => {
                    const value = rule?.rule_value ? ` (${escapeHtml(rule.rule_value)})` : '';
                    const description = rule?.description ? ` - ${escapeHtml(rule.description)}` : '';
                    return `${index + 1}. <strong>${escapeHtml(rule.rule_name || 'Rule')}</strong>${value}${description}`;
                })
                .join('<br>');
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

        function highlightExistingSegment(segmentId) {
            const currentLayer = activeExistingSegmentId ? existingSegmentLayers.get(activeExistingSegmentId) : null;
            applyExistingSegmentVisual(currentLayer, false);

            existingSegmentButtons.forEach((button) => button.classList.remove('is-active'));

            activeExistingSegmentId = segmentId || null;
            if (!activeExistingSegmentId) {
                return;
            }

            const nextLayer = existingSegmentLayers.get(activeExistingSegmentId);
            applyExistingSegmentVisual(nextLayer, true);
            nextLayer?.polyline?.bringToFront();

            const activeButton = existingSegmentButtonsById.get(activeExistingSegmentId);
            if (activeButton) {
                activeButton.classList.add('is-active');
            }
        }

        function focusExistingSegment(segment) {
            const segmentId = segmentIdKey(segment);
            if (!segmentId) return;

            highlightExistingSegment(segmentId);

            const targetLayer = existingSegmentLayers.get(segmentId);
            if (!targetLayer) return;

            const bounds = targetLayer.polyline?.getBounds?.();
            if (bounds?.isValid()) {
                map.fitBounds(bounds, { padding: [24, 24], maxZoom: 18 });
            }
            targetLayer.polyline?.openPopup?.();
            targetLayer.marker?.openPopup?.();
        }

        function drawExistingSegments() {
            existingSegmentsLayer.clearLayers();
            existingSegmentLayers.clear();
            const allPoints = [];

            existingSegments.forEach((segment) => {
                const coordinates = extractLineCoordinates(segment?.boundary_coordinates);
                if (coordinates.length < 2) {
                    return;
                }

                const latLngs = coordinates.map((pair) => [pair[1], pair[0]]);
                const segmentTypeColor = resolveSegmentColor(segment);
                const polyline = L.polyline(latLngs, {
                    color: segmentTypeColor,
                    weight: 4,
                    opacity: 0.85,
                }).addTo(existingSegmentsLayer);

                const segmentName = escapeHtml(segment?.segment_name || 'Unnamed segment');
                const segmentType = escapeHtml(segment?.segment_type || 'General segment');
                const segmentLength = Number(segment?.length_km);
                const lengthText = Number.isFinite(segmentLength) ? `${segmentLength.toFixed(2)} km` : 'Length N/A';
                const rulesText = formatSegmentRules(segment);
                const detailsMarkup = [
                    `<strong>${segmentName}</strong>`,
                    `Type: ${segmentType}`,
                    `Rules: ${rulesText}`,
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
                    icon: createExistingSegmentIcon(segmentTypeColor),
                    zIndexOffset: 650,
                }).addTo(existingSegmentsLayer);
                marker.bindPopup(detailsMarkup);
                marker.bindTooltip(detailsMarkup, {
                    direction: 'top',
                    opacity: 0.95,
                });

                const segmentId = segmentIdKey(segment);
                if (segmentId) {
                    const segmentMeta = {
                        polyline,
                        marker,
                        baseColor: segmentTypeColor,
                    };
                    existingSegmentLayers.set(segmentId, segmentMeta);
                    polyline.on('click', () => highlightExistingSegment(segmentId));
                    marker.on('click', () => highlightExistingSegment(segmentId));
                }

                allPoints.push(...latLngs);
            });

            if (allPoints.length > 1) {
                map.fitBounds(L.latLngBounds(allPoints), { padding: [24, 24], maxZoom: 16 });
            }
        }

        function registerExistingSegmentButtons() {
            existingSegmentButtons.forEach((button) => {
                let segment = null;
                try {
                    segment = JSON.parse(button.getAttribute('data-existing-segment') || '{}');
                } catch (error) {
                    segment = null;
                }

                const segmentId = segmentIdKey(segment);
                if (segmentId) {
                    existingSegmentButtonsById.set(segmentId, button);
                }

                button.addEventListener('click', function () {
                    if (!segmentId) return;
                    focusExistingSegment(segment || { id: segmentId });
                });
            });
        }

        function updatePanels() {
            if (pointCountTarget) {
                pointCountTarget.textContent = `${selectedPoints.length} points selected`;
            }

            if (generatedPointCountTarget) {
                generatedPointCountTarget.textContent = `${interpolatedCoordinates.length} points generated`;
            }

            if (pointSummary) {
                pointSummary.value = `${selectedPoints.length} selected / ${interpolatedCoordinates.length} generated`;
            }

            if (selectedCoordinatesPanel) {
                if (selectedPoints.length === 0) {
                    selectedCoordinatesPanel.textContent = 'Click on the map to choose a location.';
                } else {
                    const last = selectedPoints[selectedPoints.length - 1];
                    selectedCoordinatesPanel.textContent = `Last point: ${last.lat.toFixed(6)}, ${last.lng.toFixed(6)}`;
                }
            }

            const lengthKm = lineLengthKm(osrmLineCoordinates);
            if (lengthTarget) {
                lengthTarget.textContent = `${lengthKm.toFixed(2)} km`;
            }
            if (lengthInput) {
                lengthInput.value = lengthKm > 0 ? lengthKm.toFixed(2) : '';
            }
        }

        function setSearchStatus(message) {
            if (locationSearchStatus) {
                locationSearchStatus.textContent = message;
            }
        }

        function renderSearchResults(results) {
            if (!locationSearchResults) return;

            activeResults = Array.isArray(results) ? results : [];
            activeResultIndex = -1;
            if (activeResults.length === 0) {
                locationSearchResults.hidden = true;
                locationSearchResults.innerHTML = '';
                mapRoot.mapApi.clearPreviewLocation?.();
                return;
            }

            locationSearchResults.hidden = false;
            locationSearchResults.innerHTML = activeResults
                .map((result, index) => {
                    const label = String(result.label || result.display_name || 'Unknown location');
                    const subtitle = String(result.subtitle || result.display_name || '');
                    return `
                        <button type="button" class="geo-map-search__result" data-location-search-result-index="${index}">
                            <span class="geo-map-search__result-title">${escapeHtml(label)}</span>
                            <span class="geo-map-search__result-meta">${escapeHtml(subtitle)}</span>
                        </button>
                    `;
                })
                .join('');
        }

        function focusResultByIndex(index, shouldPreview = true) {
            if (!locationSearchResults || activeResults.length === 0) {
                return;
            }

            activeResultIndex = Math.max(0, Math.min(index, activeResults.length - 1));
            locationSearchResults
                .querySelectorAll('[data-location-search-result-index]')
                .forEach((el, idx) => el.classList.toggle('is-active', idx === activeResultIndex));

            const result = activeResults[activeResultIndex];
            const lat = Number(result?.lat);
            const lng = Number(result?.lng);
            if (shouldPreview && Number.isFinite(lat) && Number.isFinite(lng)) {
                mapRoot.mapApi.previewLocation?.(lat, lng, { zoom: 16, animate: true });
            }
        }

        async function runLocationSearch(query) {
            if (!mapRoot.mapApi?.config?.searchUrl) {
                setSearchStatus('Search service unavailable.');
                return;
            }

            if (searchController) {
                searchController.abort();
            }
            const cacheKey = query.toLowerCase();
            if (searchCache.has(cacheKey)) {
                const cached = searchCache.get(cacheKey);
                renderSearchResults(cached);
                setSearchStatus(cached.length > 0 ? `Found ${cached.length} location(s).` : 'No matching locations found.');
                return;
            }

            searchController = new AbortController();
            setSearchStatus('Searching locations...');

            try {
                const response = await fetch(
                    `${mapRoot.mapApi.config.searchUrl}?query=${encodeURIComponent(query)}`,
                    { headers: { Accept: 'application/json' }, signal: searchController.signal }
                );
                const payload = await response.json().catch(() => ({}));
                const items = Array.isArray(payload?.results) ? payload.results : (Array.isArray(payload) ? payload : []);
                searchCache.set(cacheKey, items);

                if (items.length > 0) {
                    renderSearchResults(items);
                    setSearchStatus(`Found ${items.length} location(s).`);
                    return;
                }

                const fallbackItems = await fetchDirectNominatim(query);
                if (fallbackItems.length > 0) {
                    searchCache.set(cacheKey, fallbackItems);
                    renderSearchResults(fallbackItems);
                    setSearchStatus(`Found ${fallbackItems.length} location(s) via browser fallback.`);
                    return;
                }

                renderSearchResults([]);
                setSearchStatus(payload?.message || 'No matching locations found.');
            } catch (error) {
                if (error.name === 'AbortError') return;
                const fallbackItems = await fetchDirectNominatim(query).catch(() => []);
                if (fallbackItems.length > 0) {
                    searchCache.set(query.toLowerCase(), fallbackItems);
                    renderSearchResults(fallbackItems);
                    setSearchStatus(`Found ${fallbackItems.length} location(s) via browser fallback.`);
                    return;
                }

                renderSearchResults([]);
                setSearchStatus('Search failed. Network from server is blocked, and browser fallback also failed.');
            }
        }

        function renderPickedPoints() {
            pickedLayer.clearLayers();

            selectedPoints.forEach((point, index) => {
                L.marker([point.lat, point.lng], { icon: createLocationPointIcon(index) })
                    .addTo(pickedLayer)
                    .bindTooltip(`Point ${index + 1}`);
            });

            if (selectedPoints.length >= 2) {
                L.polyline(selectedPoints.map((point) => [point.lat, point.lng]), {
                    color: '#64748b',
                    weight: 3,
                    dashArray: '6 6',
                    opacity: 0.9,
                }).addTo(pickedLayer);
            }
        }

        function renderRouteAndDots() {
            routeLayer.clearLayers();
            dotsLayer.clearLayers();

            if (osrmLineCoordinates.length >= 2) {
                L.polyline(osrmLineCoordinates.map((coord) => [coord[1], coord[0]]), {
                    color: '#0d6efd',
                    weight: 5,
                    opacity: 0.95,
                }).addTo(routeLayer);
            }

            interpolatedCoordinates.forEach((coord) => {
                L.circleMarker([coord[1], coord[0]], {
                    radius: 2.5,
                    color: '#0d6efd',
                    fillColor: '#60a5fa',
                    fillOpacity: 0.85,
                    weight: 1,
                }).addTo(dotsLayer);
            });
        }

        function persistPayload() {
            const geometryPayload = {
                type: 'Feature',
                geometry: {
                    type: 'LineString',
                    coordinates: interpolatedCoordinates,
                },
                properties: {
                    source: 'osrm_route',
                    interpolation_meters: INTERVAL_METERS,
                    selected_point_count: selectedPoints.length,
                    generated_point_count: interpolatedCoordinates.length,
                    raw_points: toLngLat(selectedPoints),
                },
            };

            const coordinatesJson = JSON.stringify(interpolatedCoordinates);
            if (coordinatesJsonString) {
                coordinatesJsonString.value = coordinatesJson;
            }
            if (coordinatesJsonPreview) {
                coordinatesJsonPreview.value = coordinatesJson;
            }
            if (boundaryInput) {
                boundaryInput.value = JSON.stringify(geometryPayload);
            }
        }

        function resetGenerated() {
            osrmLineCoordinates = [];
            interpolatedCoordinates = [];
            if (boundaryInput) {
                boundaryInput.value = '';
            }
            if (coordinatesJsonString) {
                coordinatesJsonString.value = '';
            }
            if (coordinatesJsonPreview) {
                coordinatesJsonPreview.value = '';
            }
            renderRouteAndDots();
            updatePanels();
        }

        map.on('rsrs:point-selected', function (event) {
            const latitude = Number(event?.lat);
            const longitude = Number(event?.lng);

            if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
                return;
            }

            selectedPoints.push({ lat: latitude, lng: longitude });
            renderPickedPoints();
            resetGenerated();
        });

        if (undoBtn) {
            undoBtn.addEventListener('click', function () {
                if (selectedPoints.length === 0) {
                    return;
                }

                selectedPoints.pop();
                renderPickedPoints();
                resetGenerated();
            });
        }

        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                selectedPoints.length = 0;
                renderPickedPoints();
                resetGenerated();
            });
        }

        if (generateBtn) {
            generateBtn.addEventListener('click', async function () {
                if (selectedPoints.length < MIN_POINTS_FOR_ROUTE) {
                    alert('Select at least two points first.');
                    return;
                }

                generateBtn.disabled = true;
                generateBtn.innerHTML = '<i class="bi bi-hourglass-split"></i><span>Generating...</span>';

                try {
                    osrmLineCoordinates = await fetchOsrmMatchedOrRoute(selectedPoints);
                    interpolatedCoordinates = densifyEveryMeters(osrmLineCoordinates, INTERVAL_METERS);
                    renderRouteAndDots();
                    persistPayload();

                    if (osrmLineCoordinates.length > 1) {
                        const bounds = L.latLngBounds(osrmLineCoordinates.map((coord) => [coord[1], coord[0]]));
                        map.fitBounds(bounds, { padding: [26, 26], maxZoom: 18 });
                    }
                } catch (error) {
                    alert(error.message || 'Failed to generate road shape.');
                } finally {
                    generateBtn.disabled = false;
                    generateBtn.innerHTML = '<i class="bi bi-bezier2"></i><span>Generate Road Shape</span>';
                    updatePanels();
                }
            });
        }

        if (openModalBtn) {
            openModalBtn.addEventListener('click', function (event) {
                if (interpolatedCoordinates.length < 2) {
                    event.preventDefault();
                    alert('Generate Road Shape first so the system can save full coordinates every 3 meters.');
                }
            });
        }

        if (form) {
            form.addEventListener('submit', function (event) {
                if (interpolatedCoordinates.length < 2 || !boundaryInput?.value) {
                    event.preventDefault();
                    alert('Generate Road Shape first before saving.');
                }
            });
        }

        if (segmentTypeSelect) {
            segmentTypeSelect.addEventListener('change', renderSegmentTypeRulesPreview);
            renderSegmentTypeRulesPreview();
        }

        if (locationSearchInput) {
            locationSearchInput.addEventListener('input', function () {
                const query = String(locationSearchInput.value || '').trim();
                if (locationSearchClear) {
                    locationSearchClear.hidden = query.length === 0;
                }

                if (searchDebounce) {
                    clearTimeout(searchDebounce);
                }

                if (query.length < SEARCH_MIN_CHARS) {
                    renderSearchResults([]);
                    setSearchStatus('Start typing to find a location and jump the map there.');
                    return;
                }

                searchDebounce = setTimeout(() => runLocationSearch(query), 280);
            });

            locationSearchInput.addEventListener('keydown', function (event) {
                if (!activeResults.length) return;

                if (event.key === 'ArrowDown') {
                    event.preventDefault();
                    focusResultByIndex(activeResultIndex + 1);
                    return;
                }

                if (event.key === 'ArrowUp') {
                    event.preventDefault();
                    focusResultByIndex(activeResultIndex <= 0 ? activeResults.length - 1 : activeResultIndex - 1);
                    return;
                }

                if (event.key === 'Enter') {
                    event.preventDefault();
                    const index = activeResultIndex >= 0 ? activeResultIndex : 0;
                    const target = locationSearchResults?.querySelector(`[data-location-search-result-index="${index}"]`);
                    target?.click();
                    return;
                }

                if (event.key === 'Escape') {
                    renderSearchResults([]);
                    setSearchStatus('Search closed.');
                }
            });
        }

        if (locationSearchClear) {
            locationSearchClear.addEventListener('click', function () {
                if (locationSearchInput) {
                    locationSearchInput.value = '';
                    locationSearchInput.focus();
                }
                locationSearchClear.hidden = true;
                renderSearchResults([]);
                setSearchStatus('Start typing to find a location and jump the map there.');
                mapRoot.mapApi.clearPreviewLocation?.();
            });
        }

        if (locationSearchResults) {
            locationSearchResults.addEventListener('mousemove', function (event) {
                const button = event.target.closest('[data-location-search-result-index]');
                if (!button) return;
                const index = Number(button.getAttribute('data-location-search-result-index'));
                focusResultByIndex(index, true);
            });

            locationSearchResults.addEventListener('click', function (event) {
                const button = event.target.closest('[data-location-search-result-index]');
                if (!button) return;

                const index = Number(button.getAttribute('data-location-search-result-index'));
                const result = activeResults[index];
                const lat = Number(result?.lat);
                const lng = Number(result?.lng);

                if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
                    return;
                }

                mapRoot.mapApi.centerOn(lat, lng, 17, true);
                mapRoot.mapApi.selectPoint(lat, lng);
                map.fire('rsrs:point-selected', { lat, lng });

                renderSearchResults([]);
                setSearchStatus('Location selected. You can continue adding points.');
                mapRoot.mapApi.clearPreviewLocation?.();
            });
        }

        registerExistingSegmentButtons();
        drawExistingSegments();
        updatePanels();
    }

    document.addEventListener('DOMContentLoaded', function () {
        const mapRoot = document.getElementById('roadSegmentMapLab');
        if (!mapRoot || !window.L) {
            return;
        }

        if (mapRoot.mapApi) {
            initializeRoadSegmentMap(mapRoot);
            return;
        }

        mapRoot.addEventListener('rsrs:map-ready', function () {
            initializeRoadSegmentMap(mapRoot);
        }, { once: true });
    });
})();
