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
    const sidebar = document.querySelector('[data-hotspot-sidebar]');
    const sidebarEmpty = document.querySelector('[data-hotspot-empty]');
    const sidebarContent = document.querySelector('[data-hotspot-content]');
    const sidebarCategory = document.querySelector('[data-hotspot-category]');
    const sidebarTitle = document.querySelector('[data-hotspot-title]');
    const sidebarLocation = document.querySelector('[data-hotspot-location]');
    const sidebarReportCount = document.querySelector('[data-hotspot-report-count]');
    const sidebarLastReported = document.querySelector('[data-hotspot-last-reported]');
    const sidebarTypes = document.querySelector('[data-hotspot-types]');
    const sidebarReports = document.querySelector('[data-hotspot-reports]');
    let selectedRecord = null;

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

    function setText(element, value) {
        if (!element) {
            return;
        }

        element.textContent = String(value ?? '');
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

    function detailRow(label, value) {
        return '<div><span>' + escapeHtml(label) + '</span><strong>' + escapeHtml(value || 'N/A') + '</strong></div>';
    }

    function formatCoordinates(report) {
        const lat = Number(report?.lat);
        const lng = Number(report?.lng);
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
            return 'N/A';
        }

        return lat.toFixed(6) + ', ' + lng.toFixed(6);
    }

    function ruleListHtml(rules) {
        if (!Array.isArray(rules) || rules.length === 0) {
            return '<p class="officer-hotspots-report-card__notes"><strong>Rules:</strong> No matched rules.</p>';
        }

        const items = rules.map(function (rule) {
            return '' +
                '<li>' +
                    '<strong>' + escapeHtml(rule?.name || 'Unlinked rule') + '</strong>' +
                    '<span>' + escapeHtml(rule?.type || 'Unknown') + ' | ' + escapeHtml(rule?.value || 'N/A') + '</span>' +
                    '<span>Segment: ' + escapeHtml(rule?.segment || 'No segment linked') + '</span>' +
                    '<span>Source: ' + escapeHtml(rule?.source || 'N/A') + ' | Confidence: ' + escapeHtml(rule?.confidence || 'N/A') + '</span>' +
                    '<span>Verified: ' + escapeHtml(rule?.verifiedAt || 'Not verified') + '</span>' +
                    '<p>' + escapeHtml(rule?.description || 'No rule description.') + '</p>' +
                '</li>';
        }).join('');

        return '<div class="officer-hotspots-rule-list"><h6>Matched Rules</h6><ul>' + items + '</ul></div>';
    }

    function reportCardHtml(report) {
        const url = report?.url ? String(report.url) : '#';

        return '' +
            '<article class="officer-hotspots-report-card">' +
                '<div class="officer-hotspots-report-card__head">' +
                    '<div>' +
                        '<h5>' + escapeHtml(report?.reference || 'Report') + '</h5>' +
                        '<span>' + escapeHtml(report?.type || 'Unassigned') + '</span>' +
                    '</div>' +
                    '<a class="officer-hotspots-report-card__link" href="' + escapeHtml(url) + '" title="Open report" aria-label="Open report">' +
                        '<i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>' +
                    '</a>' +
                '</div>' +
                '<div class="officer-hotspots-report-card__body">' +
                    '<div class="officer-hotspots-detail-grid">' +
                        detailRow('Report ID', report?.id) +
                        detailRow('Status', report?.status) +
                        detailRow('Priority', report?.priority) +
                        detailRow('Reported', report?.reportedAt) +
                        detailRow('Created', report?.createdAt) +
                        detailRow('Reviewed', report?.reviewedAt) +
                        detailRow('Officer', report?.officer) +
                        detailRow('Location', report?.location) +
                        detailRow('Coordinates', formatCoordinates(report)) +
                    '</div>' +
                    '<p class="officer-hotspots-report-card__description">' + escapeHtml(report?.description || 'No description provided.') + '</p>' +
                    '<p class="officer-hotspots-report-card__notes"><strong>Notes:</strong> ' + escapeHtml(report?.officerNotes || 'No officer notes yet.') + '</p>' +
                    ruleListHtml(report?.rules) +
                '</div>' +
            '</article>';
    }

    function resetSelectedRecord() {
        if (!selectedRecord) {
            return;
        }

        selectedRecord.marker.setStyle({
            weight: 2,
            color: selectedRecord.style.stroke,
            fillColor: selectedRecord.style.fill,
        });
        selectedRecord.marker.setRadius(selectedRecord.markerRadius);
        selectedRecord.circle.setStyle({
            weight: 1,
            color: selectedRecord.style.stroke,
            fillColor: selectedRecord.style.fill,
            fillOpacity: 0.1,
        });
    }

    function selectRecord(record) {
        resetSelectedRecord();
        selectedRecord = record;

        record.marker.setStyle({
            weight: 3,
            color: '#111827',
            fillColor: record.style.fill,
        });
        record.marker.setRadius(record.markerRadius + 2);
        record.circle.setStyle({
            weight: 2,
            color: '#111827',
            fillColor: record.style.fill,
            fillOpacity: 0.16,
        });
    }

    function renderSidebar(reportPoint) {
        if (!sidebar || !sidebarEmpty || !sidebarContent) {
            return;
        }

        const reports = Array.isArray(reportPoint.reports) ? reportPoint.reports : [];
        const types = Array.isArray(reportPoint.types) ? reportPoint.types.filter(Boolean) : [];

        sidebarEmpty.classList.add('d-none');
        sidebarContent.classList.remove('d-none');

        setText(sidebarCategory, reportPoint.label || 'Report point');
        setText(sidebarTitle, violationLabel(reportPoint));
        setText(sidebarLocation, reportPoint.location || 'Unknown location');
        setText(sidebarReportCount, reportPoint.count || reports.length || 0);
        setText(sidebarLastReported, reportPoint.lastReportedAt || 'N/A');

        if (sidebarTypes) {
            sidebarTypes.innerHTML = types.length > 0
                ? types.map(function (type) {
                    return '<span class="officer-hotspots-sidebar__chip">' + escapeHtml(type) + '</span>';
                }).join('')
                : '<span class="officer-hotspots-sidebar__chip">Unassigned</span>';
        }

        if (sidebarReports) {
            sidebarReports.innerHTML = reports.length > 0
                ? reports.map(reportCardHtml).join('')
                : '<p class="officer-hotspots-report-card__notes">No report details available.</p>';
        }

        sidebarContent.scrollTop = 0;
    }

    points.forEach(function (reportPoint) {
        const point = [Number(reportPoint.lat), Number(reportPoint.lng)];
        if (!Number.isFinite(point[0]) || !Number.isFinite(point[1])) {
            return;
        }

        const style = toneStyles[reportPoint.tone] || toneStyles.primary;
        const count = Number(reportPoint.count || 1);
        const markerRadius = Math.min(8, 4 + (count * 0.45));
        bounds.push(point);

        const marker = L.circleMarker(point, {
            radius: markerRadius,
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

        const record = {
            marker,
            circle,
            markerRadius,
            reportPoint,
            style,
        };

        marker.on('click', function () {
            selectRecord(record);
            renderSidebar(reportPoint);
        });
        circle.on('click', function () {
            selectRecord(record);
            renderSidebar(reportPoint);
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
