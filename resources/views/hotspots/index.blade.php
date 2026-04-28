@extends('layouts.app')

@section('title', 'Road Hotspots - Road Safety Reporting System')

@section('content')
    <style>
        .hotspots-page {
            color: var(--theme-text);
            font-family: var(--font-body);
        }

        .hotspots-page .hotspots-section {
            padding-top: 122px;
            padding-bottom: 4rem;
            background:
                radial-gradient(circle at top left, rgba(255, 255, 255, 0.95), rgba(243, 245, 249, 0.92) 38%, rgba(236, 239, 244, 1) 100%);
        }

        .hotspots-page .section-title,
        .hotspots-page h3,
        .hotspots-page h5 {
            color: var(--theme-text-strong);
            font-family: var(--bs-body-font-family);
            font-weight: 600;
        }

        .hotspots-page .section-title {
            font-size: clamp(1.55rem, 3vw, 2rem);
            margin-bottom: 0.9rem;
        }

        .hotspots-page .section-title::after {
            width: 56px;
            height: 2px;
            margin-top: 0.8rem;
            background: linear-gradient(90deg, var(--theme-navy), var(--theme-gold));
            opacity: 0.6;
        }

        .hotspots-page .section-intro {
            max-width: 680px;
            margin: 0 auto;
            color: var(--theme-text-muted);
            line-height: 1.7;
        }

        .hotspot-map-card,
        .hotspot-list-card {
            border: 1px solid var(--theme-border);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.96);
            box-shadow: var(--theme-shadow-soft);
        }

        .hotspot-map-card {
            margin-top: 2rem;
            overflow: hidden;
        }

        .hotspot-map-header,
        .hotspot-list-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1rem 1.1rem;
            border-bottom: 1px solid var(--theme-border);
        }

        .hotspot-map-header p,
        .hotspot-list-header p {
            margin: 0.25rem 0 0;
            color: var(--theme-text-muted);
            font-size: 0.9rem;
        }

        .hotspot-map {
            width: 100%;
            height: min(68vh, 620px);
            min-height: 420px;
            background: var(--theme-page-bg);
        }

        .hotspot-map-empty {
            min-height: 360px;
            display: grid;
            place-items: center;
            padding: 2rem;
            text-align: center;
            color: var(--theme-text-muted);
        }

        .hotspot-map-empty i {
            display: block;
            margin-bottom: 0.7rem;
            color: var(--theme-gold);
            font-size: 2rem;
        }

        .hotspot-list-card {
            margin-top: 1.2rem;
            overflow: hidden;
        }

        .hotspot-list {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 1rem;
            padding: 1.1rem;
        }

        .hotspot-item {
            border: 1px solid var(--theme-border);
            border-radius: 8px;
            padding: 1rem;
            background: #fff;
        }

        .hotspot-item__top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 0.8rem;
            margin-bottom: 0.7rem;
        }

        .hotspot-item__name {
            margin: 0;
            font-size: 1rem;
        }

        .hotspot-item__meta {
            margin: 0.45rem 0 0;
            color: var(--theme-text-muted);
            font-size: 0.88rem;
            line-height: 1.55;
        }

        .hotspot-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            border-radius: 999px;
            padding: 0.34rem 0.62rem;
            font-size: 0.76rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .hotspot-badge--critical,
        .hotspot-badge--high {
            background: rgba(220, 38, 38, 0.08);
            color: #991b1b;
        }

        .hotspot-badge--medium {
            background: rgba(243, 183, 74, 0.16);
            color: #8a6a28;
        }

        .hotspot-badge--low {
            background: rgba(34, 197, 94, 0.08);
            color: #166534;
        }

        .hotspot-focus-btn {
            margin-top: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            border-radius: 999px;
            border: 1px solid var(--theme-border-strong);
            color: var(--theme-navy);
            background: #fff;
            padding: 0.48rem 0.75rem;
            font-size: 0.84rem;
        }

        .hotspot-focus-btn:hover,
        .hotspot-focus-btn:focus {
            background: var(--theme-navy);
            color: #fff;
        }

        .hotspot-popup h6 {
            margin: 0 0 0.35rem;
            color: var(--theme-text-strong);
            font-weight: 700;
        }

        .hotspot-popup p {
            margin: 0.2rem 0;
            color: var(--theme-text-muted);
        }

        @media (max-width: 991.98px) {
            .hotspot-list {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 767px) {
            .hotspots-page .hotspots-section {
                padding-top: 108px;
            }

            .hotspot-list {
                grid-template-columns: 1fr;
            }

            .hotspot-map {
                min-height: 360px;
            }
        }
    </style>

    <div class="hotspots-page">
        <section class="home-section hotspots-section" id="road-hotspots">
            <div class="container">
                <div class="section-shell">
                    <div class="text-center">
                        <span class="section-eyebrow">
                            <i class="bi bi-geo-alt"></i> Road Safety Map
                        </span>
                        <h2 class="section-title mb-2">Road Hotspots</h2>
                        <p class="section-intro">
                            View mapped road safety hotspots, incident frequency, severity level, and linked rules from the reporting system.
                        </p>
                    </div>

                    <section class="hotspot-map-card">
                        <div class="hotspot-map-header">
                            <div>
                                <h3 class="mb-0">Hotspot map</h3>
                                <p>Markers and circles show hotspot locations and approximate affected radius.</p>
                            </div>
                        </div>

                        @if ($hotspots->isEmpty())
                            <div class="hotspot-map-empty">
                                <div>
                                    <i class="bi bi-map"></i>
                                    <h5>No hotspots recorded yet</h5>
                                    <p class="mb-0">Road safety hotspots will appear here once officers add them.</p>
                                </div>
                            </div>
                        @else
                            <div id="publicHotspotsMap" class="hotspot-map"></div>
                        @endif
                    </section>

                    @if ($hotspots->isNotEmpty())
                        <section class="hotspot-list-card">
                            <div class="hotspot-list-header">
                                <div>
                                    <h3 class="mb-0">Hotspot details</h3>
                                    <p>Use “View on map” to focus a specific hotspot.</p>
                                </div>
                            </div>

                            <div class="hotspot-list">
                                @foreach ($hotspots as $hotspot)
                                    @php
                                        $severity = $hotspot->severity ?: 'medium';
                                    @endphp
                                    <article class="hotspot-item">
                                        <div class="hotspot-item__top">
                                            <h5 class="hotspot-item__name">{{ $hotspot->name ?: 'Unnamed hotspot' }}</h5>
                                            <span class="hotspot-badge hotspot-badge--{{ str_replace('_', '-', $severity) }}">
                                                <i class="bi bi-circle-fill"></i>
                                                <span>{{ str($severity)->replace('_', ' ')->title() }}</span>
                                            </span>
                                        </div>
                                        <p class="hotspot-item__meta">
                                            Frequency: {{ $hotspot->frequency }}<br>
                                            Radius: {{ number_format((float) $hotspot->radius_meters) }}m<br>
                                            Rule: {{ $hotspot->rule?->rule_name ?? 'Not linked' }}
                                        </p>
                                        <button type="button" class="hotspot-focus-btn" data-hotspot-focus="{{ $hotspot->id }}">
                                            <i class="bi bi-crosshair"></i>
                                            <span>View on map</span>
                                        </button>
                                    </article>
                                @endforeach
                            </div>
                        </section>
                    @endif
                </div>
            </div>
        </section>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
@endpush

@section('scripts')
    @if ($hotspots->isNotEmpty())
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
        <script>
            (function () {
                const mapConfig = @json($mapConfig);
                const hotspots = @json($hotspotPayload);
                const mapEl = document.getElementById('publicHotspotsMap');
                if (!mapEl || !window.L || hotspots.length === 0) return;

                const map = L.map(mapEl, {
                    zoomControl: true,
                    scrollWheelZoom: true
                }).setView([
                    Number(mapConfig.defaultCenter.lat),
                    Number(mapConfig.defaultCenter.lng)
                ], Number(mapConfig.defaultZoom) || 12);

                L.tileLayer(mapConfig.tiles.url, {
                    attribution: mapConfig.tiles.attribution,
                    minZoom: Number(mapConfig.minZoom) || 3,
                    maxZoom: Number(mapConfig.maxZoom) || 19
                }).addTo(map);

                const severityColors = {
                    critical: '#991b1b',
                    high: '#b91c1c',
                    medium: '#f3b74a',
                    low: '#166534'
                };

                const bounds = [];
                const layersById = {};

                hotspots.forEach((hotspot) => {
                    const color = severityColors[hotspot.severity] || severityColors.medium;
                    const point = [hotspot.lat, hotspot.lng];
                    bounds.push(point);

                    const marker = L.circleMarker(point, {
                        radius: 8,
                        color,
                        weight: 2,
                        fillColor: color,
                        fillOpacity: 0.92
                    }).addTo(map);

                    L.circle(point, {
                        radius: hotspot.radius || 100,
                        color,
                        weight: 1,
                        fillColor: color,
                        fillOpacity: 0.12
                    }).addTo(map);

                    marker.bindPopup(`
                        <div class="hotspot-popup">
                            <h6>${escapeHtml(hotspot.name)}</h6>
                            <p><strong>Severity:</strong> ${escapeHtml(hotspot.severity)}</p>
                            <p><strong>Frequency:</strong> ${hotspot.frequency}</p>
                            <p><strong>Rule:</strong> ${escapeHtml(hotspot.rule || 'Not linked')}</p>
                            <p><strong>Updated:</strong> ${escapeHtml(hotspot.updated || 'N/A')}</p>
                        </div>
                    `);

                    layersById[hotspot.id] = marker;
                });

                if (bounds.length > 0) {
                    map.fitBounds(bounds, { padding: [32, 32], maxZoom: 15 });
                }

                document.addEventListener('click', function (event) {
                    const button = event.target.closest('[data-hotspot-focus]');
                    if (!button) return;

                    const marker = layersById[button.getAttribute('data-hotspot-focus')];
                    if (!marker) return;

                    const point = marker.getLatLng();
                    map.flyTo(point, Math.max(map.getZoom(), 16), { animate: true, duration: 0.8 });
                    marker.openPopup();
                    mapEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                });

                function escapeHtml(value) {
                    return String(value)
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;')
                        .replace(/'/g, '&#039;');
                }
            })();
        </script>
    @endif
@endsection
