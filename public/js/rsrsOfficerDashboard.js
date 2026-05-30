// Frontend helper for rsrsOfficerDashboard interactions in the RSRS interface.

// Boot dashboard charts + map after DOM is ready.
document.addEventListener('DOMContentLoaded', function () {
    const speedAnalytics = window.rsrsOfficerSpeedAnalytics;
    if (speedAnalytics && window.Chart) {
        const trendCanvas = document.getElementById('officerSpeedTrendLineChart');
        if (trendCanvas) {
            // Normalize backend chart arrays before rendering.
            const labels = Array.isArray(speedAnalytics.line_labels) ? speedAnalytics.line_labels : [];
            const speedValues = Array.isArray(speedAnalytics.line_speed_values) ? speedAnalytics.line_speed_values : [];
            const limitValues = Array.isArray(speedAnalytics.line_limit_values) ? speedAnalytics.line_limit_values : [];
            const violationTotals = Array.isArray(speedAnalytics.line_violation_totals) ? speedAnalytics.line_violation_totals : [];
            const pointMeta = Array.isArray(speedAnalytics.line_point_meta) ? speedAnalytics.line_point_meta : [];

            // Single line chart for violation trend + speed behavior.
            new window.Chart(trendCanvas, {
                type: 'line',
                data: {
                    datasets: [{
                        label: 'Total Violations',
                        data: violationTotals,
                        borderColor: '#dc2626',
                        borderWidth: 2,
                        tension: 0.25,
                        pointRadius: 0,
                        fill: false,
                        yAxisID: 'y',
                    }, {
                        label: 'Violation Speed (km/h)',
                        data: speedValues,
                        borderColor: '#2563eb',
                        // Match the reference card with a soft blue gradient under the speed line.
                        backgroundColor: function (context) {
                            const chart = context.chart;
                            const chartArea = chart.chartArea;
                            if (!chartArea) return 'rgba(37, 99, 235, 0.14)';
                            const gradient = chart.ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
                            gradient.addColorStop(0, 'rgba(37, 99, 235, 0.24)');
                            gradient.addColorStop(1, 'rgba(37, 99, 235, 0.00)');
                            return gradient;
                        },
                        borderWidth: 2,
                        tension: 0.25,
                        pointRadius: 0,
                        fill: true,
                        clip: 16,
                        yAxisID: 'y1',
                    }, {
                        label: 'Speed Limit (km/h)',
                        data: limitValues,
                        borderColor: '#16a34a',
                        borderDash: [6, 4],
                        borderWidth: 2,
                        tension: 0.15,
                        pointRadius: 0,
                        fill: false,
                        yAxisID: 'y1',
                    }],
                    labels: labels,
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' },
                        tooltip: {
                            callbacks: {
                                // Show exact source report + timestamp for the hovered index.
                                title: function (items) {
                                    const index = items?.[0]?.dataIndex ?? -1;
                                    const meta = pointMeta[index] || {};
                                    return `${meta.reference || 'Unknown report'} - ${meta.reported_at || 'N/A'}`;
                                },
                                // Keep location context visible when reading sudden spikes/dips.
                                afterTitle: function (items) {
                                    const index = items?.[0]?.dataIndex ?? -1;
                                    const meta = pointMeta[index] || {};
                                    return `Location: ${meta.location || 'Unknown segment'}`;
                                },
                            },
                        },
                    },
                    scales: {
                        x: {
                            title: { display: true, text: 'Violation Events' },
                            grid: { color: 'rgba(23, 78, 166, 0.12)' },
                        },
                        y: {
                            beginAtZero: true,
                            title: { display: true, text: 'Violations' },
                            grid: { color: 'rgba(23, 78, 166, 0.12)' },
                        },
                        y1: {
                            position: 'right',
                            beginAtZero: true,
                            title: { display: true, text: 'Speed (km/h)' },
                            grid: { drawOnChartArea: false },
                        },
                    },
                },
                plugins: [{
                    // Apply subtle drop-shadow only to the blue speed line.
                    id: 'speedLineDropShadow',
                    beforeDatasetDraw(chart, args) {
                        const dataset = chart.data.datasets?.[args.index];
                        if (!dataset || dataset.label !== 'Violation Speed (km/h)') return;
                        const ctx = chart.ctx;
                        ctx.save();
                        ctx.shadowColor = 'rgba(37, 99, 235, 0.28)';
                        ctx.shadowBlur = 10;
                        ctx.shadowOffsetX = 0;
                        ctx.shadowOffsetY = 4;
                    },
                    afterDatasetDraw(chart, args) {
                        const dataset = chart.data.datasets?.[args.index];
                        if (!dataset || dataset.label !== 'Violation Speed (km/h)') return;
                        chart.ctx.restore();
                    },
                }],
            });
        }
    }

    // Skip map boot when map payload or leaflet is missing.
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

    // Escape popup text so map content stays safe and predictable.
    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    hotspots.forEach(function (hotspot) {
        // Render one hotspot marker + radius overlay.
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
            '<div class="roadofficer-hotspot-popup">' +
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
        // Fit map view to all hotspot points.
        map.fitBounds(bounds, { padding: [32, 32], maxZoom: 15 });
    }

    // Support "Focus on map" buttons from attention cards.
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

    // Recompute map viewport once panel layout settles.
    requestAnimationFrame(function () {
        map.invalidateSize();
    });
});
