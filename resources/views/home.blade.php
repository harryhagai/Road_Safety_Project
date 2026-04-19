@inject('mapConfigService', 'App\Services\MapConfigService')
@extends('layouts.app')

@section('title', 'RSRS - Road Safety Reporting System')

@section('content')
    @php
        $mapConfig = $mapConfigService->forFrontend();
    @endphp

    <div class="home-map-layout">
        <div class="home-map-layout__container">
            <section class="geo-card geo-card--fill geo-card--map home-map-card">
                <div class="geo-card__header home-map-card__header">
                    <div>
                        <h2 class="geo-card__title">Live Road Network</h2>
                        <p class="geo-card__text mb-0">Explore active road segments, monitoring zones, and reported incidents across the map.</p>
                    </div>
                </div>

                <div class="home-map-canvas-wrap">
                    <!-- OpenStreetMap Canvas -->
                    <x-map.canvas id="mainPublicMap" :config="$mapConfig" height="100%" :show-toolbar="false" mode="viewer" />
                </div>
            </section>
        </div>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <link rel="stylesheet" href="{{ asset('css/rsrsMap.css') }}">

    <style>
        :root {
            --home-header-height: 86px;
            --home-footer-height: 56px;
        }

        .header-wrapper {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            width: 100%;
        }

        .footer-wrapper {
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            width: 100%;
        }

        main.flex-grow-1 {
            padding-top: calc(var(--home-header-height) + 0.85rem);
            padding-bottom: calc(var(--home-footer-height) + 0.85rem);
        }

        .home-map-layout {
            width: 100%;
            display: flex;
            justify-content: center;
            padding: 0 0.75rem;
        }

        .home-map-layout__container {
            width: min(1200px, 100%);
            height: calc(100vh - var(--home-header-height) - var(--home-footer-height) - 1.7rem);
            min-height: 520px;
        }

        .home-map-card {
            height: 100%;
        }

        .home-map-card__header {
            padding: 1rem 1rem 0.35rem;
        }

        .home-map-canvas-wrap {
            flex: 1 1 auto;
            min-height: 0;
            padding: 0.75rem 1rem 1rem;
        }

        .home-map-canvas-wrap #mainPublicMap,
        .home-map-canvas-wrap .geo-map-canvas,
        .home-map-canvas-wrap .leaflet-container {
            width: 100%;
            height: 100%;
            min-height: 0;
            border-radius: 18px;
            overflow: hidden;
            border: 1px solid rgba(31, 49, 79, 0.1);
        }

        @media (max-width: 768px) {
            :root {
                --home-header-height: 84px;
                --home-footer-height: 80px;
            }

            main.flex-grow-1 {
                padding-top: calc(var(--home-header-height) + 0.65rem);
                padding-bottom: calc(var(--home-footer-height) + 0.65rem);
            }

            .home-map-layout {
                padding: 0 0.5rem;
            }

            .home-map-layout__container {
                min-height: 460px;
                height: calc(100vh - var(--home-header-height) - var(--home-footer-height) - 1.3rem);
            }

            .home-map-canvas-wrap {
                padding: 0.65rem 0.75rem 0.8rem;
            }
        }
    </style>
@endpush

@section('scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script src="{{ asset('js/rsrsMapPicker.js') }}"></script>
    <script>
        (function() {
            let mapInterface = null;
            let watchId = null;

            function init() {
                const mapEl = document.getElementById('mainPublicMap');

                // Fast poll for map readiness
                const poll = () => {
                    if (mapEl && mapEl.mapApi) {
                        mapInterface = mapEl.mapApi;
                        // Immediate rendering fix
                        mapInterface.map.invalidateSize();
                        // Start GPS in background
                        initializeTracking();
                    } else {
                        requestAnimationFrame(poll);
                    }
                };
                poll();
            }

            function initializeTracking() {
                if (!navigator.geolocation || !mapInterface) return;

                const startWatching = (highAccuracy) => {
                    if (watchId !== null) navigator.geolocation.clearWatch(watchId);

                    watchId = navigator.geolocation.watchPosition(
                        (pos) => {
                            const { latitude, longitude } = pos.coords;
                            mapInterface.selectPoint(latitude, longitude);
                            // Smoothly transition to user location
                            mapInterface.map.flyTo([latitude, longitude], 16, {
                                animate: true,
                                duration: 1.5
                            });
                        },
                        (err) => {
                            console.warn(`GPS Error (${err.code}): ${err.message}`);
                            if (highAccuracy && (err.code === 3 || err.code === 2)) {
                                startWatching(false);
                            }
                        },
                        { enableHighAccuracy: highAccuracy, timeout: 15000, maximumAge: 1000 }
                    );
                };

                startWatching(true);
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', init);
            } else {
                init();
            }
        })();
    </script>
@endsection
