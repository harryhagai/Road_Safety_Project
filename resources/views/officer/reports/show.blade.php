{{-- Officer module view for show within the road safety dashboard. --}}

@extends('layouts.officerDashboardLayout')

@php
    $statusLabel = fn (?string $status) => str($status ?: 'unknown')->replace('_', ' ')->title();
    $statusTone = fn (?string $status) => match ($status) {
        'verified', 'resolved' => 'success',
        'under_review' => 'warning',
        'rejected' => 'danger',
        default => 'muted',
    };
    $automaticMatch = $report->ruleViolations->firstWhere('matched_automatically', true);
    $hasCoordinates = is_numeric($report->latitude) && is_numeric($report->longitude);
    $displayDescription = (string) ($report->description ?? '');
    if (preg_match('/Automatic overspeeding report:\s*([\d.]+)\s*km\/h recorded against a\s*([\d.]+)\s*km\/h speed limit for\s*(\d+)\s*seconds on\s*(.+)\./i', $displayDescription, $matches)) {
        $displayDescription = sprintf(
            'Vehicle recorded %.1f km/h against a %.1f km/h limit at %s.',
            (float) ($matches[1] ?? 0),
            (float) ($matches[2] ?? 0),
            trim((string) ($matches[4] ?? 'the selected road'))
        );
    }
    $reportMapPayload = [
        'point' => $hasCoordinates ? [
            'lat' => (float) $report->latitude,
            'lng' => (float) $report->longitude,
            'label' => $report->reference_no ?: ('Report #' . $report->id),
            'location' => $report->location_name ?: 'Report location',
        ] : null,
        'segments' => $report->ruleViolations
            ->map(function ($ruleViolation) {
                $segment = $ruleViolation->segment;

                return [
                    'id' => $segment?->id,
                    'name' => $segment?->segment_name ?: 'Unnamed segment',
                    'boundary_coordinates' => $segment?->boundary_coordinates,
                    'match_source' => $ruleViolation->matched_automatically ? 'automatic' : 'manual',
                ];
            })
            ->filter(fn ($segment) => !empty($segment['id']) && is_array($segment['boundary_coordinates'] ?? null))
            ->unique('id')
            ->values(),
    ];
    $normalizeCoordinatePoint = function ($coordinate): ?array {
        if (!is_array($coordinate)) {
            return null;
        }

        if (isset($coordinate['lat'], $coordinate['lng'])) {
            $lat = (float) $coordinate['lat'];
            $lng = (float) $coordinate['lng'];
            if ($lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180) {
                return ['lat' => $lat, 'lng' => $lng];
            }
            return null;
        }

        if (isset($coordinate['latitude'], $coordinate['longitude'])) {
            $lat = (float) $coordinate['latitude'];
            $lng = (float) $coordinate['longitude'];
            if ($lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180) {
                return ['lat' => $lat, 'lng' => $lng];
            }
            return null;
        }

        if (!array_is_list($coordinate) || count($coordinate) < 2) {
            return null;
        }

        if (!is_numeric($coordinate[0]) || !is_numeric($coordinate[1])) {
            return null;
        }

        $first = (float) $coordinate[0];
        $second = (float) $coordinate[1];

        // GeoJSON is commonly [lng, lat], but some payloads come as [lat, lng].
        if (abs($first) <= 20 && abs($second) > 20) {
            $lat = $first;
            $lng = $second;
        } else {
            $lat = $second;
            $lng = $first;
        }

        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return null;
        }

        return ['lat' => $lat, 'lng' => $lng];
    };
    $collectCoordinatePoints = null;
    $collectCoordinatePoints = function ($node, array &$points) use (&$collectCoordinatePoints, $normalizeCoordinatePoint): void {
        if (is_array($node)) {
            $normalized = $normalizeCoordinatePoint($node);
            if ($normalized) {
                $points[] = $normalized;
                return;
            }

            foreach ($node as $child) {
                $collectCoordinatePoints($child, $points);
            }
        }
    };
    $haversineDistanceMeters = function (float $lat1, float $lng1, float $lat2, float $lng2): float {
        $earthRadius = 6371000;
        $latDiff = deg2rad($lat2 - $lat1);
        $lngDiff = deg2rad($lng2 - $lng1);
        $a = sin($latDiff / 2) * sin($latDiff / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($lngDiff / 2) * sin($lngDiff / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    };
    $nearestSegmentPoint = null;
    $nearestSegmentDistanceMeters = null;
    if ($hasCoordinates) {
        $reportLat = (float) $report->latitude;
        $reportLng = (float) $report->longitude;

        foreach ($reportMapPayload['segments'] as $segmentPayload) {
            $geometry = $segmentPayload['boundary_coordinates'] ?? [];
            $coordinatesRoot = data_get($geometry, 'geometry.coordinates')
                ?? data_get($geometry, 'features.0.geometry.coordinates')
                ?? data_get($geometry, 'coordinates')
                ?? $geometry;

            $segmentPoints = [];
            $collectCoordinatePoints($coordinatesRoot, $segmentPoints);

            foreach ($segmentPoints as $point) {
                $distance = $haversineDistanceMeters($reportLat, $reportLng, (float) $point['lat'], (float) $point['lng']);
                if ($nearestSegmentDistanceMeters === null || $distance < $nearestSegmentDistanceMeters) {
                    $nearestSegmentDistanceMeters = $distance;
                    $nearestSegmentPoint = $point;
                }
            }
        }
    }
    $nearestSegmentDistanceLabel = $nearestSegmentDistanceMeters === null
        ? 'N/A'
        : ($nearestSegmentDistanceMeters < 1000
            ? number_format($nearestSegmentDistanceMeters, 1).' m'
            : number_format($nearestSegmentDistanceMeters / 1000, 2).' km');
    $nearestSegmentPointLabel = $nearestSegmentPoint
        ? number_format((float) $nearestSegmentPoint['lat'], 6).', '.number_format((float) $nearestSegmentPoint['lng'], 6)
        : 'No matched segment points';
    $reportMapPayload['nearest_point'] = $nearestSegmentPoint ? [
        'lat' => (float) $nearestSegmentPoint['lat'],
        'lng' => (float) $nearestSegmentPoint['lng'],
        'distance_meters' => $nearestSegmentDistanceMeters !== null ? round((float) $nearestSegmentDistanceMeters, 2) : null,
    ] : null;
@endphp

@section('page_header_actions')
    <a href="{{ route('officer.reports.index') }}" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
        <i class="bi bi-arrow-left" aria-hidden="true"></i>
        <span>Reports</span>
    </a>
@endsection

@section('content')
<div class="container-fluid px-3 px-lg-4 py-4 officer-report-detail-page">
    @if (session('success'))
        <div class="alert alert-success d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <div class="row g-3 g-xl-4">
        <div class="col-12 col-xl-5">
            <section class="report-detail-panel report-map-preview-panel h-100">
                <div class="report-detail-header">
                    <div>
                        <span class="report-detail-eyebrow">Map Preview</span>
                        <h3>{{ $report->location_name ?: 'Unknown location' }}</h3>
                    </div>
                </div>

                @if ($hasCoordinates)
                    <div
                        id="officerReportMiniMap"
                        class="report-mini-map"
                        data-lat="{{ (float) $report->latitude }}"
                        data-lng="{{ (float) $report->longitude }}"
                        data-location="{{ $report->location_name ?: 'Report location' }}"
                    ></div>
                    <script type="application/json" id="officerReportMapPayload">@json($reportMapPayload)</script>
                @else
                    <div class="text-muted small mt-2">Coordinates are not available for this report.</div>
                @endif
            </section>
        </div>

        <div class="col-12 col-xl-7">
            <section class="report-detail-panel h-100">
                <div class="report-detail-header">
                    <div>
                        <span class="report-detail-eyebrow">Report Reference</span>
                        <h3>{{ $report->reference_no }}</h3>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="report-badge report-badge--{{ $automaticMatch ? 'info' : 'muted' }}">
                            <i class="bi {{ $automaticMatch ? 'bi-cpu' : 'bi-person-lines-fill' }}" aria-hidden="true"></i>
                            {{ $automaticMatch ? 'Automatic' : 'Manual' }}
                        </span>
                        <span class="report-badge report-badge--{{ $statusTone($report->status) }}">{{ $statusLabel($report->status) }}</span>
                    </div>
                </div>

                <div class="report-detail-grid mb-4">
                    <div>
                        <span>Violation type</span>
                        <strong>{{ $report->violationType?->name ?? 'Unassigned' }}</strong>
                    </div>
                    <div>
                        <span>Priority</span>
                        <strong>{{ $statusLabel($report->priority) }}</strong>
                    </div>
                    <div>
                        <span>Reported at</span>
                        <strong>{{ optional($report->reported_at)->format('d M Y, H:i') ?? optional($report->created_at)->format('d M Y, H:i') }}</strong>
                    </div>
                    <div>
                        <span>Reviewed at</span>
                        <strong>{{ optional($report->reviewed_at)->format('d M Y, H:i') ?? 'Not reviewed' }}</strong>
                    </div>
                </div>

                <div class="report-detail-block mb-4">
                    <h4>Description</h4>
                    <p>{{ $displayDescription }}</p>
                </div>

                <div class="report-detail-block">
                    <h4>Location Summary</h4>
                    <div class="report-location-grid">
                        <div>
                            <span class="report-detail-label">Coordinates</span>
                            <div class="fw-semibold">
                                {{ $hasCoordinates ? number_format((float) $report->latitude, 6) . ', ' . number_format((float) $report->longitude, 6) : 'N/A' }}
                            </div>
                        </div>
                        <div>
                            <span class="report-detail-label">Review state</span>
                            <div class="fw-semibold">{{ optional($report->reviewed_at)->format('d M Y, H:i') ?? 'Pending review' }}</div>
                        </div>
                        <div>
                            <span class="report-detail-label">Distance To Nearest Segment Point</span>
                            <div class="fw-semibold">{{ $nearestSegmentDistanceLabel }}</div>
                            <div class="small text-muted">{{ $nearestSegmentPointLabel }}</div>
                        </div>
                    </div>
                </div>

            </section>
        </div>

        <div class="col-12">
            <section class="report-detail-panel report-action-panel">
                <div class="report-detail-header">
                    <div>
                        <span class="report-detail-eyebrow">Officer Action</span>
                        <h3>Review status</h3>
                    </div>
                </div>

                <form method="POST" action="{{ route('officer.reports.update', $report) }}" class="d-grid gap-3">
                    @csrf
                    @method('PUT')

                    <div class="row g-3">
                        <div class="col-12 col-lg-4">
                            <label for="status" class="form-label">Status</label>
                            <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
                                @foreach ($statuses as $status)
                                    <option value="{{ $status }}" @selected(old('status', $report->status) === $status)>{{ $statusLabel($status) }}</option>
                                @endforeach
                            </select>
                            @error('status')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-lg-4">
                            <label for="priority" class="form-label">Priority</label>
                            <select id="priority" name="priority" class="form-select @error('priority') is-invalid @enderror" required>
                                @foreach (['normal', 'medium', 'high'] as $priority)
                                    <option value="{{ $priority }}" @selected(old('priority', $report->priority) === $priority)>{{ $statusLabel($priority) }}</option>
                                @endforeach
                            </select>
                            @error('priority')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-lg-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100 d-inline-flex align-items-center justify-content-center gap-2">
                                <i class="bi bi-save" aria-hidden="true"></i>
                                <span>Save review</span>
                            </button>
                        </div>
                    </div>

                    <div>
                        <label for="officer_notes" class="form-label">Officer notes</label>
                        <textarea id="officer_notes" name="officer_notes" class="form-control @error('officer_notes') is-invalid @enderror" rows="5" placeholder="Add verification notes or action taken.">{{ old('officer_notes', $report->officer_notes) }}</textarea>
                        @error('officer_notes')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </form>
            </section>
        </div>
    </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
<link rel="stylesheet" href="{{ asset('css/rsrsOfficerReportShow.css') }}?v={{ filemtime(public_path('css/rsrsOfficerReportShow.css')) }}">
@endpush

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script src="{{ asset('js/rsrsOfficerReportShow.js') }}?v={{ filemtime(public_path('js/rsrsOfficerReportShow.js')) }}"></script>
@endpush
