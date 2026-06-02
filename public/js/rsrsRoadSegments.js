// Road-segment builder page initializer.

(function () {
    const segments = window.RsrsRoadSegments || {};

    function initializeRoadSegmentMap(mapRoot) {
        if (!mapRoot?.mapApi || !window.L) {
            return;
        }

        const map = mapRoot.mapApi.map;
        map.setView(segments.DAR_ES_SALAAM_CENTER, Math.max(12, map.getZoom() || 12));

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
        const createModalEl = document.getElementById('createRoadSegmentModal');
        const warningModalEl = document.getElementById('roadSegmentWarningModal');
        const warningTitleTarget = document.getElementById('roadSegmentWarningTitle');
        const warningMessageTarget = document.getElementById('roadSegmentWarningMessage');
        const warningGenerateBtn = document.getElementById('roadSegmentWarningGenerateBtn');
        const form = document.getElementById('roadSegmentForm');
        const segmentTypeSelect = document.getElementById('segment_type_id');
        const segmentTypeRulesPreview = document.getElementById('segmentTypeRulesPreview');
        const editForm = document.getElementById('editRoadSegmentForm');
        const editNameInput = document.getElementById('edit_segment_name');
        const editTypeSelect = document.getElementById('edit_segment_type_id');
        const editTypeRulesPreview = document.getElementById('editSegmentTypeRulesPreview');
        const editDescriptionInput = document.getElementById('edit_description');
        const editLengthInput = document.getElementById('edit_length_km');
        const deleteForm = document.getElementById('deleteRoadSegmentForm');
        const deleteNameTarget = document.getElementById('deleteRoadSegmentName');
        const locationSearchInput = document.getElementById('roadSegmentLocationSearch');
        const locationSearchResults = document.getElementById('roadSegmentLocationSearchResults');
        const locationSearchStatus = document.getElementById('roadSegmentLocationSearchStatus');
        const locationSearchClear = document.getElementById('roadSegmentLocationSearchClear');
        const existingSegmentButtons = Array.from(document.querySelectorAll('[data-existing-segment-focus]'));

        const pickedLayer = L.layerGroup().addTo(map);
        const routeLayer = L.layerGroup().addTo(map);
        const dotsLayer = L.layerGroup().addTo(map);
        const existingSegmentsLayer = L.layerGroup().addTo(map);

        const pageConfig = window.roadSegmentPage || {};
        const existingSegments = Array.isArray(pageConfig.existingSegments) ? pageConfig.existingSegments : [];
        const segmentTypesWithRules = Array.isArray(pageConfig.segmentTypesWithRules) ? pageConfig.segmentTypesWithRules : [];
        const updateUrlTemplate = String(pageConfig.updateUrlTemplate || '');
        const destroyUrlTemplate = String(pageConfig.destroyUrlTemplate || '');

        function showWarningModal(message, title = 'Road shape required', options = {}) {
            if (warningTitleTarget) {
                warningTitleTarget.textContent = title;
            }
            if (warningMessageTarget) {
                warningMessageTarget.textContent = message;
            }
            if (warningGenerateBtn) {
                warningGenerateBtn.hidden = !options.showGenerateAction;
            }

            if (warningModalEl && window.bootstrap?.Modal) {
                window.bootstrap.Modal.getOrCreateInstance(warningModalEl).show();
                return;
            }

            if (window.Swal) {
                window.Swal.fire({
                    icon: 'warning',
                    title,
                    text: message,
                    confirmButtonText: 'OK',
                });
                return;
            }

            console.warn(message);
        }

        function showCreateSegmentModal() {
            if (!createModalEl || !window.bootstrap?.Modal) {
                return;
            }

            window.bootstrap.Modal.getOrCreateInstance(createModalEl).show();
        }

        function hideWarningModal() {
            if (!warningModalEl || !window.bootstrap?.Modal) {
                return;
            }

            window.bootstrap.Modal.getOrCreateInstance(warningModalEl).hide();
        }

        const existingSegmentsController = segments.createExistingSegmentsController({
            map,
            segments: existingSegments,
            layer: existingSegmentsLayer,
            buttons: existingSegmentButtons,
        });
        const modalActions = segments.createSegmentModalActions({
            updateUrlTemplate,
            destroyUrlTemplate,
            segmentTypesWithRules,
            editForm,
            editNameInput,
            editTypeSelect,
            editTypeRulesPreview,
            editDescriptionInput,
            editLengthInput,
            deleteForm,
            deleteNameTarget,
        });
        const searchController = segments.createLocationSearchController({
            mapRoot,
            input: locationSearchInput,
            resultsEl: locationSearchResults,
            statusEl: locationSearchStatus,
            clearButton: locationSearchClear,
            onSelect({ lat, lng }) {
                mapRoot.mapApi.centerOn(lat, lng, 17, true);
                mapRoot.mapApi.selectPoint(lat, lng);
                map.fire('rsrs:point-selected', { lat, lng });
            },
        });

        function renderSegmentTypeRulesPreview() {
            segments.renderSegmentTypeRulesPreviewForSelect(
                segmentTypeSelect,
                segmentTypeRulesPreview,
                segmentTypesWithRules,
                'Select a segment type to preview default rules that will be auto-created.',
                'No default rules for this segment type. Segment will be saved without auto-generated rules.'
            );
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

            const lengthKm = segments.lineLengthKm(osrmLineCoordinates);
            if (lengthTarget) {
                lengthTarget.textContent = `${lengthKm.toFixed(2)} km`;
            }
            if (lengthInput) {
                lengthInput.value = lengthKm > 0 ? lengthKm.toFixed(2) : '';
            }
        }

        function renderPickedPoints() {
            pickedLayer.clearLayers();

            selectedPoints.forEach((point, index) => {
                L.marker([point.lat, point.lng], { icon: segments.createLocationPointIcon(index) })
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
                    interpolation_meters: segments.INTERVAL_METERS,
                    selected_point_count: selectedPoints.length,
                    generated_point_count: interpolatedCoordinates.length,
                    raw_points: segments.toLngLat(selectedPoints),
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

        undoBtn?.addEventListener('click', function () {
            if (selectedPoints.length === 0) {
                return;
            }

            selectedPoints.pop();
            renderPickedPoints();
            resetGenerated();
        });

        clearBtn?.addEventListener('click', function () {
            selectedPoints.length = 0;
            renderPickedPoints();
            resetGenerated();
        });

        async function generateRoadShape() {
            if (generateBtn?.disabled) {
                return;
            }

            if (selectedPoints.length < segments.MIN_POINTS_FOR_ROUTE) {
                showWarningModal('Select at least two points first.', 'Select points first');
                return;
            }

            generateBtn.disabled = true;
            generateBtn.innerHTML = '<i class="bi bi-hourglass-split"></i><span>Generating...</span>';

            try {
                osrmLineCoordinates = await segments.fetchOsrmMatchedOrRoute(selectedPoints);
                interpolatedCoordinates = segments.densifyEveryMeters(osrmLineCoordinates, segments.INTERVAL_METERS);
                renderRouteAndDots();
                persistPayload();

                if (osrmLineCoordinates.length > 1) {
                    const bounds = L.latLngBounds(osrmLineCoordinates.map((coord) => [coord[1], coord[0]]));
                    map.fitBounds(bounds, { padding: [26, 26], maxZoom: 18 });
                }
            } catch (error) {
                showWarningModal(error.message || 'Failed to generate road shape.', 'Road shape unavailable');
            } finally {
                generateBtn.disabled = false;
                generateBtn.innerHTML = '<i class="bi bi-bezier2"></i><span>Generate Road Shape</span>';
                updatePanels();
            }
        }

        generateBtn?.addEventListener('click', generateRoadShape);

        warningGenerateBtn?.addEventListener('click', function () {
            if (selectedPoints.length < segments.MIN_POINTS_FOR_ROUTE) {
                showWarningModal('Select at least two points first.', 'Select points first');
                return;
            }

            hideWarningModal();
            generateRoadShape();
        });

        openModalBtn?.addEventListener('click', function (event) {
            event.preventDefault();

            if (interpolatedCoordinates.length < 2) {
                showWarningModal(
                    'Generate Road Shape first so the system can save full coordinates every 3 meters.',
                    'Road shape required',
                    { showGenerateAction: true }
                );
                return;
            }

            showCreateSegmentModal();
        });

        form?.addEventListener('submit', function (event) {
            if (interpolatedCoordinates.length < 2 || !boundaryInput?.value) {
                event.preventDefault();
                showWarningModal(
                    'Generate Road Shape first before saving.',
                    'Road shape required',
                    { showGenerateAction: true }
                );
            }
        });

        segmentTypeSelect?.addEventListener('change', renderSegmentTypeRulesPreview);
        renderSegmentTypeRulesPreview();
        searchController.bind();
        existingSegmentsController.init();
        modalActions.register();
        updatePanels();
    }

    segments.initWhenMapReady?.('roadSegmentMapLab', initializeRoadSegmentMap);
})();
