// Road-segment mapping flow with OSRM route fitting and 3-meter interpolation.

(function () {
    const DAR_ES_SALAAM_CENTER = [-6.7924, 39.2083];
    const MIN_POINTS_FOR_ROUTE = 2;
    const INTERVAL_METERS = 3;
    const SEARCH_MIN_CHARS = 2;

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

    function lineLengthKm(lineCoordinates) {
        if (!window.turf || !Array.isArray(lineCoordinates) || lineCoordinates.length < 2) {
            return 0;
        }

        return turf.length(turf.lineString(lineCoordinates), { units: 'kilometers' });
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

    async function fetchOsrmRoute(points) {
        const coordinates = points.map((point) => `${point.lng},${point.lat}`).join(';');
        const url = `https://router.project-osrm.org/route/v1/driving/${coordinates}?overview=full&geometries=geojson&steps=false`;
        const response = await fetch(url, { method: 'GET' });
        const data = await response.json().catch(() => ({}));

        if (!response.ok || data.code !== 'Ok' || !Array.isArray(data.routes) || !data.routes[0]?.geometry?.coordinates) {
            throw new Error(data.message || 'OSRM route generation failed.');
        }

        return data.routes[0].geometry.coordinates;
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
        const locationSearchInput = document.getElementById('roadSegmentLocationSearch');
        const locationSearchResults = document.getElementById('roadSegmentLocationSearchResults');
        const locationSearchStatus = document.getElementById('roadSegmentLocationSearchStatus');
        const locationSearchClear = document.getElementById('roadSegmentLocationSearchClear');

        const pickedLayer = L.layerGroup().addTo(map);
        const routeLayer = L.layerGroup().addTo(map);
        const dotsLayer = L.layerGroup().addTo(map);
        let searchController = null;
        let searchDebounce = null;
        let activeResults = [];
        let activeResultIndex = -1;
        const searchCache = new Map();

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
                    osrmLineCoordinates = await fetchOsrmRoute(selectedPoints);
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
