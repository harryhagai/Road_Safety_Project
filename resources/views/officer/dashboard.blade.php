{{-- Officer module view for dashboard within the road safety dashboard. --}}

@extends('layouts.officerDashboardLayout')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/rsrsOfficerDashboard.css') }}?v={{ filemtime(public_path('css/rsrsOfficerDashboard.css')) }}">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
@endpush

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
                        <h3 class="roadofficer-panel-title">Speed violation analytics</h3>
                    </div>
                    @if (($speedAnalytics['sample_count'] ?? 0) > 0)
                        <div class="roadofficer-panel-metric">
                            <span>Avg Violation Speed</span>
                            <strong>{{ number_format($speedAnalytics['average_speed'], 1) }} km/h</strong>
                        </div>
                    @endif
                </div>

                <div class="roadofficer-panel-body">
                    @if (($speedAnalytics['sample_count'] ?? 0) > 0)
                        <div class="roadofficer-speed-analytics">
                            <div class="roadofficer-speed-chart-wrap">
                                <canvas id="officerSpeedTrendLineChart" height="190"></canvas>
                            </div>
                        </div>
                    @else
                        <div class="roadofficer-empty-state">
                            <i class="bi bi-bar-chart-line"></i>
                            <h4>No violation speed analytics yet</h4>
                            <p>Line trend will appear once automatic speed violation reports are available.</p>
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
                    @if ($attentionHotspotPayload->isNotEmpty())
                        <div id="officerHotspotsMap" class="roadofficer-hotspot-map"></div>
                    @else
                        <div class="roadofficer-empty-state roadofficer-empty-state--map">
                            <i class="bi bi-map"></i>
                            <h4>No violation hotspots yet</h4>
                            <p>Once more violations are submitted, high-priority segment hotspots will appear here.</p>
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

                <div class="roadofficer-panel-body roadofficer-panel-body--attention">
                    @if ($attentionReports->isNotEmpty())
                        <div class="roadofficer-hotspot-list">
                            @foreach ($attentionReports as $report)
                                <article class="roadofficer-hotspot-card">
                                    <div class="roadofficer-hotspot-card__top">
                                        <div>
                                            <h4 class="roadofficer-hotspot-card__title">{{ $report->reference_no ?: 'Report #' . $report->id }}</h4>
                                        </div>
                                    </div>

                                    <div class="roadofficer-hotspot-card__details">
                                        <span>
                                            Violation: {{ $report->violationType?->name ?? 'Unassigned' }} | Status:
                                            <span class="roadofficer-table-badge">
                                                {{ str($report->status ?: 'unknown')->replace('_', ' ')->title() }}
                                            </span>
                                        </span>
                                        <span>Reported: {{ optional($report->reported_at)->format('d M Y, H:i') ?? optional($report->created_at)->format('d M Y, H:i') ?? 'N/A' }}</span>
                                        <span>Location: {{ $report->location_name ?: 'Unknown location' }}</span>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    @else
                        <div class="roadofficer-empty-state">
                            <i class="bi bi-geo"></i>
                            <h4>No reports yet</h4>
                            <p>Recent reports that need attention will appear here automatically.</p>
                        </div>
                    @endif
                </div>
            </section>
        </div>
    </div>
@endsection

@section('scripts')
    @if (($speedAnalytics['sample_count'] ?? 0) > 0)
        <script>
            window.rsrsOfficerSpeedAnalytics = @json($speedAnalytics);
        </script>
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    @endif
    @if ($attentionHotspotPayload->isNotEmpty())
        <script>
            window.rsrsOfficerDashboardMap = {
                mapConfig: @json($mapConfig),
                hotspots: @json($attentionHotspotPayload),
            };
        </script>
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    @endif
    <script src="{{ asset('js/rsrsOfficerDashboard.js') }}?v={{ filemtime(public_path('js/rsrsOfficerDashboard.js')) }}"></script>
@endsection
