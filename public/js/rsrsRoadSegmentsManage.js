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

        existingSegmentsController.init();
        modalActions.register();
        setStatus(null);
    }

    segments.initWhenMapReady?.('roadSegmentManagementMap', initializeRoadSegmentManagementMap);
})();
