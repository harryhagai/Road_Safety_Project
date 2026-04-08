document.addEventListener('DOMContentLoaded', function () {
    const dashboardPage = document.querySelector('.academic-dashboard-page');

    if (!dashboardPage || typeof Chart === 'undefined') {
        return;
    }

    const endpoint = dashboardPage.dataset.endpoint;
    const alertBox = document.getElementById('academicDashboardAlert');
    const numberFormatter = new Intl.NumberFormat();
    const chartInstances = {};

    Chart.defaults.font.family = "'Segoe UI', sans-serif";
    Chart.defaults.color = '#5f7698';

    function showAlert(message) {
        if (!alertBox) {
            return;
        }

        alertBox.textContent = message;
        alertBox.classList.add('is-visible');
    }

    function setStatValues(stats) {
        document.querySelectorAll('[data-stat]').forEach(function (element) {
            const key = element.dataset.stat;
            const value = Number(stats?.[key] ?? 0);
            element.textContent = numberFormatter.format(value);
        });
    }

    function toggleEmptyState(emptyStateId, hasData) {
        const emptyState = document.getElementById(emptyStateId);

        if (!emptyState) {
            return;
        }

        emptyState.classList.toggle('is-visible', !hasData);
    }

    function createOrReplaceChart(canvasId, emptyStateId, config) {
        const canvas = document.getElementById(canvasId);
        const labels = config?.data?.labels ?? [];
        const datasets = config?.data?.datasets ?? [];
        const hasData = labels.length > 0 && datasets.length > 0 && datasets.some(function (dataset) {
            return Array.isArray(dataset.data) && dataset.data.length > 0;
        });

        toggleEmptyState(emptyStateId, hasData);

        if (!canvas || !hasData) {
            return;
        }

        if (chartInstances[canvasId]) {
            chartInstances[canvasId].destroy();
        }

        chartInstances[canvasId] = new Chart(canvas, config);
    }

    function buildBarOptions() {
        return {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                x: {
                    grid: {
                        display: false
                    }
                },
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Average score'
                    },
                    grid: {
                        color: 'rgba(148, 163, 184, 0.18)'
                    }
                }
            }
        };
    }

    function buildGroupedBarOptions() {
        return {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        boxWidth: 8
                    }
                }
            },
            scales: {
                x: {
                    stacked: false,
                    grid: {
                        display: false
                    }
                },
                y: {
                    beginAtZero: true,
                    suggestedMax: 100,
                    ticks: {
                        callback: function (value) {
                            return value + '%';
                        }
                    },
                    title: {
                        display: true,
                        text: 'Pass rate'
                    },
                    grid: {
                        color: 'rgba(148, 163, 184, 0.18)'
                    }
                }
            }
        };
    }

    fetch(endpoint, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
        .then(function (response) {
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.status);
            }

            return response.json();
        })
        .then(function (data) {
            setStatValues(data.stats || {});

            createOrReplaceChart('olevelPerformanceTrendChart', 'olevelPerformanceTrendEmpty', {
                type: 'bar',
                data: data.olevelPerformanceTrend || { labels: [], datasets: [] },
                options: buildBarOptions()
            });

            createOrReplaceChart('alevelPerformanceTrendChart', 'alevelPerformanceTrendEmpty', {
                type: 'bar',
                data: data.alevelPerformanceTrend || { labels: [], datasets: [] },
                options: buildBarOptions()
            });

            createOrReplaceChart('olevelSubjectSnapshotChart', 'olevelSubjectSnapshotEmpty', {
                type: 'bar',
                data: data.olevelSubjectSnapshot || { labels: [], datasets: [] },
                options: buildBarOptions()
            });

            createOrReplaceChart('alevelSubjectSnapshotChart', 'alevelSubjectSnapshotEmpty', {
                type: 'bar',
                data: data.alevelSubjectSnapshot || { labels: [], datasets: [] },
                options: buildBarOptions()
            });

            createOrReplaceChart('olevelClassPassRateChart', 'olevelClassPassRateEmpty', {
                type: 'bar',
                data: data.olevelClassPassRate || { labels: [], datasets: [] },
                options: buildGroupedBarOptions()
            });

            createOrReplaceChart('alevelClassPassRateChart', 'alevelClassPassRateEmpty', {
                type: 'bar',
                data: data.alevelClassPassRate || { labels: [], datasets: [] },
                options: buildGroupedBarOptions()
            });
        })
        .catch(function (error) {
            console.error('Failed to load dashboard data', error);
            document.querySelectorAll('[data-stat]').forEach(function (element) {
                element.textContent = 'N/A';
            });

            showAlert('Dashboard data failed to load. Please refresh and try again.');
            toggleEmptyState('olevelPerformanceTrendEmpty', false);
            toggleEmptyState('alevelPerformanceTrendEmpty', false);
            toggleEmptyState('olevelSubjectSnapshotEmpty', false);
            toggleEmptyState('alevelSubjectSnapshotEmpty', false);
            toggleEmptyState('olevelClassPassRateEmpty', false);
            toggleEmptyState('alevelClassPassRateEmpty', false);
        });
});
