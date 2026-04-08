(function () {
    document.addEventListener('DOMContentLoaded', function () {
        const mapRoot = document.getElementById('roadRuleMap');

        if (!mapRoot || !mapRoot.mapApi) {
            return;
        }

        const map = mapRoot.mapApi.map;
        const segments = window.roadRulePage?.segments || [];
        const rules = window.roadRulePage?.rules || [];

        const selectedSegmentPanel = document.getElementById('ruleSelectedSegmentPanel');
        const coveragePanel = document.getElementById('ruleCoveragePanel');
        const statusPanel = document.getElementById('ruleStatusPanel');
        const segmentSelect = document.getElementById('segment_id');
        const ruleNameInput = document.getElementById('rule_name');
        const locationNameInput = document.getElementById('location_name');
        const modalElement = document.getElementById('createRoadRuleModal');
        const modalInstance = modalElement ? bootstrap.Modal.getOrCreateInstance(modalElement) : null;

        const segmentLayer = L.layerGroup().addTo(map);
        const segmentMarkerLayer = L.layerGroup().addTo(map);
        let activeLayer = null;
        let activeSegmentId = null;

        function createSegmentIcon() {
            return L.divIcon({
                className: 'geo-rule-segment-marker',
                html: `
                    <div class="geo-rule-segment-marker__pin">
                        <i class="bi bi-signpost-split-fill"></i>
                    </div>
                `,
                iconSize: [34, 34],
                iconAnchor: [17, 17],
                popupAnchor: [0, -12],
            });
        }

        function getSegmentLatLngs(segment) {
            const coordinates = segment.boundary_coordinates?.geometry?.coordinates || [];

            if (!Array.isArray(coordinates) || coordinates.length < 2) {
                return [];
            }

            return coordinates.map((coordinate) => [coordinate[1], coordinate[0]]);
        }

        function getSegmentSummary(segment) {
            const parts = [];

            if (segment.segment_type) {
                parts.push(segment.segment_type);
            }

            if (segment.length_km) {
                parts.push(`${Number(segment.length_km).toFixed(2)} km`);
            }

            return parts.join(' | ') || 'General segment';
        }

        function buildTooltipContent(segment) {
            const description = segment.description
                ? `<div class="geo-rule-segment-tooltip__description">${segment.description}</div>`
                : '';

            return `
                <div class="geo-rule-segment-tooltip">
                    <div class="geo-rule-segment-tooltip__title">${segment.segment_name || 'Road segment'}</div>
                    <div class="geo-rule-segment-tooltip__meta">${getSegmentSummary(segment)}</div>
                    ${description}
                </div>
            `;
        }

        function autoFillRuleForm(segment) {
            if (segmentSelect) {
                segmentSelect.value = String(segment.id);
            }

            if (locationNameInput && locationNameInput.dataset.autoFilled !== 'false') {
                locationNameInput.value = segment.segment_name || '';
            }

            if (ruleNameInput && ruleNameInput.dataset.autoFilled !== 'false') {
                ruleNameInput.value = `${segment.segment_name || 'Road segment'} rule`;
            }
        }

        function resetStyles() {
            segmentLayer.eachLayer((layer) => {
                if (typeof layer.setStyle === 'function') {
                    layer.setStyle({
                        color: '#7d8ca3',
                        weight: 4,
                        opacity: 0.55,
                    });
                }
            });
        }

        function applySegmentHoverState(segmentId) {
            segmentLayer.eachLayer((layer) => {
                if (layer.segmentId === Number(segmentId) && typeof layer.setStyle === 'function') {
                    layer.setStyle({
                        color: '#d9485f',
                        weight: 5,
                        opacity: 0.9,
                    });
                }
            });
        }

        function applySegmentSelectedState(segmentId) {
            segmentLayer.eachLayer((layer) => {
                if (layer.segmentId === Number(segmentId) && typeof layer.setStyle === 'function') {
                    layer.setStyle({
                        color: '#0d6efd',
                        weight: 5,
                        opacity: 0.95,
                    });
                }
            });
        }

        function restoreSegmentVisualState() {
            resetStyles();

            if (activeSegmentId !== null) {
                applySegmentSelectedState(activeSegmentId);
            }
        }

        function renderSegments() {
            segmentLayer.clearLayers();
            segmentMarkerLayer.clearLayers();

            segments.forEach((segment) => {
                const latLngs = getSegmentLatLngs(segment);

                if (latLngs.length < 2) {
                    return;
                }

                const polyline = L.polyline(latLngs, {
                    color: '#7d8ca3',
                    weight: 4,
                    opacity: 0.55,
                }).addTo(segmentLayer);

                polyline.segmentId = segment.id;
                polyline.bindTooltip(buildTooltipContent(segment), {
                    sticky: true,
                    direction: 'top',
                    opacity: 1,
                });
                polyline.on('mouseover', function () {
                    restoreSegmentVisualState();
                    applySegmentHoverState(segment.id);
                });
                polyline.on('mouseout', function () {
                    restoreSegmentVisualState();
                });
                polyline.on('click', function () {
                    highlightSegment(segment.id);
                    autoFillRuleForm(segment);
                    modalInstance?.show();
                });

                const center = L.latLngBounds(latLngs).getCenter();
                const marker = L.marker(center, {
                    icon: createSegmentIcon(),
                }).addTo(segmentMarkerLayer);

                marker.segmentId = segment.id;
                marker.bindTooltip(buildTooltipContent(segment), {
                    sticky: true,
                    direction: 'top',
                    opacity: 1,
                });
                marker.on('mouseover', function () {
                    restoreSegmentVisualState();
                    applySegmentHoverState(segment.id);
                });
                marker.on('mouseout', function () {
                    restoreSegmentVisualState();
                });
                marker.on('click', function () {
                    highlightSegment(segment.id);
                    autoFillRuleForm(segment);
                    modalInstance?.show();
                });
            });
        }

        function resetInspector() {
            if (selectedSegmentPanel) {
                selectedSegmentPanel.textContent = 'No segment selected.';
            }

            if (coveragePanel) {
                coveragePanel.textContent = 'Choose a rule or segment to inspect its coverage.';
            }

            if (statusPanel) {
                statusPanel.textContent = 'No rule selected.';
            }
        }

        function highlightSegment(segmentId) {
            const segment = segments.find((item) => Number(item.id) === Number(segmentId));

            if (!segment) {
                return;
            }

            const latLngs = getSegmentLatLngs(segment);

            if (latLngs.length < 2) {
                return;
            }

            activeSegmentId = Number(segmentId);
            restoreSegmentVisualState();

            if (activeLayer) {
                map.removeLayer(activeLayer);
                activeLayer = null;
            }

            activeLayer = L.polyline(latLngs, {
                color: '#d9485f',
                weight: 6,
                opacity: 0.35,
            }).addTo(map);

            map.fitBounds(L.latLngBounds(latLngs), {
                padding: [30, 30],
            });

            if (selectedSegmentPanel) {
                selectedSegmentPanel.textContent = segment.segment_name || 'Unnamed segment';
            }

            if (coveragePanel) {
                coveragePanel.textContent = getSegmentSummary(segment);
            }
        }

        document.querySelectorAll('[data-road-rule]').forEach((button) => {
            button.addEventListener('click', function () {
                const rule = JSON.parse(button.dataset.roadRule || '{}');

                if (statusPanel) {
                    statusPanel.textContent = rule.is_active ? 'Active rule' : 'Inactive rule';
                }

                if (coveragePanel) {
                    coveragePanel.textContent = `${rule.rule_type || 'Rule'}${rule.rule_value ? ` | ${rule.rule_value}` : ''}`;
                }

                if (selectedSegmentPanel) {
                    selectedSegmentPanel.textContent = rule.segment_name || 'No linked segment';
                }

                if (rule.segment_id) {
                    highlightSegment(rule.segment_id);
                }
            });
        });

        segmentSelect?.addEventListener('change', function () {
            if (this.value) {
                highlightSegment(this.value);
            }
        });

        ruleNameInput?.addEventListener('input', function () {
            this.dataset.autoFilled = this.value.trim() ? 'false' : 'true';
        });

        locationNameInput?.addEventListener('input', function () {
            this.dataset.autoFilled = this.value.trim() ? 'false' : 'true';
        });

        if (ruleNameInput && !ruleNameInput.value.trim()) {
            ruleNameInput.dataset.autoFilled = 'true';
        }

        if (locationNameInput && !locationNameInput.value.trim()) {
            locationNameInput.dataset.autoFilled = 'true';
        }

        renderSegments();
        resetInspector();
    });
})();
