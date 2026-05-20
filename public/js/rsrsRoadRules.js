// Frontend helper for rsrsRoadRules interactions in the RSRS interface.

(function () {
    document.addEventListener('DOMContentLoaded', function () {
        const mapRoot = document.getElementById('roadRuleMap');

        if (!mapRoot || !mapRoot.mapApi) {
            return;
        }

        const pageConfig = window.roadRulePage || {};
        const map = mapRoot.mapApi.map;
        const dataUrl = pageConfig.dataUrl;

        const selectedSegmentPanel = document.getElementById('ruleSelectedSegmentPanel');
        const coveragePanel = document.getElementById('ruleCoveragePanel');
        const statusPanel = document.getElementById('ruleStatusPanel');
        const segmentSelect = document.getElementById('segment_id');
        const locationNameInput = document.getElementById('location_name');
        const modalElement = document.getElementById('createRoadRuleModal');
        const modalInstance = modalElement ? bootstrap.Modal.getOrCreateInstance(modalElement) : null;
        const resultsList = document.getElementById('roadRuleResultsList');
        const searchInput = document.getElementById('roadRuleSearchInput');
        const resultsCount = document.getElementById('roadRuleResultsCount');
        const loadMoreBtn = document.getElementById('roadRuleLoadMoreBtn');

        const segmentLayer = L.layerGroup().addTo(map);
        const segmentMarkerLayer = L.layerGroup().addTo(map);
        const activeMatchLayer = L.layerGroup().addTo(map);

        let renderedSegments = Array.isArray(pageConfig.segments) ? pageConfig.segments : [];
        let activeSegmentId = null;
        let activeRuleId = null;
        let currentPage = Number(pageConfig.pagination?.current_page || 1);
        let hasMorePages = Boolean(pageConfig.pagination?.has_more);
        let currentSearch = '';
        let searchDebounceHandle = null;
        let requestSerial = 0;

        // Encapsulate one UI behavior so the page stays easier to maintain.

        function escapeHtml(value) {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        // Encapsulate one UI behavior so the page stays easier to maintain.

        function createSegmentIcon(isActive) {
            return L.divIcon({
                className: `geo-rule-segment-marker${isActive ? ' is-active' : ''}`,
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

        // Encapsulate one UI behavior so the page stays easier to maintain.

        function getSegmentLatLngs(segment) {
            const coordinates = segment.boundary_coordinates?.geometry?.coordinates || [];

            if (!Array.isArray(coordinates) || coordinates.length < 2) {
                return [];
            }

            return coordinates.map((coordinate) => [coordinate[1], coordinate[0]]);
        }

        // Encapsulate one UI behavior so the page stays easier to maintain.

        function getSegmentSummary(segment) {
            const parts = [];

            if (segment.segment_type) {
                parts.push(segment.segment_type);
            }

            if (segment.length_km) {
                parts.push(`${Number(segment.length_km).toFixed(2)} km`);
            }

            if (segment.road_rules_count) {
                parts.push(`${segment.road_rules_count} rule${segment.road_rules_count === 1 ? '' : 's'}`);
            }

            return parts.join(' | ') || 'General segment';
        }

        // Encapsulate one UI behavior so the page stays easier to maintain.

        function formatRuleMeta(rule) {
            const parts = [];

            if (rule.rule_type) {
                parts.push(String(rule.rule_type).replace(/_/g, ' '));
            }

            if (rule.rule_value) {
                parts.push(rule.rule_value);
            }

            return parts.join(' | ') || 'Saved road rule';
        }

        // Encapsulate one UI behavior so the page stays easier to maintain.

        function buildTooltipContent(segment) {
            const description = segment.description
                ? `<div class="geo-rule-segment-tooltip__description">${escapeHtml(segment.description)}</div>`
                : '';

            return `
                <div class="geo-rule-segment-tooltip">
                    <div class="geo-rule-segment-tooltip__title">${escapeHtml(segment.segment_name || 'Road segment')}</div>
                    <div class="geo-rule-segment-tooltip__meta">${escapeHtml(getSegmentSummary(segment))}</div>
                    ${description}
                </div>
            `;
        }

        // Encapsulate one UI behavior so the page stays easier to maintain.

        function autoFillRuleForm(segment) {
            if (segmentSelect) {
                segmentSelect.value = String(segment.id);
                segmentSelect.dispatchEvent(new Event('change', { bubbles: true }));
            }

            if (locationNameInput && locationNameInput.dataset.autoFilled !== 'false') {
                locationNameInput.value = segment.segment_name || '';
            }

        }

        // Encapsulate one UI behavior so the page stays easier to maintain.

        function resetStyles() {
            segmentLayer.eachLayer((layer) => {
                if (typeof layer.setStyle === 'function') {
                    layer.setStyle({
                        color: '#7d8ca3',
                        weight: 4,
                        opacity: 0.48,
                    });
                }
            });

            segmentMarkerLayer.eachLayer((marker) => {
                if (marker.segmentId !== undefined) {
                    marker.setIcon(createSegmentIcon(Number(marker.segmentId) === Number(activeSegmentId)));
                }
            });
        }

        // Encapsulate one UI behavior so the page stays easier to maintain.

        function updateResultSelectionState() {
            document.querySelectorAll('[data-segment-id]').forEach((node) => {
                const isSelected = Number(node.dataset.segmentId) === Number(activeSegmentId);
                node.classList.toggle('is-selected', isSelected);
            });

            document.querySelectorAll('[data-road-rule]').forEach((node) => {
                const rule = safeJsonParse(node.dataset.roadRule);
                const isSelected = Number(rule?.id) === Number(activeRuleId);
                node.classList.toggle('is-selected', isSelected);
            });
        }

        // Encapsulate one UI behavior so the page stays easier to maintain.

        function clearActiveMatchLayer() {
            activeMatchLayer.clearLayers();
        }

        // Encapsulate one UI behavior so the page stays easier to maintain.

        function applySegmentHoverState(segmentId) {
            segmentLayer.eachLayer((layer) => {
                if (layer.segmentId === Number(segmentId) && typeof layer.setStyle === 'function') {
                    layer.setStyle({
                        color: '#d9485f',
                        weight: 5,
                        opacity: 0.95,
                    });
                }
            });

            segmentMarkerLayer.eachLayer((marker) => {
                if (marker.segmentId === Number(segmentId)) {
                    marker.getElement()?.classList.add('is-hovered');
                }
            });
        }

        // Encapsulate one UI behavior so the page stays easier to maintain.

        function clearMarkerHoverState() {
            segmentMarkerLayer.eachLayer((marker) => {
                marker.getElement()?.classList.remove('is-hovered');
            });
        }

        // Encapsulate one UI behavior so the page stays easier to maintain.

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

        // Encapsulate one UI behavior so the page stays easier to maintain.

        function restoreSegmentVisualState() {
            clearMarkerHoverState();
            resetStyles();

            if (activeSegmentId !== null) {
                applySegmentSelectedState(activeSegmentId);
            }
        }

        // Encapsulate one UI behavior so the page stays easier to maintain.

        function drawMatchGlow(segment) {
            clearActiveMatchLayer();

            const latLngs = getSegmentLatLngs(segment);

            if (latLngs.length < 2) {
                return;
            }

            L.polyline(latLngs, {
                color: '#d9485f',
                weight: 8,
                opacity: 0.18,
            }).addTo(activeMatchLayer);
        }

        // Encapsulate one UI behavior so the page stays easier to maintain.

        function resetInspector() {
            if (selectedSegmentPanel) {
                selectedSegmentPanel.textContent = 'No segment selected.';
            }

            if (coveragePanel) {
                coveragePanel.textContent = 'Hover or choose a result to inspect it.';
            }

            if (statusPanel) {
                statusPanel.textContent = 'No rule selected.';
            }
        }

        // Encapsulate one UI behavior so the page stays easier to maintain.

        function findSegment(segmentId) {
            return renderedSegments.find((item) => Number(item.id) === Number(segmentId)) || null;
        }

        // Encapsulate one UI behavior so the page stays easier to maintain.

        function updateInspectorForSegment(segment, customCoverage) {
            if (selectedSegmentPanel) {
                selectedSegmentPanel.textContent = segment?.segment_name || 'No segment selected.';
            }

            if (coveragePanel) {
                coveragePanel.textContent = customCoverage || (segment ? getSegmentSummary(segment) : 'Hover or choose a result to inspect it.');
            }

            if (!activeRuleId && statusPanel) {
                statusPanel.textContent = segment && segment.road_rules_count
                    ? `${segment.road_rules_count} linked rule${segment.road_rules_count === 1 ? '' : 's'}`
                    : 'No rule selected.';
            }
        }

        // Encapsulate one UI behavior so the page stays easier to maintain.

        function highlightSegment(segmentId, options) {
            const settings = Object.assign({
                fitBounds: true,
                openModal: false,
                prefillForm: false,
                preserveActiveRule: false,
                customCoverage: null,
            }, options || {});

            const segment = findSegment(segmentId);

            if (!segment) {
                return;
            }

            const latLngs = getSegmentLatLngs(segment);

            if (latLngs.length < 2) {
                return;
            }

            activeSegmentId = Number(segmentId);

            if (!settings.preserveActiveRule) {
                activeRuleId = null;
            }

            restoreSegmentVisualState();
            drawMatchGlow(segment);
            updateInspectorForSegment(segment, settings.customCoverage);
            updateResultSelectionState();

            if (settings.fitBounds) {
                map.fitBounds(L.latLngBounds(latLngs), {
                    padding: [30, 30],
                });
            }

            if (settings.prefillForm) {
                autoFillRuleForm(segment);
            }

            if (settings.openModal) {
                modalInstance?.show();
            }
        }

        // Encapsulate one UI behavior so the page stays easier to maintain.

        function previewSegment(segmentId, coverageText) {
            const segment = findSegment(segmentId);

            if (!segment) {
                return;
            }

            restoreSegmentVisualState();
            applySegmentHoverState(segmentId);
            drawMatchGlow(segment);
            updateInspectorForSegment(segment, coverageText);
        }

        // Encapsulate one UI behavior so the page stays easier to maintain.

        function clearPreview() {
            clearActiveMatchLayer();

            if (activeSegmentId !== null) {
                highlightSegment(activeSegmentId, {
                    fitBounds: false,
                    preserveActiveRule: true,
                    customCoverage: activeRuleId !== null ? coveragePanel?.textContent : null,
                });
                return;
            }

            restoreSegmentVisualState();
            resetInspector();
        }

        // Encapsulate one UI behavior so the page stays easier to maintain.

        function safeJsonParse(value) {
            try {
                return JSON.parse(value || '{}');
            } catch (error) {
                return null;
            }
        }

        // Encapsulate one UI behavior so the page stays easier to maintain.

        function bindSegmentLayerInteractions(layer, segment) {
            layer.segmentId = segment.id;
            layer.bindTooltip(buildTooltipContent(segment), {
                sticky: true,
                direction: 'top',
                opacity: 1,
            });
            layer.on('mouseover', function () {
                previewSegment(segment.id);
            });
            layer.on('mouseout', function () {
                clearPreview();
            });
            layer.on('click', function () {
                highlightSegment(segment.id, {
                    openModal: true,
                    prefillForm: true,
                });
            });
        }

        // Encapsulate one UI behavior so the page stays easier to maintain.

        function renderSegmentsOnMap() {
            segmentLayer.clearLayers();
            segmentMarkerLayer.clearLayers();
            clearActiveMatchLayer();

            renderedSegments.forEach((segment) => {
                const latLngs = getSegmentLatLngs(segment);

                if (latLngs.length < 2) {
                    return;
                }

                const polyline = L.polyline(latLngs, {
                    color: '#7d8ca3',
                    weight: 4,
                    opacity: 0.48,
                }).addTo(segmentLayer);

                bindSegmentLayerInteractions(polyline, segment);

                const center = L.latLngBounds(latLngs).getCenter();
                const marker = L.marker(center, {
                    icon: createSegmentIcon(Number(segment.id) === Number(activeSegmentId)),
                }).addTo(segmentMarkerLayer);

                bindSegmentLayerInteractions(marker, segment);
            });

            restoreSegmentVisualState();
        }

        // Encapsulate one UI behavior so the page stays easier to maintain.

        function segmentMarkup(segment) {
            const rulesMarkup = Array.isArray(segment.rules) && segment.rules.length
                ? `
                    <div class="geo-rule-result__rules">
                        ${segment.rules.map((rule) => `
                            <button
                                type="button"
                                class="geo-rule-chip"
                                data-road-rule='${escapeHtml(JSON.stringify(rule))}'
                                data-segment-id="${segment.id}"
                            >
                                <span>${escapeHtml(rule.rule_name)}</span>
                            </button>
                        `).join('')}
                    </div>
                `
                : '<div class="geo-rule-result__empty">No rules saved for this segment.</div>';

            return `
                <article class="geo-rule-result" data-segment-id="${segment.id}">
                    <button type="button" class="geo-rule-result__segment" data-road-rule-segment='${escapeHtml(JSON.stringify(segment))}'>
                        <span class="geo-rule-result__segment-main">
                            <span class="geo-rule-result__segment-icon">
                                <i class="bi bi-signpost-split"></i>
                            </span>
                            <span>
                                <span class="geo-rule-result__segment-name">${escapeHtml(segment.segment_name || 'Road segment')}</span>
                                <span class="geo-rule-result__segment-meta">${escapeHtml(getSegmentSummary(segment))}</span>
                            </span>
                        </span>
                        <span class="geo-rule-result__count">${segment.road_rules_count || 0}</span>
                    </button>
                    ${rulesMarkup}
                </article>
            `;
        }

        // Encapsulate one UI behavior so the page stays easier to maintain.

        function renderResultsList(segments, mode) {
            if (!resultsList) {
                return;
            }

            if (!segments.length && mode === 'replace') {
                resultsList.innerHTML = '<div class="geo-segment-list__empty">No matching segments or rules found.</div>';
                updateResultSelectionState();
                return;
            }

            const html = segments.map(segmentMarkup).join('');

            if (mode === 'append') {
                resultsList.insertAdjacentHTML('beforeend', html);
            } else {
                resultsList.innerHTML = html;
            }

            updateResultSelectionState();
        }

        // Encapsulate one UI behavior so the page stays easier to maintain.

        function updateLoadMoreButton() {
            if (!loadMoreBtn) {
                return;
            }

            loadMoreBtn.disabled = !hasMorePages;
            loadMoreBtn.querySelector('span').textContent = hasMorePages ? 'Load more' : 'No more results';
        }

        // Encapsulate one UI behavior so the page stays easier to maintain.

        function updateResultsMeta(meta) {
            currentPage = Number(meta?.current_page || 1);
            hasMorePages = Boolean(meta?.has_more);

            if (resultsCount) {
                resultsCount.textContent = meta?.total ?? renderedSegments.length;
            }

            updateLoadMoreButton();
        }

        // Encapsulate one UI behavior so the page stays easier to maintain.

        function mergeSegments(existingSegments, newSegments) {
            const merged = new Map(existingSegments.map((segment) => [Number(segment.id), segment]));

            newSegments.forEach((segment) => {
                merged.set(Number(segment.id), segment);
            });

            return Array.from(merged.values());
        }

        async function fetchSegments(options) {
            const settings = Object.assign({
                page: 1,
                append: false,
                search: '',
            }, options || {});

            const serial = ++requestSerial;

            if (loadMoreBtn && settings.append) {
                loadMoreBtn.disabled = true;
                loadMoreBtn.querySelector('span').textContent = 'Loading...';
            }

            if (resultsList && !settings.append) {
                resultsList.classList.add('is-loading');
            }

            try {
                const url = new URL(dataUrl, window.location.origin);
                url.searchParams.set('page', String(settings.page));

                if (settings.search) {
                    url.searchParams.set('search', settings.search);
                }

                const response = await fetch(url.toString(), {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                });

                if (!response.ok) {
                    throw new Error(`Request failed with status ${response.status}`);
                }

                const payload = await response.json();

                if (serial !== requestSerial) {
                    return;
                }

                const items = Array.isArray(payload.items) ? payload.items : [];

                renderedSegments = settings.append
                    ? mergeSegments(renderedSegments, items)
                    : items;

                renderSegmentsOnMap();
                renderResultsList(items, settings.append ? 'append' : 'replace');
                updateResultsMeta(payload.meta || {});

                if (settings.search.trim()) {
                    fitMapToCurrentResults();
                }

                if (activeSegmentId !== null && !findSegment(activeSegmentId)) {
                    activeSegmentId = null;
                    activeRuleId = null;
                    resetInspector();
                }
            } catch (error) {
                if (resultsList && !settings.append) {
                    resultsList.innerHTML = '<div class="geo-segment-list__empty">Unable to load results right now.</div>';
                }
            } finally {
                resultsList?.classList.remove('is-loading');
                updateLoadMoreButton();
            }
        }

        // Encapsulate one UI behavior so the page stays easier to maintain.

        function fitMapToCurrentResults() {
            const bounds = [];

            renderedSegments.forEach((segment) => {
                const latLngs = getSegmentLatLngs(segment);

                if (latLngs.length >= 2) {
                    bounds.push(...latLngs);
                }
            });

            if (bounds.length >= 2) {
                map.fitBounds(L.latLngBounds(bounds), {
                    padding: [32, 32],
                });
            }
        }

        // Encapsulate one UI behavior so the page stays easier to maintain.

        function handleSegmentButtonClick(button) {
            const segment = safeJsonParse(button.dataset.roadRuleSegment);

            if (!segment?.id) {
                return;
            }

            highlightSegment(segment.id, {
                openModal: true,
                prefillForm: true,
            });
        }

        // Encapsulate one UI behavior so the page stays easier to maintain.

        function handleRuleButtonClick(button) {
            const rule = safeJsonParse(button.dataset.roadRule);

            if (!rule) {
                return;
            }

            activeRuleId = Number(rule.id);

            if (statusPanel) {
                statusPanel.textContent = rule.is_active ? 'Active rule' : 'Inactive rule';
            }

            if (rule.segment_id) {
                highlightSegment(rule.segment_id, {
                    fitBounds: true,
                    preserveActiveRule: true,
                    customCoverage: formatRuleMeta(rule),
                });
            }

            updateResultSelectionState();
        }

        resultsList?.addEventListener('mouseover', function (event) {
            const ruleButton = event.target.closest('[data-road-rule]');

            if (ruleButton) {
                const rule = safeJsonParse(ruleButton.dataset.roadRule);

                if (rule?.segment_id) {
                    previewSegment(rule.segment_id, formatRuleMeta(rule));
                }

                return;
            }

            const segmentButton = event.target.closest('[data-road-rule-segment]');

            if (segmentButton) {
                const segment = safeJsonParse(segmentButton.dataset.roadRuleSegment);

                if (segment?.id) {
                    previewSegment(segment.id);
                }
            }
        });

        resultsList?.addEventListener('mouseout', function (event) {
            if (event.target.closest('[data-road-rule]') || event.target.closest('[data-road-rule-segment]')) {
                clearPreview();
            }
        });

        resultsList?.addEventListener('click', function (event) {
            const ruleButton = event.target.closest('[data-road-rule]');

            if (ruleButton) {
                handleRuleButtonClick(ruleButton);
                return;
            }

            const segmentButton = event.target.closest('[data-road-rule-segment]');

            if (segmentButton) {
                handleSegmentButtonClick(segmentButton);
            }
        });

        loadMoreBtn?.addEventListener('click', function () {
            if (!hasMorePages) {
                return;
            }

            fetchSegments({
                page: currentPage + 1,
                append: true,
                search: currentSearch,
            });
        });

        searchInput?.addEventListener('input', function () {
            currentSearch = this.value.trim();

            window.clearTimeout(searchDebounceHandle);

            searchDebounceHandle = window.setTimeout(function () {
                activeSegmentId = null;
                activeRuleId = null;
                resetInspector();
                fetchSegments({
                    page: 1,
                    append: false,
                    search: currentSearch,
                });
            }, 280);
        });

        segmentSelect?.addEventListener('change', function () {
            if (this.value) {
                highlightSegment(this.value, {
                    fitBounds: true,
                    preserveActiveRule: false,
                });
            }
        });

        locationNameInput?.addEventListener('input', function () {
            this.dataset.autoFilled = this.value.trim() ? 'false' : 'true';
        });

        if (locationNameInput && !locationNameInput.value.trim()) {
            locationNameInput.dataset.autoFilled = 'true';
        }

        renderSegmentsOnMap();
        renderResultsList(renderedSegments, 'replace');
        updateResultsMeta(pageConfig.pagination || {});
        resetInspector();
    });
})();
