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
                    <x-map.canvas id="mainPublicMap" :config="$mapConfig" height="100%" :show-toolbar="false" mode="viewer" />
                </section>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <link rel="stylesheet" href="{{ asset('css/rsrsMap.css') }}">
    <link rel="stylesheet" href="{{ asset('css/rsrsHomeMap.css') }}?v={{ filemtime(public_path('css/rsrsHomeMap.css')) }}">
@endpush

@section('scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script src="{{ asset('js/rsrsMapPicker.js') }}?v={{ filemtime(public_path('js/rsrsMapPicker.js')) }}"></script>
    <script src="{{ asset('js/rsrsHomeMap.js') }}?v={{ filemtime(public_path('js/rsrsHomeMap.js')) }}"></script>
@endsection
