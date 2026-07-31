(function () {
    const payload = window.rsrsViolationAnalysis || {};
    if (!window.Chart || !payload) {
        return;
    }

    function labels(items) {
        return Array.isArray(items) ? items.map((item) => item.label) : [];
    }

    function values(items) {
        return Array.isArray(items) ? items.map((item) => Number(item.value || 0)) : [];
    }

    function chart(canvasId, config) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) {
            return null;
        }

        return new Chart(canvas, config);
    }

    chart('violationTrendChart', {
        type: 'line',
        data: {
            labels: Array.isArray(payload.trend?.labels) ? payload.trend.labels : [],
            datasets: [
                {
                    label: 'Parking',
                    data: Array.isArray(payload.trend?.parking_values) ? payload.trend.parking_values.map((value) => Number(value || 0)) : [],
                    borderColor: '#0f766e',
                    backgroundColor: 'rgba(15, 118, 110, 0.1)',
                    tension: 0.32,
                    fill: false,
                    pointRadius: 3,
                    pointHoverRadius: 5,
                },
                {
                    label: 'Overspeeding',
                    data: Array.isArray(payload.trend?.overspeeding_values) ? payload.trend.overspeeding_values.map((value) => Number(value || 0)) : [],
                    borderColor: '#b91c1c',
                    backgroundColor: 'rgba(185, 28, 28, 0.1)',
                    tension: 0.32,
                    fill: false,
                    pointRadius: 3,
                    pointHoverRadius: 5,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { beginAtZero: true, ticks: { precision: 0 } },
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'bottom',
                    labels: { boxWidth: 10, usePointStyle: true },
                },
            },
        },
    });

    chart('violationSegmentsChart', {
        type: 'bar',
        data: {
            labels: labels(payload.segments),
            datasets: [{
                label: 'Reports',
                data: values(payload.segments),
                backgroundColor: '#174ea6',
                borderRadius: 6,
                maxBarThickness: 34,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            scales: {
                x: { beginAtZero: true, ticks: { precision: 0 } },
            },
            plugins: {
                legend: { display: false },
            },
        },
    });

})();
