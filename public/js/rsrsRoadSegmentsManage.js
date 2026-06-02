// Dedicated segment management map/list workflow.

(function () {
    const segments = window.RsrsRoadSegments || {};

    function initializeRoadSegmentManagementMap(mapRoot) {
        if (!mapRoot?.mapApi || !window.L) return;

        const map = mapRoot.mapApi.map;
        map.setView(segments.DAR_ES_SALAAM_CENTER, Math.max(12, map.getZoom() || 12));

        const pageConfig = window.roadSegmentManagePage || {};
        const existingSegments = Array.isArray(pageConfig.existingSegments) ? pageConfig.existingSegments : [];
        const segmentTypesWithRules = Array.isArray(pageConfig.segmentTypesWithRules) ? pageConfig.segmentTypesWithRules : [];
        const updateUrlTemplate = String(pageConfig.updateUrlTemplate || '');
        const destroyUrlTemplate = String(pageConfig.destroyUrlTemplate || '');

        const statusTarget = document.getElementById('segmentManageStatus');
        const searchInput = document.getElementById('segmentManageSearch');
        const searchClearButton = document.getElementById('segmentManageSearchClear');
        const searchStatusTarget = document.getElementById('segmentManageSearchStatus');
        const visibleCountTarget = document.getElementById('segmentManageVisibleCount');
        const noResultsTarget = document.getElementById('segmentManageNoResults');
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

        function buildSearchText(segment) {
            return [
                segment?.segment_name,
                segment?.segment_type,
                segment?.description,
            ]
                .map((value) => String(value || '').toLowerCase().trim())
                .filter(Boolean)
                .join(' ');
        }

        function updateSearchUi(query, visibleCount) {
            if (visibleCountTarget) {
                visibleCountTarget.textContent = String(visibleCount);
            }

            if (searchClearButton) {
                searchClearButton.hidden = query.length === 0;
            }

            if (noResultsTarget) {
                noResultsTarget.hidden = !(query && visibleCount === 0);
            }

            if (!searchStatusTarget) {
                return;
            }

            if (!query) {
                searchStatusTarget.textContent = 'Showing all saved segments.';
                return;
            }

            if (visibleCount === 0) {
                searchStatusTarget.textContent = 'No matching segments found.';
                return;
            }

            searchStatusTarget.textContent = `Showing ${visibleCount} matching segment${visibleCount === 1 ? '' : 's'}.`;
        }

        const existingSegmentsController = segments.createExistingSegmentsController({
            map,
            segments: existingSegments,
            layer: existingSegmentsLayer,
            buttons: existingSegmentButtons,
            onHighlight: setStatus,
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
            onEdit: existingSegmentsController.focusExistingSegment,
            onDelete: existingSegmentsController.focusExistingSegment,
        });

        function applySearch() {
            const query = String(searchInput?.value || '').toLowerCase().trim();
            const visibleCount = existingSegmentsController.filterSegments((segment) => {
                if (!query) {
                    return true;
                }

                return buildSearchText(segment).includes(query);
            });

            updateSearchUi(query, visibleCount);
        }

        existingSegmentsController.init();
        modalActions.register();
        searchInput?.addEventListener('input', applySearch);
        searchClearButton?.addEventListener('click', function () {
            if (!searchInput) return;
            searchInput.value = '';
            searchInput.focus();
            applySearch();
        });
        applySearch();
        setStatus(null);
    }

    segments.initWhenMapReady?.('roadSegmentManagementMap', initializeRoadSegmentManagementMap);
})();
