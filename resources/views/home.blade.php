@inject('mapConfigService', 'App\Services\MapConfigService')
@extends('layouts.app')

@section('title', 'RSRS - Road Safety Reporting System')

@section('content')
    @php
        $mapConfig = $mapConfigService->forFrontend();
    @endphp

    <div class="container-fluid container-xl geo-workspace px-2 px-md-3 py-2 py-md-3 home-geo-workspace">
        <div class="row g-2 g-md-3 geo-workspace__grid">
            <div class="col-12">
                <section class="geo-card geo-card--fill geo-card--map home-geo-card">
                    <div class="geo-card__header">
                        <div>
                            <h2 class="geo-card__title">Live Road Network</h2>
                            <p class="geo-card__text mb-0">Explore active road segments, monitoring zones, and reported incidents across the map.</p>
                        </div>
                    </div>

                    <x-map.canvas id="mainPublicMap" :config="$mapConfig" height="100%" :show-toolbar="false" mode="viewer" />
                </section>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <link rel="stylesheet" href="{{ asset('css/rsrsMap.css') }}">

    <style>
        :root {
            --home-header-height: 84px;
            --home-footer-height: 80px;
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
            padding-top: calc(var(--home-header-height) + 0.6rem);
            padding-bottom: calc(var(--home-footer-height) + 0.6rem);
        }

        .home-geo-workspace {
            min-height: calc(100vh - var(--home-header-height) - var(--home-footer-height) - 1.2rem);
        }

        .home-geo-card {
            display: flex;
            flex-direction: column;
            margin: 0 auto;
            width: 100%;
            height: calc(100vh - var(--home-header-height) - var(--home-footer-height) - 1.2rem);
            min-height: 460px;
        }

        .home-geo-card .geo-map-shell {
            display: flex;
            flex-direction: column;
            flex: 1 1 auto;
            min-height: 0;
            height: 100%;
            padding: 0.75rem 1rem 1rem;
        }

        .home-geo-card #mainPublicMap,
        .home-geo-card .geo-map-canvas {
            height: 100% !important;
            min-height: 320px;
            border-radius: 18px;
        }

        .home-location-control__button {
            width: 40px;
            height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 0;
            background: #ffffff;
            color: #1f314f;
            font-size: 1rem;
            cursor: pointer;
        }

        .home-location-control__button:hover {
            background: #f3f7ff;
            color: #0d4eb3;
        }

        .home-location-control__button.is-locating {
            color: #0d6efd;
            animation: homeLocPulse 1s ease-in-out infinite;
        }

        @keyframes homeLocPulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.08);
            }
        }

        @media (min-width: 768px) {
            :root {
                --home-header-height: 86px;
                --home-footer-height: 56px;
            }

            main.flex-grow-1 {
                padding-top: calc(var(--home-header-height) + 0.8rem);
                padding-bottom: calc(var(--home-footer-height) + 0.8rem);
            }

            .home-geo-workspace,
            .home-geo-card {
                min-height: 520px;
                height: calc(100vh - var(--home-header-height) - var(--home-footer-height) - 1.6rem);
            }

            .home-geo-card .geo-map-shell {
                padding: 0.75rem 1rem 1rem;
            }

            .home-geo-card #mainPublicMap,
            .home-geo-card .geo-map-canvas {
                min-height: 420px;
            }
        }

        @media (min-width: 992px) {
            .home-geo-card {
                max-width: 1200px;
            }
        }
    </style>
@endpush

@section('scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script src="{{ asset('js/rsrsMapPicker.js') }}?v={{ filemtime(public_path('js/rsrsMapPicker.js')) }}"></script>
    <script>
        (function () {
            let mapInterface = null;
            let watchId = null;
            let hasCentered = false;
            let userHasAdjustedView = false;
            let lastTrackedPoint = null;
            let lastTrackTimestamp = 0;
            let locationButton = null;

            function setLocatingState(isLocating) {
                if (!locationButton) return;

                locationButton.disabled = isLocating;
                locationButton.classList.toggle('is-locating', isLocating);
                locationButton.title = isLocating ? 'Finding your current position...' : 'Use my current location';
            }

            function createLocationControl() {
                if (!mapInterface?.map || locationButton) return;

                const LocationControl = L.Control.extend({
                    options: { position: 'topright' },
                    onAdd: function () {
                        const container = L.DomUtil.create('div', 'leaflet-bar home-location-control');
                        const button = L.DomUtil.create('button', 'home-location-control__button', container);
                        button.type = 'button';
                        button.title = 'Use my current location';
                        button.innerHTML = '<i class="bi bi-geo-alt-fill" aria-hidden="true"></i>';
                        locationButton = button;

                        L.DomEvent.disableClickPropagation(container);
                        L.DomEvent.disableScrollPropagation(container);

                        L.DomEvent.on(button, 'click', function (event) {
                            L.DomEvent.preventDefault(event);
                            L.DomEvent.stopPropagation(event);
                            bootstrapGps(true);
                        });

                        return container;
                    },
                });

                mapInterface.map.addControl(new LocationControl());
            }

            function distanceInMeters(a, b) {
                const toRad = (value) => (value * Math.PI) / 180;
                const earthRadius = 6371000;
                const dLat = toRad(b.lat - a.lat);
                const dLng = toRad(b.lng - a.lng);
                const lat1 = toRad(a.lat);
                const lat2 = toRad(b.lat);
                const sinLat = Math.sin(dLat / 2);
                const sinLng = Math.sin(dLng / 2);
                const h = sinLat * sinLat + Math.cos(lat1) * Math.cos(lat2) * sinLng * sinLng;

                return 2 * earthRadius * Math.atan2(Math.sqrt(h), Math.sqrt(1 - h));
            }

            function applyPosition(position) {
                if (!mapInterface) return;

                const latitude = Number(position.coords.latitude);
                const longitude = Number(position.coords.longitude);
                const accuracy = Number(position.coords.accuracy);
                const now = Date.now();
                const currentPoint = { lat: latitude, lng: longitude };

                if (lastTrackedPoint) {
                    const movedMeters = distanceInMeters(lastTrackedPoint, currentPoint);
                    if (movedMeters < 5 && now - lastTrackTimestamp < 1200) {
                        return;
                    }
                }

                lastTrackedPoint = currentPoint;
                lastTrackTimestamp = now;

                mapInterface.selectPoint(latitude, longitude, { resolveLocation: false });
                mapInterface.setUserLocation?.(latitude, longitude, { accuracy });
                setLocatingState(false);

                if (!hasCentered) {
                    mapInterface.map.panTo([latitude, longitude], { animate: false });
                    hasCentered = true;
                } else if (!userHasAdjustedView) {
                    mapInterface.map.panTo([latitude, longitude], { animate: false });
                }
            }

            function startWatch(highAccuracy) {
                if (!navigator.geolocation || !mapInterface) return;

                if (watchId !== null) {
                    navigator.geolocation.clearWatch(watchId);
                }

                watchId = navigator.geolocation.watchPosition(
                    applyPosition,
                    (error) => {
                        if (highAccuracy && (error.code === 2 || error.code === 3)) {
                            startWatch(false);
                            return;
                        }

                        setLocatingState(false);
                    },
                    {
                        enableHighAccuracy: highAccuracy,
                        timeout: highAccuracy ? 9000 : 12000,
                        maximumAge: highAccuracy ? 0 : 5000,
                    }
                );
            }

            function bootstrapGps(force = false) {
                if (!navigator.geolocation || !mapInterface) return;
                if (!force && watchId !== null) return;

                setLocatingState(true);

                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        applyPosition(position);
                        startWatch(true);
                    },
                    () => {
                        navigator.geolocation.getCurrentPosition(
                            (position) => {
                                applyPosition(position);
                                startWatch(false);
                            },
                            () => {
                                setLocatingState(false);
                                startWatch(false);
                            },
                            { enableHighAccuracy: false, timeout: 8000, maximumAge: 15000 }
                        );
                    },
                    { enableHighAccuracy: true, timeout: 5000, maximumAge: 0 }
                );
            }

            function initWhenMapReady() {
                const mapEl = document.getElementById('mainPublicMap');
                if (!mapEl) return;

                const wireUp = () => {
                    if (!mapEl.mapApi) return;
                    mapInterface = mapEl.mapApi;
                    mapInterface.ensureSize();
                    createLocationControl();

                    const markUserAdjusted = () => {
                        if (hasCentered) {
                            userHasAdjustedView = true;
                        }
                    };

                    mapInterface.map.on('zoomstart', markUserAdjusted);
                    mapInterface.map.on('dragstart', markUserAdjusted);

                    bootstrapGps();
                };

                if (mapEl.mapApi) {
                    wireUp();
                    return;
                }

                mapEl.addEventListener('rsrs:map-ready', wireUp, { once: true });
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initWhenMapReady);
            } else {
                initWhenMapReady();
            }
        })();
    </script>
@endsection
