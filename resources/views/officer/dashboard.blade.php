{{-- Officer module view for dashboard within the road safety dashboard. --}}

@extends('layouts.officerDashboardLayout')

@section('content')
    <div class="container-fluid px-3 px-lg-4 py-4 roadofficer-dashboard-page">
        <div class="roadofficer-dashboard-stats">
            @foreach ($stats as $stat)
                <article class="roadofficer-stat-card tone-{{ $loop->iteration }}">
                    <span class="roadofficer-stat-icon">
                        <i class="bi {{ $stat['icon'] }}" aria-hidden="true"></i>
                    </span>
                    <div>
                        <div class="roadofficer-stat-label">{{ $stat['label'] }}</div>
                        <div class="roadofficer-stat-value">{{ number_format($stat['value']) }}</div>
                    </div>
                </article>
            @endforeach
        </div>

        <div class="roadofficer-dashboard-grid">
            <section class="roadofficer-dashboard-panel">
                <div class="roadofficer-panel-head">
                    <span class="roadofficer-panel-icon">
                        <i class="bi bi-bar-chart-line" aria-hidden="true"></i>
                    </span>
                    <div>
                        <h3 class="roadofficer-panel-title">Status breakdown</h3>
                    </div>
                </div>

                <div class="roadofficer-panel-body">
                    @if ($reportStatuses->isNotEmpty())
                        <div class="roadofficer-status-list">
                            @foreach ($reportStatuses as $status)
                                <div class="roadofficer-status-item">
                                    <span>{{ $status['label'] }}</span>
                                    <strong>{{ number_format($status['value']) }}</strong>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="roadofficer-empty-state">
                            <i class="bi bi-bar-chart-line"></i>
                            <h4>No report status data</h4>
                            <p>Report summaries will appear here once cases are submitted into the system.</p>
                        </div>
                    @endif
                </div>
            </section>

            <section class="roadofficer-dashboard-panel">
                <div class="roadofficer-panel-head">
                    <span class="roadofficer-panel-icon">
                        <i class="bi bi-clipboard-data" aria-hidden="true"></i>
                    </span>
                    <div>
                        <h3 class="roadofficer-panel-title">Latest submitted reports</h3>
                    </div>
                    <a href="{{ route('officer.reports.index') }}" class="roadofficer-panel-link">Open reports</a>
                </div>

                <div class="roadofficer-panel-body roadofficer-panel-body--table">
                    @if ($recentReports->isNotEmpty())
                        <div class="table-responsive">
                            <table class="table roadofficer-dashboard-table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Reference</th>
                                        <th>Violation</th>
                                        <th>Status</th>
                                        <th>Reported at</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($recentReports as $report)
                                        <tr>
                                            <td>{{ $report->reference_no ?: 'Report #' . $report->id }}</td>
                                            <td>{{ $report->violationType?->name ?? 'Unassigned' }}</td>
                                            <td>
                                                <span class="roadofficer-table-badge">
                                                    {{ str($report->status ?: 'unknown')->replace('_', ' ')->title() }}
                                                </span>
                                            </td>
                                            <td>{{ optional($report->reported_at)->format('d M Y, H:i') ?? optional($report->created_at)->format('d M Y, H:i') ?? 'N/A' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="roadofficer-empty-state">
                            <i class="bi bi-clipboard-x"></i>
                            <h4>No reports yet</h4>
                            <p>The dashboard will list the latest submitted reports once reporting activity starts.</p>
                        </div>
                    @endif
                </div>
            </section>

            <section class="roadofficer-dashboard-panel">
                <div class="roadofficer-panel-head">
                    <span class="roadofficer-panel-icon">
                        <i class="bi bi-map" aria-hidden="true"></i>
                    </span>
                    <div>
                        <h3 class="roadofficer-panel-title">Mapped hotspot locations</h3>
                    </div>
                    <a href="{{ route('hotspots.index') }}" class="roadofficer-panel-link">View full map</a>
                </div>

                <div class="roadofficer-panel-body roadofficer-panel-body--map">
                    @if ($hotspots->isNotEmpty())
                        <div id="officerHotspotsMap" class="roadofficer-hotspot-map"></div>
                    @else
                        <div class="roadofficer-empty-state roadofficer-empty-state--map">
                            <i class="bi bi-map"></i>
                            <h4>No hotspots recorded</h4>
                            <p>When officers add hotspot records, the map will display them here automatically.</p>
                        </div>
                    @endif
                </div>
            </section>

            <section class="roadofficer-dashboard-panel">
                <div class="roadofficer-panel-head">
                    <span class="roadofficer-panel-icon">
                        <i class="bi bi-geo-alt" aria-hidden="true"></i>
                    </span>
                    <div>
                        <h3 class="roadofficer-panel-title">Areas that need attention</h3>
                    </div>
                </div>

                <div class="roadofficer-panel-body">
                    @if ($hotspots->isNotEmpty())
                        <div class="roadofficer-hotspot-list">
                            @foreach ($hotspots as $hotspot)
                                @php
                                    $severity = $hotspot->severity ?: 'medium';
                                @endphp
                                <article class="roadofficer-hotspot-card">
                                    <div class="roadofficer-hotspot-card__top">
                                        <div>
                                            <h4 class="roadofficer-hotspot-card__title">{{ $hotspot->name ?: 'Unnamed hotspot' }}</h4>
                                            <p class="roadofficer-hotspot-card__meta">
                                                Rule: {{ $hotspot->rule?->rule_name ?? 'Not linked' }}
                                            </p>
                                        </div>
                                        <span class="roadofficer-severity-badge roadofficer-severity-badge--{{ str_replace('_', '-', $severity) }}">
                                            {{ str($severity)->replace('_', ' ')->title() }}
                                        </span>
                                    </div>

                                    <div class="roadofficer-hotspot-card__details">
                                        <span>Frequency: {{ number_format((int) ($hotspot->frequency ?: 0)) }}</span>
                                        <span>Radius: {{ number_format((float) ($hotspot->radius_meters ?: 0)) }} m</span>
                                        <span>Updated: {{ optional($hotspot->last_updated_at ?? $hotspot->updated_at)->format('d M Y, H:i') ?? 'N/A' }}</span>
                                    </div>

                                    <button type="button" class="roadofficer-focus-btn" data-hotspot-focus="{{ $hotspot->id }}">
                                        <i class="bi bi-crosshair"></i>
                                        <span>Focus on map</span>
                                    </button>
                                </article>
                            @endforeach
                        </div>
                    @else
                        <div class="roadofficer-empty-state">
                            <i class="bi bi-geo"></i>
                            <h4>No hotspot details available</h4>
                            <p>Hotspot records will appear here once new dangerous locations are captured in the system.</p>
                        </div>
                    @endif
                </div>
            </section>
        </div>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/rsrsOfficerDashboard.css') }}?v={{ filemtime(public_path('css/rsrsOfficerDashboard.css')) }}">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
@endpush

@section('scripts')
    @if ($hotspots->isNotEmpty())
        <script>
            window.rsrsOfficerDashboardMap = {
                mapConfig: @json($mapConfig),
                hotspots: @json($hotspotPayload),
            };
        </script>
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    @endif
    <script src="{{ asset('js/rsrsOfficerDashboard.js') }}?v={{ filemtime(public_path('js/rsrsOfficerDashboard.js')) }}"></script>
@endsection
