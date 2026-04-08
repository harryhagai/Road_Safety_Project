@extends('layouts.officerDashboardLayout')

@section('content')
    <div class="container-fluid geo-workspace px-1 px-lg-2 py-2">
        <div class="row g-2 geo-workspace__grid">
            <div class="col-12 col-xl-8">
                <section class="geo-card geo-card--fill geo-card--map">
                    <div class="geo-card__header">
                        <div>
                            <h2 class="geo-card__title">Road segment mapping</h2>
                            <p class="geo-card__text mb-0">Select a location and review the mapped coordinates.</p>
                        </div>
                    </div>

                    <x-map.canvas id="roadSegmentMapLab" :config="$mapConfig" height="calc(100vh - 235px)" :show-toolbar="false" />
                </section>
            </div>

            <div class="col-12 col-xl-4">
                <section class="geo-card geo-card--fill geo-card--inspector">
                    <div class="geo-card__header">
                        <div>
                            <h2 class="geo-card__title">Location details</h2>
                            <p class="geo-card__text mb-0">Coordinates, payload, and resolved address.</p>
                        </div>
                    </div>

                    <div class="geo-location-panel geo-location-panel--compact">
                        <div class="geo-location-panel__label">Selected coordinates</div>
                        <div id="selectedCoordinatesPanel" class="geo-location-panel__value">
                            Click on the map to choose a location.
                        </div>
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
