@extends('layouts.officerDashboardLayout')

@section('content')
    <div class="container-fluid geo-workspace px-1 px-lg-2 py-2">
        <div class="row g-2 geo-workspace__grid">
            <div class="col-12 col-xl-8">
                <section class="geo-card geo-card--fill geo-card--map">
                    <div class="geo-card__header">
                        <div>
                            <span class="geo-card__eyebrow">Phase 1</span>
                            <h2 class="geo-card__title">Geospatial foundation lab</h2>
                            <p class="geo-card__text mb-0">Map stack, point picking, and reverse geocoding preview.</p>
                        </div>
                    </div>

                    <x-map.canvas id="roadSegmentMapLab" :config="$mapConfig" height="calc(100vh - 235px)" />
                </section>
            </div>

            <div class="col-12 col-xl-4">
                <section class="geo-card geo-card--fill geo-card--inspector">
                    <div class="geo-card__header">
                        <div>
                            <span class="geo-card__eyebrow">Inspector</span>
                            <h2 class="geo-card__title">Map payload preview</h2>
                            <p class="geo-card__text mb-0">Live payload and resolved address for the selected point.</p>
                        </div>
                    </div>

                    <div class="geo-card__meta">
                        <div class="geo-meta-row">
                            <span class="geo-meta-row__label">Provider</span>
                            <span class="geo-meta-row__value">{{ $mapConfig['provider'] }}</span>
                        </div>
                        <div class="geo-meta-row">
                            <span class="geo-meta-row__label">Tile source</span>
                            <span class="geo-meta-row__value">OpenStreetMap</span>
                        </div>
                        <div class="geo-meta-row">
                            <span class="geo-meta-row__label">Default center</span>
                            <span class="geo-meta-row__value">
                                {{ $mapConfig['defaultCenter']['lat'] }}, {{ $mapConfig['defaultCenter']['lng'] }}
                            </span>
                        </div>
                    </div>

                    <div class="geo-payload-panel">
                        <div class="geo-payload-panel__header">
                            <span>Current payload</span>
                        </div>
                        <pre id="mapPayloadPreview" class="geo-payload-panel__content">No point selected yet.</pre>
                    </div>

                    <div class="geo-location-panel">
                        <div class="geo-location-panel__label">Resolved location</div>
                        <div id="mapResolvedLocation" class="geo-location-panel__value">
                            Location name will appear here after reverse geocoding.
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <link rel="stylesheet" href="{{ asset('css/map.css') }}">
@endpush

@section('scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
@endsection

@push('scripts')
    <script src="{{ asset('js/map-picker.js') }}"></script>
    <script src="{{ asset('js/officerMapLab.js') }}"></script>
@endpush
