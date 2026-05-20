// Frontend helper for rsrsOfficerDashboard interactions in the RSRS interface.

document.addEventListener('DOMContentLoaded', function () {
    const mapEl = document.getElementById('officerHotspotsMap');
    const payload = window.rsrsOfficerDashboardMap;

    if (!mapEl || !payload || !window.L) {
        return;
    }

    const mapConfig = payload.mapConfig || {};
    const hotspots = Array.isArray(payload.hotspots) ? payload.hotspots : [];

    if (hotspots.length === 0) {
        return;
    }

    const map = L.map(mapEl, {
        zoomControl: true,
        scrollWheelZoom: true,
    }).setView(
        [
            Number(mapConfig?.defaultCenter?.lat || -6.8),
            Number(mapConfig?.defaultCenter?.lng || 39.28),
        ],
        Number(mapConfig.defaultZoom || 12)
    );

    L.tileLayer(mapConfig?.tiles?.url, {
        attribution: mapConfig?.tiles?.attribution,
        minZoom: Number(mapConfig.minZoom || 3),
        maxZoom: Number(mapConfig.maxZoom || 19),
    }).addTo(map);

    const severityColors = {
        critical: '#991b1b',
        high: '#b91c1c',
        medium: '#d97706',
        low: '#166534',
    };

    const bounds = [];
    const markersById = {};

    // Encapsulate one UI behavior so the page stays easier to maintain.
    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    hotspots.forEach(function (hotspot) {
        const point = [Number(hotspot.lat), Number(hotspot.lng)];
        const color = severityColors[hotspot.severity] || severityColors.medium;

        bounds.push(point);

        const marker = L.circleMarker(point, {
            radius: 8,
            color: color,
            weight: 2,
            fillColor: color,
            fillOpacity: 0.92,
        }).addTo(map);

        L.circle(point, {
            radius: Number(hotspot.radius || 100),
            color: color,
            weight: 1,
            fillColor: color,
            fillOpacity: 0.12,
        }).addTo(map);

        marker.bindPopup(
            '<div class=\"roadofficer-hotspot-popup\">' +
                '<h6>' + escapeHtml(hotspot.name || 'Unnamed hotspot') + '</h6>' +
                '<p><strong>Severity:</strong> ' + escapeHtml(hotspot.severity || 'medium') + '</p>' +
                '<p><strong>Frequency:</strong> ' + escapeHtml(hotspot.frequency || 0) + '</p>' +
                '<p><strong>Rule:</strong> ' + escapeHtml(hotspot.rule || 'Not linked') + '</p>' +
                '<p><strong>Updated:</strong> ' + escapeHtml(hotspot.updated || 'N/A') + '</p>' +
            '</div>'
        );

        markersById[String(hotspot.id)] = marker;
    });

    if (bounds.length > 0) {
        map.fitBounds(bounds, { padding: [32, 32], maxZoom: 15 });
    }

    document.addEventListener('click', function (event) {
        const button = event.target.closest('[data-hotspot-focus]');
        if (!button) {
            return;
        }

        const marker = markersById[button.getAttribute('data-hotspot-focus')];
        if (!marker) {
            return;
        }

        const point = marker.getLatLng();
        map.flyTo(point, Math.max(map.getZoom(), 16), { animate: true, duration: 0.8 });
        marker.openPopup();
        mapEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });

    requestAnimationFrame(function () {
        map.invalidateSize();
    });
});
