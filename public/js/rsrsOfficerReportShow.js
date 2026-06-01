// Frontend helper for officer report detail map comparison.
document.addEventListener('DOMContentLoaded', function () {
    const mapEl = document.getElementById('officerReportMiniMap');
    if (!mapEl || !window.L) {
        return;
    }

    const payloadEl = document.getElementById('officerReportMapPayload');
    if (!payloadEl) {
        return;
    }

    let reportMapPayload = null;
    try {
        reportMapPayload = JSON.parse(payloadEl.textContent || '{}');
    } catch (error) {
        return;
    }

    const point = reportMapPayload?.point || null;
    const lat = Number(point?.lat);
    const lng = Number(point?.lng);
    if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
        return;
    }

    const map = L.map(mapEl, {
        zoomControl: true,
        scrollWheelZoom: true,
        touchZoom: true,
        doubleClickZoom: true,
        dragging: true,
    }).setView([lat, lng], 15);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 19,
    }).addTo(map);

    const comparisonBounds = [[lat, lng]];

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function validPoint(candidateLat, candidateLng) {
        if (!Number.isFinite(candidateLat) || !Number.isFinite(candidateLng)) {
            return null;
        }
        if (candidateLat < -90 || candidateLat > 90 || candidateLng < -180 || candidateLng > 180) {
            return null;
        }

        return { lat: candidateLat, lng: candidateLng };
    }

    function normalizeCoordinate(coordinate) {
        if (!coordinate || typeof coordinate !== 'object') {
            return null;
        }

        if (Array.isArray(coordinate)) {
            if (coordinate.length < 2) {
                return null;
            }

            const first = Number(coordinate[0]);
            const second = Number(coordinate[1]);
            if (!Number.isFinite(first) || !Number.isFinite(second)) {
                return null;
            }

            // GeoJSON is usually [lng, lat], but some payloads arrive as [lat, lng].
            if (Math.abs(first) <= 20 && Math.abs(second) > 20) {
                return validPoint(first, second);
            }

            return validPoint(second, first);
        }

        if ('lat' in coordinate && 'lng' in coordinate) {
            return validPoint(Number(coordinate.lat), Number(coordinate.lng));
        }

        if ('latitude' in coordinate && 'longitude' in coordinate) {
            return validPoint(Number(coordinate.latitude), Number(coordinate.longitude));
        }

        return null;
    }

    function collectPoints(node, points) {
        const normalized = normalizeCoordinate(node);
        if (normalized) {
            points.push(normalized);
            return;
        }

        if (Array.isArray(node)) {
            node.forEach(function (child) {
                collectPoints(child, points);
            });
            return;
        }

        if (node && typeof node === 'object') {
            Object.values(node).forEach(function (child) {
                collectPoints(child, points);
            });
        }
    }

    function segmentLatLngs(boundaryCoordinates) {
        const root = boundaryCoordinates || {};
        const coordinatesRoot = root?.geometry?.coordinates
            || root?.features?.[0]?.geometry?.coordinates
            || root?.coordinates
            || root;
        const points = [];
        collectPoints(coordinatesRoot, points);

        const latLngs = [];
        points.forEach(function (pointItem) {
            const mappedPoint = [pointItem.lat, pointItem.lng];
            const previous = latLngs[latLngs.length - 1];
            if (!previous || Math.abs(previous[0] - mappedPoint[0]) > 1e-9 || Math.abs(previous[1] - mappedPoint[1]) > 1e-9) {
                latLngs.push(mappedPoint);
            }
        });

        return latLngs;
    }

    const segments = Array.isArray(reportMapPayload?.segments) ? reportMapPayload.segments : [];

    segments.forEach(function (segment) {
        const latLngs = segmentLatLngs(segment.boundary_coordinates);
        if (latLngs.length < 2) {
            return;
        }

        const segmentLine = L.polyline(latLngs, {
            color: '#2563eb',
            weight: 4,
            opacity: 0.78,
            dashArray: segment.match_source === 'manual' ? '7 6' : null,
        }).addTo(map);

        segmentLine.bindPopup(
            '<div class="roadofficer-hotspot-popup">' +
                '<strong>' + escapeHtml(segment.name || 'Matched segment') + '</strong><br>' +
                'Match: ' + escapeHtml(segment.match_source || 'unknown') +
            '</div>'
        );

        comparisonBounds.push(...latLngs);
    });

    const reportMarker = L.circleMarker([lat, lng], {
        radius: 8,
        color: '#b91c1c',
        weight: 2,
        fillColor: '#ef4444',
        fillOpacity: 0.92,
    }).addTo(map);

    reportMarker.bindPopup(
        '<div class="roadofficer-hotspot-popup">' +
            '<strong>' + escapeHtml(point?.label || 'Report location') + '</strong><br>' +
            escapeHtml(point?.location || mapEl.dataset.location || 'Unknown location') + '<br>' +
            Number(lat).toFixed(6) + ', ' + Number(lng).toFixed(6) +
        '</div>'
    ).openPopup();

    const nearestPoint = reportMapPayload?.nearest_point || null;
    const nearestLat = Number(nearestPoint?.lat);
    const nearestLng = Number(nearestPoint?.lng);
    if (Number.isFinite(nearestLat) && Number.isFinite(nearestLng)) {
        const nearestMarker = L.circleMarker([nearestLat, nearestLng], {
            radius: 6,
            color: '#0f766e',
            weight: 2,
            fillColor: '#14b8a6',
            fillOpacity: 0.95,
        }).addTo(map);

        const distanceMeters = Number(nearestPoint?.distance_meters);
        const distanceLabel = Number.isFinite(distanceMeters)
            ? (distanceMeters < 1000
                ? `${distanceMeters.toFixed(1)} m`
                : `${(distanceMeters / 1000).toFixed(2)} km`)
            : 'N/A';

        nearestMarker.bindPopup(
            '<div class="roadofficer-hotspot-popup">' +
                '<strong>Nearest segment point</strong><br>' +
                Number(nearestLat).toFixed(6) + ', ' + Number(nearestLng).toFixed(6) + '<br>' +
                'Distance: ' + escapeHtml(distanceLabel) +
            '</div>'
        );

        L.polyline([[lat, lng], [nearestLat, nearestLng]], {
            color: '#0f766e',
            weight: 2,
            opacity: 0.9,
            dashArray: '4 4',
        }).addTo(map);

        comparisonBounds.push([nearestLat, nearestLng]);
    }

    if (comparisonBounds.length > 1) {
        map.fitBounds(comparisonBounds, {
            padding: [24, 24],
            maxZoom: 17,
        });
    }

    requestAnimationFrame(function () {
        map.invalidateSize();
    });
});
