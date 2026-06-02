// Existing segment map layer/list interactions.

(function () {
    const namespace = window.RsrsRoadSegments = window.RsrsRoadSegments || {};

    function createExistingSegmentsController(options) {
        const {
            map,
            segments,
            layer,
            buttons,
            fitOnDraw = true,
            onHighlight,
        } = options;

        const existingSegments = Array.isArray(segments) ? segments : [];
        const existingSegmentButtons = Array.isArray(buttons) ? buttons : [];
        const existingSegmentLayers = new Map();
        const existingSegmentButtonsById = new Map();
        let activeExistingSegmentId = null;

        function applyExistingSegmentVisual(segmentMeta, isActive) {
            if (!segmentMeta) return;

            segmentMeta.polyline?.setStyle({
                color: isActive ? '#111827' : segmentMeta.baseColor,
                weight: isActive ? 6 : 4,
                opacity: isActive ? 1 : 0.85,
            });

            if (segmentMeta.marker) {
                segmentMeta.marker.setIcon(namespace.createExistingSegmentIcon(segmentMeta.baseColor, isActive));
                segmentMeta.marker.setZIndexOffset(isActive ? 1200 : 650);
            }
        }

        function highlightExistingSegment(segmentId) {
            const currentLayer = activeExistingSegmentId ? existingSegmentLayers.get(activeExistingSegmentId) : null;
            applyExistingSegmentVisual(currentLayer, false);

            existingSegmentButtons.forEach((button) => button.closest('.geo-segment-item')?.classList.remove('is-active'));

            activeExistingSegmentId = segmentId || null;
            if (!activeExistingSegmentId) {
                onHighlight?.(null);
                return;
            }

            const nextLayer = existingSegmentLayers.get(activeExistingSegmentId);
            applyExistingSegmentVisual(nextLayer, true);
            nextLayer?.polyline?.bringToFront();

            const activeButton = existingSegmentButtonsById.get(activeExistingSegmentId);
            if (activeButton) {
                activeButton.closest('.geo-segment-item')?.classList.add('is-active');
            }
            onHighlight?.(nextLayer?.segment || null);
        }

        function focusExistingSegment(segment) {
            const segmentId = namespace.segmentIdKey(segment);
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
            layer.clearLayers();
            existingSegmentLayers.clear();
            const allPoints = [];

            existingSegments.forEach((segment) => {
                const coordinates = namespace.extractLineCoordinates(segment?.boundary_coordinates);
                if (coordinates.length < 2) {
                    return;
                }

                const latLngs = coordinates.map((pair) => [pair[1], pair[0]]);
                const segmentTypeColor = namespace.resolveSegmentColor(segment);
                const polyline = L.polyline(latLngs, {
                    color: segmentTypeColor,
                    weight: 4,
                    opacity: 0.85,
                }).addTo(layer);

                const segmentName = namespace.escapeHtml(segment?.segment_name || 'Unnamed segment');
                const segmentType = namespace.escapeHtml(segment?.segment_type || 'General segment');
                const segmentLength = Number(segment?.length_km);
                const lengthText = Number.isFinite(segmentLength) ? `${segmentLength.toFixed(2)} km` : 'Length N/A';
                const detailsMarkup = [
                    `<strong>${segmentName}</strong>`,
                    `Type: ${segmentType}`,
                    `Rules: ${namespace.formatSegmentRules(segment)}`,
                    `Length: ${namespace.escapeHtml(lengthText)}`,
                ].join('<br>');

                polyline.bindPopup(detailsMarkup);
                polyline.bindTooltip(detailsMarkup, {
                    direction: 'top',
                    sticky: true,
                    opacity: 0.95,
                });

                const center = L.latLngBounds(latLngs).getCenter();
                const marker = L.marker(center, {
                    icon: namespace.createExistingSegmentIcon(segmentTypeColor),
                    zIndexOffset: 650,
                }).addTo(layer);
                marker.bindPopup(detailsMarkup);
                marker.bindTooltip(detailsMarkup, {
                    direction: 'top',
                    opacity: 0.95,
                });

                const segmentId = namespace.segmentIdKey(segment);
                if (segmentId) {
                    const segmentMeta = {
                        segment,
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

            if (fitOnDraw && allPoints.length > 1) {
                map.fitBounds(L.latLngBounds(allPoints), { padding: [24, 24], maxZoom: 16 });
            }
        }

        function registerExistingSegmentButtons() {
            existingSegmentButtons.forEach((button) => {
                const segment = namespace.parseSegmentFromButton(button, 'data-existing-segment');
                const segmentId = namespace.segmentIdKey(segment);
                if (segmentId) {
                    existingSegmentButtonsById.set(segmentId, button);
                }

                button.addEventListener('click', function () {
                    if (!segmentId) return;
                    focusExistingSegment(segment || { id: segmentId });
                });
            });
        }

        function init() {
            registerExistingSegmentButtons();
            drawExistingSegments();
        }

        return {
            drawExistingSegments,
            focusExistingSegment,
            highlightExistingSegment,
            init,
        };
    }

    Object.assign(namespace, {
        createExistingSegmentsController,
    });
})();
