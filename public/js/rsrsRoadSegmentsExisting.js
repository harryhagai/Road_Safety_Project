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

        function segmentMidpointLatLng(lineCoordinates, latLngs) {
            if (window.turf && Array.isArray(lineCoordinates) && lineCoordinates.length >= 2) {
                const line = turf.lineString(lineCoordinates);
                const totalKm = turf.length(line, { units: 'kilometers' });

                if (Number.isFinite(totalKm) && totalKm > 0) {
                    const midpoint = turf.along(line, totalKm / 2, { units: 'kilometers' });
                    const coordinates = midpoint?.geometry?.coordinates;
                    if (Array.isArray(coordinates) && coordinates.length >= 2) {
                        return [coordinates[1], coordinates[0]];
                    }
                }
            }

            return latLngs[Math.floor((latLngs.length - 1) / 2)] || latLngs[0];
        }

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

        function setSegmentMetaVisibility(segmentMeta, isVisible) {
            if (!segmentMeta) return;

            segmentMeta.isVisible = isVisible;
            segmentMeta.article?.toggleAttribute('hidden', !isVisible);

            if (segmentMeta.polyline) {
                if (isVisible && !layer.hasLayer(segmentMeta.polyline)) {
                    layer.addLayer(segmentMeta.polyline);
                } else if (!isVisible && layer.hasLayer(segmentMeta.polyline)) {
                    layer.removeLayer(segmentMeta.polyline);
                }
            }

            if (segmentMeta.marker) {
                if (isVisible && !layer.hasLayer(segmentMeta.marker)) {
                    layer.addLayer(segmentMeta.marker);
                } else if (!isVisible && layer.hasLayer(segmentMeta.marker)) {
                    layer.removeLayer(segmentMeta.marker);
                }
            }
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

                const marker = L.marker(segmentMidpointLatLng(coordinates, latLngs), {
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
                        article: null,
                        button: null,
                        isVisible: true,
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
                const article = button.closest('.geo-segment-item');
                if (segmentId) {
                    existingSegmentButtonsById.set(segmentId, button);
                    const segmentMeta = existingSegmentLayers.get(segmentId);
                    if (segmentMeta) {
                        segmentMeta.button = button;
                        segmentMeta.article = article;
                    }
                }

                button.addEventListener('click', function () {
                    if (!segmentId) return;
                    focusExistingSegment(segment || { id: segmentId });
                });
            });
        }

        function filterSegments(predicate) {
            let visibleCount = 0;

            existingSegments.forEach((segment) => {
                const segmentId = namespace.segmentIdKey(segment);
                if (!segmentId) return;

                const segmentMeta = existingSegmentLayers.get(segmentId);
                if (!segmentMeta) return;

                const matches = predicate(segment);
                setSegmentMetaVisibility(segmentMeta, matches);
                if (matches) {
                    visibleCount += 1;
                }
            });

            if (activeExistingSegmentId) {
                const activeMeta = existingSegmentLayers.get(activeExistingSegmentId);
                if (activeMeta && activeMeta.isVisible === false) {
                    highlightExistingSegment(null);
                }
            }

            return visibleCount;
        }

        function init() {
            drawExistingSegments();
            registerExistingSegmentButtons();
        }

        return {
            drawExistingSegments,
            filterSegments,
            focusExistingSegment,
            highlightExistingSegment,
            init,
        };
    }

    Object.assign(namespace, {
        createExistingSegmentsController,
    });
})();
