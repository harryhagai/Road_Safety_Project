(function () {
    function getDistanceInKm(pointA, pointB) {
        const toRadians = (value) => (value * Math.PI) / 180;
        const earthRadiusKm = 6371;

        const latDelta = toRadians(pointB.lat - pointA.lat);
        const lngDelta = toRadians(pointB.lng - pointA.lng);
        const a =
            Math.sin(latDelta / 2) * Math.sin(latDelta / 2) +
            Math.cos(toRadians(pointA.lat)) *
                Math.cos(toRadians(pointB.lat)) *
                Math.sin(lngDelta / 2) *
                Math.sin(lngDelta / 2);

        return earthRadiusKm * (2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a)));
    }

    function createGeometry(points) {
        return {
            type: 'Feature',
            geometry: {
                type: 'LineString',
                coordinates: points.map((point) => [point.lng, point.lat]),
            },
            properties: {
                point_count: points.length,
            },
        };
    }

    function createPointIcon(index, isLatest) {
        return L.divIcon({
            className: `geo-point-marker${isLatest ? ' geo-point-marker--latest' : ''}`,
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

    function getSegmentNameSuggestion(points) {
        if (!Array.isArray(points) || points.length === 0) {
            return '';
        }

        const firstPoint = points[0];
        const lastPoint = points[points.length - 1];

        const firstRoad = firstPoint.address?.road || firstPoint.address?.pedestrian || firstPoint.address?.footway || null;
        const lastRoad = lastPoint.address?.road || lastPoint.address?.pedestrian || lastPoint.address?.footway || null;

        const firstArea =
            firstPoint.address?.suburb ||
            firstPoint.address?.neighbourhood ||
            firstPoint.address?.ward ||
            firstPoint.address?.city_district ||
            firstPoint.address?.city ||
            null;
        const lastArea =
            lastPoint.address?.suburb ||
            lastPoint.address?.neighbourhood ||
            lastPoint.address?.ward ||
            lastPoint.address?.city_district ||
            lastPoint.address?.city ||
            null;

        if (firstRoad && lastRoad && firstRoad === lastRoad) {
            return `${firstRoad} segment`;
        }

        if (firstRoad && lastRoad) {
            return `${firstRoad} - ${lastRoad} link`;
        }

        if (firstArea && lastArea && firstArea === lastArea) {
            return `${firstArea} segment`;
        }

        if (firstArea && lastArea) {
            return `${firstArea} - ${lastArea} segment`;
        }

        if (firstPoint.displayName) {
            return `${firstPoint.displayName.split(',')[0]} segment`;
        }

        return '';
    }

    function getUniqueSegmentName(candidate, existingNames) {
        const normalizedExisting = new Set(
            existingNames
                .filter(Boolean)
                .map((name) => String(name).trim().toLowerCase())
        );

        const baseName = String(candidate || '').trim();

        if (!baseName) {
            return '';
        }

        if (!normalizedExisting.has(baseName.toLowerCase())) {
            return baseName;
        }

        let suffix = 2;
        let nextCandidate = `${baseName} (${suffix})`;

        while (normalizedExisting.has(nextCandidate.toLowerCase())) {
            suffix += 1;
            nextCandidate = `${baseName} (${suffix})`;
        }

        return nextCandidate;
    }

    function isSamePoint(point, lat, lng) {
        return (
            Math.abs(Number(point.lat) - Number(lat)) < 0.000001 &&
            Math.abs(Number(point.lng) - Number(lng)) < 0.000001
        );
    }

    document.addEventListener('DOMContentLoaded', function () {
        const mapRoot = document.getElementById('roadSegmentMapLab');

        if (!mapRoot || !mapRoot.mapApi) {
            return;
        }

        const map = mapRoot.mapApi.map;
        const existingSegments = window.roadSegmentPage?.existingSegments || [];
        const existingSegmentNames = existingSegments.map((segment) => segment.segment_name);
        const selectedPoints = [];

        const pointCountTarget = document.getElementById('segmentPointCount');
        const lengthTarget = document.getElementById('segmentLengthPreview');
        const openModalBtn = document.getElementById('openSegmentModalBtn');
        const undoBtn = document.getElementById('undoSegmentPointBtn');
        const clearBtn = document.getElementById('clearSegmentPointsBtn');
        const boundaryInput = document.getElementById('boundary_coordinates');
        const pointSummary = document.getElementById('segment_point_summary');
        const lengthInput = document.getElementById('length_km');
        const modalElement = document.getElementById('createRoadSegmentModal');
        const segmentNameInput = document.getElementById('segment_name');

        const pointLayer = L.layerGroup().addTo(map);
        const existingLayer = L.layerGroup().addTo(map);
        let workingLine = null;

        if (segmentNameInput && !segmentNameInput.value.trim()) {
            segmentNameInput.dataset.autoSuggested = 'true';
        }

        function renderExistingSegments() {
            existingLayer.clearLayers();

            existingSegments.forEach((segment) => {
                const coordinates = segment.boundary_coordinates?.geometry?.coordinates || [];

                if (!Array.isArray(coordinates) || coordinates.length < 2) {
                    return;
                }

                const latLngs = coordinates.map((coordinate) => [coordinate[1], coordinate[0]]);
                const polyline = L.polyline(latLngs, {
                    color: '#7d8ca3',
                    weight: 4,
                    opacity: 0.55,
                }).addTo(existingLayer);

                polyline.bindTooltip(segment.segment_name || 'Road segment');
            });
        }

        function calculateLength(points) {
            if (points.length < 2) {
                return 0;
            }

            let total = 0;

            for (let index = 1; index < points.length; index += 1) {
                total += getDistanceInKm(points[index - 1], points[index]);
            }

            return total;
        }

        function refreshWorkingSegment() {
            pointLayer.clearLayers();

            selectedPoints.forEach((point, index) => {
                const isLatest = index === selectedPoints.length - 1;
                L.marker([point.lat, point.lng], {
                    icon: createPointIcon(index, isLatest),
                })
                    .bindTooltip(`Point ${index + 1}`)
                    .addTo(pointLayer);
            });

            if (workingLine) {
                map.removeLayer(workingLine);
                workingLine = null;
            }

            if (selectedPoints.length >= 2) {
                workingLine = L.polyline(
                    selectedPoints.map((point) => [point.lat, point.lng]),
                    {
                        color: '#0d6efd',
                        weight: 5,
                        opacity: 0.9,
                    }
                ).addTo(map);
            }

            const lengthKm = calculateLength(selectedPoints);
            const geometry = selectedPoints.length >= 2 ? createGeometry(selectedPoints) : null;

            if (pointCountTarget) {
                pointCountTarget.textContent = `${selectedPoints.length} point${selectedPoints.length === 1 ? '' : 's'} selected`;
            }

            if (lengthTarget) {
                lengthTarget.textContent = `${lengthKm.toFixed(2)} km`;
            }

            if (lengthInput) {
                lengthInput.value = lengthKm > 0 ? lengthKm.toFixed(2) : '';
            }

            if (pointSummary) {
                pointSummary.value = `${selectedPoints.length} point${selectedPoints.length === 1 ? '' : 's'}`;
            }

            if (boundaryInput) {
                boundaryInput.value = geometry ? JSON.stringify(geometry) : '';
            }

            if (openModalBtn) {
                openModalBtn.disabled = selectedPoints.length < 2;
            }

            if (segmentNameInput?.dataset.autoSuggested !== 'false') {
                const suggestedName = getUniqueSegmentName(
                    getSegmentNameSuggestion(selectedPoints),
                    existingSegmentNames
                );
                segmentNameInput.value = suggestedName;
            }
        }

        map.on('rsrs:point-selected', function (event) {
            if (typeof event.lat !== 'number' || typeof event.lng !== 'number') {
                return;
            }

            selectedPoints.push({
                lat: event.lat,
                lng: event.lng,
            });

            refreshWorkingSegment();
        });

        map.on('rsrs:location-resolved', function (event) {
            const targetPoint = selectedPoints.find((point) => isSamePoint(point, event.lat, event.lng));

            if (!targetPoint) {
                return;
            }

            targetPoint.displayName = event.displayName || null;
            targetPoint.address = event.address || {};

            if (segmentNameInput?.dataset.autoSuggested !== 'false') {
                const suggestedName = getUniqueSegmentName(
                    getSegmentNameSuggestion(selectedPoints),
                    existingSegmentNames
                );
                segmentNameInput.value = suggestedName;
            }
        });

        undoBtn?.addEventListener('click', function () {
            selectedPoints.pop();
            refreshWorkingSegment();
        });

        clearBtn?.addEventListener('click', function () {
            selectedPoints.splice(0, selectedPoints.length);
            refreshWorkingSegment();
        });

        modalElement?.addEventListener('show.bs.modal', function (event) {
            if (selectedPoints.length < 2) {
                event.preventDefault();
                window.showAcademicUiAlert?.({
                    theme: 'warning',
                    title: 'More points needed',
                    text: 'Select at least two points on the map before saving a road segment.',
                    showConfirmButton: true,
                    confirmButtonText: '<i class="bi bi-check2 me-1"></i> OK',
                });
            }
        });

        segmentNameInput?.addEventListener('input', function () {
            this.dataset.autoSuggested = this.value.trim() ? 'false' : 'true';
        });

        document.querySelectorAll('[data-existing-segment]').forEach((button) => {
            button.addEventListener('click', function () {
                const segment = JSON.parse(button.dataset.existingSegment || '{}');
                const coordinates = segment.boundary_coordinates?.geometry?.coordinates || [];

                if (!Array.isArray(coordinates) || coordinates.length < 2) {
                    return;
                }

                const latLngs = coordinates.map((coordinate) => [coordinate[1], coordinate[0]]);
                const bounds = L.latLngBounds(latLngs);

                existingLayer.eachLayer((layer) => {
                    if (typeof layer.setStyle === 'function') {
                        layer.setStyle({
                            color: '#7d8ca3',
                            weight: 4,
                            opacity: 0.55,
                        });
                    }
                });

                existingLayer.eachLayer((layer) => {
                    if (layer.getTooltip && layer.getTooltip()?.getContent() === (segment.segment_name || 'Road segment')) {
                        layer.setStyle({
                            color: '#0d6efd',
                            weight: 5,
                            opacity: 0.95,
                        });
                    }
                });

                map.fitBounds(bounds, {
                    padding: [30, 30],
                });
            });
        });

        renderExistingSegments();
        refreshWorkingSegment();
    });
})();
