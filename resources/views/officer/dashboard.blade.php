@extends('layouts.officerDashboardLayout')

@section('content')
<div class="container-fluid px-3 px-lg-4 py-4 officer-dashboard-page">
    <div class="row g-3 g-xl-4 mb-4">
        @foreach ($stats as $stat)
            <div class="col-12 col-sm-6 col-xxl-3">
                <article class="dashboard-stat-card dashboard-stat-card--{{ $stat['tone'] }}">
                    <div class="dashboard-stat-card__icon">
                        <i class="bi {{ $stat['icon'] }}"></i>
                    </div>
                    <div>
                        <span class="dashboard-stat-card__label">{{ $stat['label'] }}</span>
                        <h2 class="dashboard-stat-card__value">{{ number_format($stat['value']) }}</h2>
                    </div>
                </article>
            </div>
        @endforeach
    </div>

    <div class="row g-3 g-xl-4 mb-4">
        <div class="col-12 col-xl-8">
            <section class="dashboard-panel h-100">
                <div class="dashboard-panel__header">
                    <div>
                        <span class="dashboard-panel__eyebrow">Quick Summary</span>
                        <h3 class="dashboard-panel__title">Operational overview</h3>
                    </div>
                </div>

                <div class="row g-3">
                    @foreach ($summaryTiles as $tile)
                        <div class="col-12 col-sm-6">
                            <article class="dashboard-summary-tile">
                                <div class="dashboard-summary-tile__icon">
                                    <i class="bi {{ $tile['icon'] }}"></i>
                                </div>
                                <div>
                                    <span class="dashboard-summary-tile__label">{{ $tile['label'] }}</span>
                                    <div class="dashboard-summary-tile__value">{{ number_format($tile['value']) }}</div>
                                </div>
                            </article>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>

        <div class="col-12 col-xl-4">
            <section class="dashboard-panel h-100">
                <div class="dashboard-panel__header">
                    <div>
                        <span class="dashboard-panel__eyebrow">Report Activity</span>
                        <h3 class="dashboard-panel__title">Status breakdown</h3>
                    </div>
                </div>

                <div class="dashboard-metric-list">
                    @forelse ($reportStatuses as $status)
                        <div class="dashboard-metric-row">
                            <span>{{ $status['label'] }}</span>
                            <strong>{{ number_format($status['value']) }}</strong>
                        </div>
                    @empty
                        <p class="dashboard-empty-state mb-0">No report statuses available yet.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </div>

    <div class="row g-3 g-xl-4 mb-4">
        <div class="col-12 col-xl-6">
            <section class="dashboard-panel h-100">
                <div class="dashboard-panel__header">
                    <div>
                        <span class="dashboard-panel__eyebrow">Rule Coverage</span>
                        <h3 class="dashboard-panel__title">Top rule types</h3>
                    </div>
                    <a href="{{ route('officer.road-rules.index') }}" class="btn btn-sm dashboard-ghost-btn">Open rules</a>
                </div>

                <div class="dashboard-metric-list">
                    @forelse ($ruleTypes as $ruleType)
                        <div class="dashboard-metric-row">
                            <span>{{ $ruleType['label'] }}</span>
                            <strong>{{ number_format($ruleType['value']) }}</strong>
                        </div>
                    @empty
                        <p class="dashboard-empty-state mb-0">No road rule records found.</p>
                    @endforelse
                </div>
            </section>
        </div>

        <div class="col-12 col-xl-6">
            <section class="dashboard-panel h-100">
                <div class="dashboard-panel__header">
                    <div>
                        <span class="dashboard-panel__eyebrow">Violation Trends</span>
                        <h3 class="dashboard-panel__title">Most used violation types</h3>
                    </div>
                    <a href="{{ route('officer.violation-types.index') }}" class="btn btn-sm dashboard-ghost-btn">Manage types</a>
                </div>

                <div class="dashboard-metric-list">
                    @forelse ($violationTypes as $type)
                        <div class="dashboard-metric-row">
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span>{{ $type['label'] }}</span>
                                <span class="dashboard-badge {{ $type['active'] ? 'dashboard-badge--success' : 'dashboard-badge--muted' }}">
                                    {{ $type['active'] ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                            <strong>{{ number_format($type['value']) }}</strong>
                        </div>
                    @empty
                        <p class="dashboard-empty-state mb-0">No violation types have been configured yet.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </div>

    <div class="row g-3 g-xl-4">
        <div class="col-12 col-xl-6">
            <section class="dashboard-panel h-100">
                <div class="dashboard-panel__header">
                    <div>
                        <span class="dashboard-panel__eyebrow">Latest Reports</span>
                        <h3 class="dashboard-panel__title">Recently submitted cases</h3>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table dashboard-table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Reference</th>
                                <th>Violation</th>
                                <th>Status</th>
                                <th>Reported</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recentReports as $report)
                                <tr>
                                    <td>{{ $report->reference_no ?: 'Report #' . $report->id }}</td>
                                    <td>{{ $report->violationType?->name ?? 'Unassigned' }}</td>
                                    <td>
                                        <span class="dashboard-badge dashboard-badge--muted">
                                            {{ str($report->status ?: 'unknown')->replace('_', ' ')->title() }}
                                        </span>
                                    </td>
                                    <td>{{ optional($report->reported_at)->format('d M Y, H:i') ?? optional($report->created_at)->format('d M Y, H:i') ?? 'N/A' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">No reports available.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <div class="col-12 col-xl-6">
            <section class="dashboard-panel h-100">
                <div class="dashboard-panel__header">
                    <div>
                        <span class="dashboard-panel__eyebrow">Recent Segments</span>
                        <h3 class="dashboard-panel__title">Mapped road segments</h3>
                    </div>
                    <a href="{{ route('officer.road-segments.index') }}" class="btn btn-sm dashboard-ghost-btn">View segments</a>
                </div>

                <div class="table-responsive">
                    <table class="table dashboard-table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Segment</th>
                                <th>Type</th>
                                <th>Length</th>
                                <th>Rules</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recentSegments as $segment)
                                <tr>
                                    <td>{{ $segment->segment_name }}</td>
                                    <td>{{ $segment->segment_type_name ?: 'General' }}</td>
                                    <td>{{ $segment->length_km ? number_format((float) $segment->length_km, 2) . ' km' : 'N/A' }}</td>
                                    <td>{{ number_format($segment->road_rules_count) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">No road segments available.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <div class="col-12 col-xl-6">
            <section class="dashboard-panel h-100">
                <div class="dashboard-panel__header">
                    <div>
                        <span class="dashboard-panel__eyebrow">Recent Rules</span>
                        <h3 class="dashboard-panel__title">Latest enforcement rules</h3>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table dashboard-table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Rule</th>
                                <th>Type</th>
                                <th>Segment</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recentRules as $rule)
                                <tr>
                                    <td>{{ $rule->rule_name }}</td>
                                    <td>{{ str($rule->rule_type ?: 'general')->replace('_', ' ')->title() }}</td>
                                    <td>{{ $rule->segment?->segment_name ?? 'Not linked' }}</td>
                                    <td>
                                        <span class="dashboard-badge {{ $rule->is_active ? 'dashboard-badge--success' : 'dashboard-badge--muted' }}">
                                            {{ $rule->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">No road rules available.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <div class="col-12 col-xl-6">
            <section class="dashboard-panel h-100">
                <div class="dashboard-panel__header">
                    <div>
                        <span class="dashboard-panel__eyebrow">Hotspot Watch</span>
                        <h3 class="dashboard-panel__title">Latest hotspot updates</h3>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table dashboard-table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Hotspot</th>
                                <th>Severity</th>
                                <th>Linked Rule</th>
                                <th>Updated</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($hotspots as $hotspot)
                                <tr>
                                    <td>{{ $hotspot->name }}</td>
                                    <td>{{ str($hotspot->severity ?: 'unknown')->replace('_', ' ')->title() }}</td>
                                    <td>{{ $hotspot->rule?->rule_name ?? 'Not linked' }}</td>
                                    <td>{{ optional($hotspot->last_updated_at)->format('d M Y, H:i') ?? optional($hotspot->updated_at)->format('d M Y, H:i') ?? 'N/A' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">No hotspots have been recorded.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .officer-dashboard-page {
        color: #232c3a;
    }

    .dashboard-stat-card,
    .dashboard-panel,
    .dashboard-summary-tile {
        background: #fff;
        border: 1px solid rgba(35, 44, 58, 0.1);
        box-shadow: 0 16px 40px rgba(27, 34, 48, 0.07);
    }

    .dashboard-stat-card,
    .dashboard-summary-tile {
        border-radius: 24px;
    }

    .dashboard-panel {
        border-radius: 28px;
        padding: 1.35rem;
    }

    .dashboard-stat-card {
        display: flex;
        align-items: center;
        gap: 1rem;
        min-height: 138px;
        padding: 1.25rem 1.35rem;
        overflow: hidden;
        position: relative;
    }

    .dashboard-stat-card::after {
        content: '';
        position: absolute;
        inset: auto -20px -40px auto;
        width: 120px;
        height: 120px;
        border-radius: 50%;
        opacity: 0.12;
        background: currentColor;
    }

    .dashboard-stat-card--blue {
        color: #1e5eff;
        background: linear-gradient(180deg, rgba(30, 94, 255, 0.08), #fff);
    }

    .dashboard-stat-card--slate {
        color: #475569;
        background: linear-gradient(180deg, rgba(71, 85, 105, 0.08), #fff);
    }

    .dashboard-stat-card--teal {
        color: #0f766e;
        background: linear-gradient(180deg, rgba(15, 118, 110, 0.08), #fff);
    }

    .dashboard-stat-card--amber {
        color: #b45309;
        background: linear-gradient(180deg, rgba(180, 83, 9, 0.08), #fff);
    }

    .dashboard-stat-card__icon,
    .dashboard-summary-tile__icon {
        width: 56px;
        height: 56px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 18px;
        background: rgba(255, 255, 255, 0.76);
        border: 1px solid rgba(35, 44, 58, 0.08);
        font-size: 1.35rem;
        flex-shrink: 0;
    }

    .dashboard-stat-card__label,
    .dashboard-summary-tile__label,
    .dashboard-panel__eyebrow {
        display: block;
        font-size: 0.72rem;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #667085;
    }

    .dashboard-stat-card__value {
        margin: 0.25rem 0 0;
        font-size: clamp(1.65rem, 2vw, 2.1rem);
        color: #232c3a;
    }

    .dashboard-panel__header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .dashboard-panel__title {
        margin: 0.25rem 0 0;
        font-size: 1.02rem;
        color: #232c3a;
    }

    .dashboard-summary-tile {
        display: flex;
        align-items: center;
        gap: 0.95rem;
        padding: 1rem 1.05rem;
        height: 100%;
    }

    .dashboard-summary-tile__value {
        font-size: 1.5rem;
        font-weight: 700;
        color: #232c3a;
    }

    .dashboard-metric-list {
        display: grid;
        gap: 0.85rem;
    }

    .dashboard-metric-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 0.9rem 1rem;
        border-radius: 18px;
        background: rgba(35, 44, 58, 0.04);
        border: 1px solid rgba(35, 44, 58, 0.08);
    }

    .dashboard-badge {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: 0.28rem 0.65rem;
        font-size: 0.72rem;
        font-weight: 600;
        line-height: 1;
    }

    .dashboard-badge--success {
        background: rgba(22, 163, 74, 0.12);
        color: #15803d;
    }

    .dashboard-badge--muted {
        background: rgba(71, 85, 105, 0.12);
        color: #475569;
    }

    .dashboard-ghost-btn {
        border-radius: 14px;
        border: 1px solid rgba(35, 44, 58, 0.12);
        background: rgba(35, 44, 58, 0.04);
        color: #232c3a;
        font-size: 0.82rem;
        font-weight: 600;
        padding: 0.5rem 0.8rem;
    }

    .dashboard-ghost-btn:hover,
    .dashboard-ghost-btn:focus {
        background: #232c3a;
        border-color: #232c3a;
        color: #fff;
    }

    .dashboard-table {
        --bs-table-bg: transparent;
        --bs-table-border-color: rgba(35, 44, 58, 0.08);
        font-size: 0.88rem;
    }

    .dashboard-table thead th {
        color: #667085;
        font-size: 0.74rem;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        border-top: 0;
        padding-top: 0;
    }

    .dashboard-table tbody td {
        color: #232c3a;
    }

    .dashboard-empty-state {
        color: #667085;
        font-size: 0.9rem;
    }

    @media (max-width: 767.98px) {
        .dashboard-panel {
            padding: 1.1rem;
        }

        .dashboard-panel__header,
        .dashboard-metric-row {
            flex-direction: column;
            align-items: flex-start;
        }
    }
</style>
@endpush
