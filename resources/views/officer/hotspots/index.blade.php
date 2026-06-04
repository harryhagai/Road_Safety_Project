@extends('layouts.officerDashboardLayout')

@section('title', 'Hotspots')

@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <link rel="stylesheet" href="{{ asset('css/rsrsOfficerHotspots.css') }}?v={{ filemtime(public_path('css/rsrsOfficerHotspots.css')) }}">
@endpush

@section('page_header_actions')
    @php
        $total = $reports->count();
        $updated = optional($reports->first()?->reported_at ?? $reports->first()?->created_at)?->format('H:i:s') ?: '-';
    @endphp
    <div class="officer-hotspots-stats">
        <div class="officer-hotspots-stats__item"><span>Total Reports</span><strong>{{ $total }}</strong></div>
        <div class="officer-hotspots-stats__item"><span>Average / Point</span><strong>{{ number_format($averageReports, 2) }}</strong></div>
        <div class="officer-hotspots-stats__item"><span>Danger Points</span><strong>{{ $dangerPoints }}</strong></div>
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
    <script type="application/json" id="officerHotspotsPayload">
        @json([
            'mapConfig' => $mapConfig,
            'points' => $reportPayload,
        ])
    </script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script src="https://unpkg.com/leaflet-rotate@0.2.8/dist/leaflet-rotate.js"></script>
    <script src="{{ asset('js/rsrsOfficerHotspots.js') }}?v={{ filemtime(public_path('js/rsrsOfficerHotspots.js')) }}"></script>
@endsection
