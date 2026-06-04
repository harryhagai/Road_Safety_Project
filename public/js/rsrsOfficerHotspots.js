// Full officer hotspot map for grouped report locations.
document.addEventListener('DOMContentLoaded', function () {
    const mapEl = document.getElementById('officerHotspotsFullMap');
    const payloadEl = document.getElementById('officerHotspotsPayload');
    if (!mapEl || !payloadEl || !window.L) {
        return;
    }

    let payload = {};
    try {
        payload = JSON.parse(payloadEl.textContent || '{}');
    } catch (error) {
        return;
    }

    const mapConfig = payload.mapConfig || {};
    const points = Array.isArray(payload.points) ? payload.points : [];

    const map = L.map(mapEl, {
        zoomControl: false,
        scrollWheelZoom: true,
        rotate: true,
        rotateControl: false,
        bearing: 0,
    }).setView(
        [
            Number(mapConfig?.defaultCenter?.lat || -6.8),
            Number(mapConfig?.defaultCenter?.lng || 39.28),
        ],
        Number(mapConfig?.defaultZoom || 12)
    );

    L.control.zoom({ position: 'bottomright' }).addTo(map);

    function normalizeBearing(value) {
        const bearing = Number(value);
        if (!Number.isFinite(bearing)) {
            return 0;
        }

        return ((bearing % 360) + 360) % 360;
    }

    function getMapBearing() {
        if (typeof map.getBearing === 'function') {
            return normalizeBearing(map.getBearing());
        }

        return normalizeBearing(map._bearing || 0);
    }

    function setMapBearing(value) {
        if (typeof map.setBearing !== 'function') {
            return;
        }

        map.setBearing(normalizeBearing(value));
    }

    function addRotationControl() {
        if (typeof map.setBearing !== 'function') {
            return;
        }

        const RotationControl = L.Control.extend({
            options: { position: 'bottomright' },
            onAdd: function () {
                const container = L.DomUtil.create('div', 'leaflet-bar officer-hotspots-rotate-control');
                const rotateLeft = L.DomUtil.create('button', 'officer-hotspots-rotate-control__btn', container);
                const reset = L.DomUtil.create('button', 'officer-hotspots-rotate-control__btn officer-hotspots-rotate-control__btn--bearing', container);
                const rotateRight = L.DomUtil.create('button', 'officer-hotspots-rotate-control__btn', container);

                rotateLeft.type = 'button';
                rotateLeft.title = 'Rotate map left';
                rotateLeft.setAttribute('aria-label', 'Rotate map left');
                rotateLeft.innerHTML = '<i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>';

                reset.type = 'button';
                reset.title = 'Reset map north';
                reset.setAttribute('aria-label', 'Reset map north');

                rotateRight.type = 'button';
                rotateRight.title = 'Rotate map right';
                rotateRight.setAttribute('aria-label', 'Rotate map right');
                rotateRight.innerHTML = '<i class="bi bi-arrow-clockwise" aria-hidden="true"></i>';

                function updateBearingLabel() {
                    reset.textContent = Math.round(getMapBearing()) + ' deg';
                }

                L.DomEvent.disableClickPropagation(container);
                L.DomEvent.disableScrollPropagation(container);

                L.DomEvent.on(rotateLeft, 'click', function () {
                    setMapBearing(getMapBearing() - 15);
                    updateBearingLabel();
                });
                L.DomEvent.on(reset, 'click', function () {
                    setMapBearing(0);
                    updateBearingLabel();
                });
                L.DomEvent.on(rotateRight, 'click', function () {
                    setMapBearing(getMapBearing() + 15);
                    updateBearingLabel();
                });

                map.on('rotate', updateBearingLabel);
                updateBearingLabel();

                return container;
            },
        });

        map.addControl(new RotationControl());
    }

    addRotationControl();

    L.tileLayer(mapConfig?.tiles?.url || 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: mapConfig?.tiles?.attribution || '&copy; OpenStreetMap contributors',
        minZoom: Number(mapConfig?.minZoom || 3),
        maxZoom: Number(mapConfig?.maxZoom || 19),
    }).addTo(map);

    const toneStyles = {
        danger: {
            stroke: '#b02a37',
            fill: '#dc3545',
            radius: 38,
        },
        warning: {
            stroke: '#b45309',
            fill: '#ffc107',
            radius: 30,
        },
        primary: {
            stroke: '#174ea6',
            fill: '#2563eb',
            radius: 24,
        },
    };

    const bounds = [];

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function reportListHtml(reports) {
        if (!Array.isArray(reports) || reports.length === 0) {
            return '';
        }

        const items = reports.map(function (report) {
            const reference = escapeHtml(report.reference || 'Report');
            const type = escapeHtml(report.type || 'Unassigned');
            const reportedAt = escapeHtml(report.reportedAt || 'N/A');

            return '<li><strong>' + reference + '</strong> - ' + type + ' <span>(' + reportedAt + ')</span></li>';
        }).join('');

        return '<ul>' + items + '</ul>';
    }

    function violationLabel(reportPoint) {
        const types = Array.isArray(reportPoint.types) ? reportPoint.types.filter(Boolean) : [];
        if (types.length === 0) {
            return 'Violation report';
        }

        if (types.length === 1) {
            return types[0];
        }

        return types[0] + ' +' + String(types.length - 1) + ' more';
    }

    points.forEach(function (reportPoint) {
        const point = [Number(reportPoint.lat), Number(reportPoint.lng)];
        if (!Number.isFinite(point[0]) || !Number.isFinite(point[1])) {
            return;
        }

        const style = toneStyles[reportPoint.tone] || toneStyles.primary;
        const count = Number(reportPoint.count || 1);
        bounds.push(point);

        const marker = L.circleMarker(point, {
            radius: Math.min(8, 4 + (count * 0.45)),
            color: style.stroke,
            weight: 2,
            fillColor: style.fill,
            fillOpacity: reportPoint.tone === 'warning' ? 0.9 : 0.92,
        }).addTo(map);

        const circle = L.circle(point, {
            radius: Number(style.radius || 110),
            color: style.stroke,
            weight: 1,
            fillColor: style.fill,
            fillOpacity: 0.1,
        }).addTo(map);

        const popupHtml =
            '<div class="roadofficer-hotspot-popup">' +
                '<h6>' + escapeHtml(violationLabel(reportPoint)) + '</h6>' +
                '<p><strong>Category:</strong> ' + escapeHtml(reportPoint.label || 'Report point') + '</p>' +
                '<p><strong>Reports:</strong> ' + escapeHtml(count) + '</p>' +
                '<p><strong>Types:</strong> ' + escapeHtml((reportPoint.types || []).join(', ') || 'N/A') + '</p>' +
                '<p><strong>Location:</strong> ' + escapeHtml(reportPoint.location || 'Unknown location') + '</p>' +
                '<p><strong>Last Reported:</strong> ' + escapeHtml(reportPoint.lastReportedAt || 'N/A') + '</p>' +
                reportListHtml(reportPoint.reports) +
            '</div>';

        marker.bindPopup(popupHtml);
        circle.bindPopup(popupHtml);
        marker.on('mouseover', function () {
            marker.openPopup();
        });
        circle.on('mouseover', function () {
            marker.openPopup();
        });
    });

    if (bounds.length > 0) {
        map.fitBounds(bounds, { padding: [32, 32], maxZoom: 15 });
    }

    requestAnimationFrame(function () {
        map.invalidateSize();
    });
    window.addEventListener('resize', function () {
        map.invalidateSize();
    });
});
