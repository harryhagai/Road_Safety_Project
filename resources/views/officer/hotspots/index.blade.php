@extends('layouts.officerDashboardLayout')

@section('title', 'Hotspots')

@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <style>
        .officer-hotspots-map-shell {
            height: calc(100vh - 220px);
            min-height: 560px;
            border-radius: 14px;
            overflow: hidden;
            border: 1px solid rgba(26, 35, 55, 0.12);
            box-shadow: 0 12px 28px rgba(22, 29, 44, 0.14);
            background: #e9eef5;
        }
        .officer-hotspots-map {
            width: 100%;
            height: 100%;
        }
        .officer-hotspots-stats {
            display: flex;
            align-items: stretch;
            gap: 0.5rem;
            flex-wrap: nowrap;
            justify-content: flex-end;
            white-space: nowrap;
        }
        .officer-hotspots-stats__item {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            justify-content: space-between;
            gap: 0.1rem;
            min-width: 110px;
            padding: 0.45rem 0.6rem;
            border-radius: 10px;
            border: 1px solid rgba(25, 40, 62, 0.12);
            background: #fff;
            font-size: 0.8rem;
            color: #324761;
        }
        .officer-hotspots-stats__item strong {
            font-size: 0.84rem;
            color: #1f3247;
        }
        @media (max-width: 768px) {
            .officer-hotspots-map-shell {
                height: calc(100vh - 190px);
                min-height: 460px;
            }
            .officer-hotspots-stats {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
        }
    </style>
@endpush

@section('page_header_actions')
    @php
        $total = $violations->count();
        $updated = optional($violations->first()?->reported_at ?? $violations->first()?->created_at)?->format('H:i:s') ?: '-';
    @endphp
    <div class="officer-hotspots-stats">
        <div class="officer-hotspots-stats__item"><span>Total Speed Violations</span><strong>{{ $total }}</strong></div>
        <div class="officer-hotspots-stats__item"><span>Average / Point</span><strong>{{ number_format($averageViolations, 2) }}</strong></div>
        <div class="officer-hotspots-stats__item"><span>Critical Points</span><strong>{{ $criticalPoints }}</strong></div>
        <div class="officer-hotspots-stats__item"><span>Warning Points</span><strong>{{ $warningPoints }}</strong></div>
        <div class="officer-hotspots-stats__item"><span>Last Report</span><strong>{{ $updated }}</strong></div>
    </div>
@endsection

@section('content')
    <div class="container-fluid px-3 px-lg-4 pb-4">
        <div class="officer-hotspots-map-shell">
            <div id="officerHotspotsFullMap" class="officer-hotspots-map"></div>
        </div>
    </div>
@endsection

@section('scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
        (() => {
            const mapConfig = @json($mapConfig);
            const violations = @json($violationPayload);
            const mapEl = document.getElementById('officerHotspotsFullMap');
            if (!mapEl || !window.L) return;

            const map = L.map(mapEl, {
                zoomControl: false,
                scrollWheelZoom: true,
            }).setView(
                [Number(mapConfig?.defaultCenter?.lat || -6.8), Number(mapConfig?.defaultCenter?.lng || 39.28)],
                Number(mapConfig?.defaultZoom || 12)
            );

            L.control.zoom({ position: 'bottomright' }).addTo(map);

            L.tileLayer(mapConfig?.tiles?.url || 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: mapConfig?.tiles?.attribution || '&copy; OpenStreetMap contributors',
                minZoom: Number(mapConfig?.minZoom || 3),
                maxZoom: Number(mapConfig?.maxZoom || 19),
            }).addTo(map);

            const levelColors = {
                critical: '#b91c1c',
                warning: '#d97706',
            };
            const levelRadius = {
                critical: 180,
                warning: 120,
            };

            const bounds = [];

            const escapeHtml = (value) => String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');

            violations.forEach((violation) => {
                const color = levelColors[violation.level] || levelColors.warning;
                const point = [Number(violation.lat), Number(violation.lng)];
                if (!Number.isFinite(point[0]) || !Number.isFinite(point[1])) return;
                bounds.push(point);

                const marker = L.circleMarker(point, {
                    radius: Math.min(16, 7 + Number(violation.count || 1)),
                    color: color,
                    weight: 2,
                    fillColor: color,
                    fillOpacity: 0.92,
                }).addTo(map);

                L.circle(point, {
                    radius: Number(levelRadius[violation.level] || 110),
                    color: color,
                    weight: 1,
                    fillColor: color,
                    fillOpacity: 0.12,
                }).addTo(map);

                marker.bindPopup(
                    `<div class="roadofficer-hotspot-popup">
                        <h6>${escapeHtml(violation.level === 'critical' ? 'Critical Speed Point' : 'Warning Speed Point')}</h6>
                        <p><strong>Speed Violations:</strong> ${escapeHtml(violation.count || 0)}</p>
                        <p><strong>Class:</strong> ${escapeHtml(violation.level || 'warning')}</p>
                        <p><strong>Types:</strong> ${escapeHtml((violation.types || []).join(', ') || 'N/A')}</p>
                        <p><strong>Location:</strong> ${escapeHtml(violation.location || 'Unknown location')}</p>
                        <p><strong>Last Reported:</strong> ${escapeHtml(violation.lastReportedAt || 'N/A')}</p>
                    </div>`
                );
            });

            if (bounds.length > 0) {
                map.fitBounds(bounds, { padding: [32, 32], maxZoom: 15 });
            }

            requestAnimationFrame(() => map.invalidateSize());
            window.addEventListener('resize', () => map.invalidateSize());
        })();
    </script>
@endsection
