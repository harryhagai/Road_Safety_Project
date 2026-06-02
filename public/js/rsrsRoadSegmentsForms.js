// Road-segment edit/delete modal actions.

(function () {
    const namespace = window.RsrsRoadSegments = window.RsrsRoadSegments || {};

    function createSegmentModalActions(options) {
        const {
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
            onEdit,
            onDelete,
        } = options;

        const rules = Array.isArray(segmentTypesWithRules) ? segmentTypesWithRules : [];

        function renderEditRulesPreview() {
            namespace.renderSegmentTypeRulesPreviewForSelect(
                editTypeSelect,
                editTypeRulesPreview,
                rules,
                'Select a segment type to preview default rules.',
                'No default rules for this segment type.'
            );
        }

        function prepareEditModal(segment) {
            const segmentId = namespace.segmentIdKey(segment);
            if (!segmentId || !editForm) return;

            const updateUrl = namespace.buildSegmentActionUrl(updateUrlTemplate, segmentId);
            if (updateUrl) {
                editForm.setAttribute('action', updateUrl);
            }

            if (editNameInput) {
                editNameInput.value = String(segment?.segment_name || '');
            }
            if (editTypeSelect) {
                editTypeSelect.value = segment?.segment_type_id ? String(segment.segment_type_id) : '';
            }
            if (editDescriptionInput) {
                editDescriptionInput.value = String(segment?.description || '');
            }
            if (editLengthInput) {
                const length = Number(segment?.length_km);
                editLengthInput.value = Number.isFinite(length) && length > 0 ? length.toFixed(2) : '';
            }

            renderEditRulesPreview();
            onEdit?.(segment);
        }

        function prepareDeleteModal(segment) {
            const segmentId = namespace.segmentIdKey(segment);
            if (!segmentId || !deleteForm) return;

            const destroyUrl = namespace.buildSegmentActionUrl(destroyUrlTemplate, segmentId);
            if (destroyUrl) {
                deleteForm.setAttribute('action', destroyUrl);
            }

            if (deleteNameTarget) {
                deleteNameTarget.textContent = String(segment?.segment_name || 'this segment');
            }
            onDelete?.(segment);
        }

        function register() {
            document.querySelectorAll('[data-edit-segment-trigger]').forEach((button) => {
                button.addEventListener('click', function () {
                    prepareEditModal(namespace.parseSegmentFromButton(button, 'data-segment'));
                });
            });

            document.querySelectorAll('[data-delete-segment-trigger]').forEach((button) => {
                button.addEventListener('click', function () {
                    prepareDeleteModal(namespace.parseSegmentFromButton(button, 'data-segment'));
                });
            });

            editTypeSelect?.addEventListener('change', renderEditRulesPreview);
        }

        return {
            prepareDeleteModal,
            prepareEditModal,
            register,
            renderEditRulesPreview,
        };
    }

    Object.assign(namespace, {
        createSegmentModalActions,
    });
})();
