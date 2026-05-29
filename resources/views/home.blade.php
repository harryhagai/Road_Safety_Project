{{-- Blade view for the home page in the RSRS application. --}}

@inject('mapConfigService', 'App\Services\MapConfigService')
@extends('layouts.app')

@section('title', 'RSRS - Road Safety Reporting System')

@push('critical-head')
    <link rel="stylesheet" href="{{ asset('css/rsrsHomeLoader.css') }}">
@endpush

@push('page_loader')
    <div class="home-page-loader" id="homePageLoader" data-home-map-loader role="status" aria-live="polite">
        <div class="home-page-loader__panel">
            <div class="home-page-loader__brand">rsrs</div>
            <div class="home-page-loader__visual" aria-hidden="true">
                <span class="home-page-loader__ring home-page-loader__ring--outer"></span>
                <span class="home-page-loader__ring home-page-loader__ring--middle"></span>
                <span class="home-page-loader__ring home-page-loader__ring--inner"></span>
                <span class="home-page-loader__core"></span>
            </div>
            <div class="home-page-loader__content">
                <span class="home-page-loader__eyebrow">road safety reporting system</span>
                <span class="home-page-loader__message">loading the live map...</span>
                <span>preparing location, layers, and your first view.</span>
            </div>
        </div>
    </div>
@endpush

@section('content')
    @php
        $mapConfig = $mapConfigService->forFrontend();
    @endphp

    <div class="container-fluid container-xl geo-workspace px-2 px-md-3 py-2 py-md-3 home-geo-workspace">
        <div class="row g-2 g-md-3 geo-workspace__grid">
            <div class="col-12">
                <section class="geo-card geo-card--fill geo-card--map home-geo-card">
                    <div class="home-map-stage">
                        <div class="home-speed-alert home-speed-alert--idle" data-home-speed-alert aria-live="polite">
                            <div class="home-speed-alert__icon" data-home-speed-alert-icon>
                                <i class="bi bi-info-circle-fill" aria-hidden="true"></i>
                            </div>
                            <div class="home-speed-alert__body">
                                <div class="home-speed-alert__label" data-home-speed-alert-label>Speed info</div>
                                <div class="home-speed-alert__message" data-home-speed-alert-message>
                                    We are checking your location and the nearest speed rule.
                                </div>
                                <div class="home-speed-alert__meta">
                                    <span data-home-speed-alert-location>Segment: waiting...</span>
                                    <span data-home-speed-alert-limit>Speed limit: unknown</span>
                                </div>
                            </div>
                        </div>
                        <div class="home-speed-widget" data-home-speed-widget aria-live="polite">
                            <span class="home-speed-widget__label">Speed</span>
                            <div class="home-speed-widget__dial" aria-hidden="true">
                                <span class="home-speed-widget__ring"></span>
                                <span class="home-speed-widget__core"></span>
                                <span class="home-speed-widget__pulse"></span>
                            </div>
                            <div class="home-speed-widget__value">
                                <strong data-home-speed-value>0</strong>
                                <span>km/h</span>
                            </div>
                            <small data-home-speed-status>Waiting for movement...</small>
                        </div>
                        <x-map.canvas id="mainPublicMap" :config="$mapConfig" height="100%" :show-toolbar="false" mode="viewer" />
                    </div>
                </section>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <link rel="stylesheet" href="{{ asset('css/rsrsMap.css') }}">
    <link rel="stylesheet" href="{{ asset('css/rsrsHomeMap.css') }}">
@endpush

@section('scripts')
    <script>
        (() => {
            const syncViewportOffsets = () => {
                const header = document.querySelector('.header-wrapper');
                const footer = document.querySelector('.footer-wrapper');
                const root = document.documentElement;

                const headerHeight = header ? Math.round(header.getBoundingClientRect().height) : 0;
                const footerHeight = footer ? Math.round(footer.getBoundingClientRect().height) : 0;

                root.style.setProperty('--home-header-height', `${headerHeight}px`);
                root.style.setProperty('--home-footer-height', `${footerHeight}px`);
            };

            window.addEventListener('load', syncViewportOffsets);
            window.addEventListener('resize', syncViewportOffsets);
            syncViewportOffsets();
        })();
    </script>
    <script>
        window.rsrsAutoSpeedReporting = {
            evaluateUrl: @json(route('auto-speed-reports.evaluate')),
            storeUrl: @json(route('auto-speed-reports.store')),
            csrfToken: @json(csrf_token()),
        };
    </script>
    <script src="{{ asset('js/rsrsHomeLoader.js') }}"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script src="{{ asset('js/rsrsMapPicker.js') }}"></script>
    <script src="{{ asset('js/rsrsHomeMap.js') }}"></script>
@endsection
