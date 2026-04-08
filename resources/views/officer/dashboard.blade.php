@extends('layouts.officerDashboardLayout')

@section('content')
@php
    $statCards = [
        ['label' => 'Submitted Reports', 'key' => 'submittedReports', 'icon' => 'bi-clipboard-data', 'tone' => 'tone-1'],
        ['label' => 'Active Officers', 'key' => 'activeOfficers', 'icon' => 'bi-person-badge', 'tone' => 'tone-3'],
        ['label' => 'Active Rules', 'key' => 'activeRules', 'icon' => 'bi-check-circle', 'tone' => 'tone-1'],
        ['label' => 'Hotspots', 'key' => 'hotspots', 'icon' => 'bi-geo-alt', 'tone' => 'tone-4'],
    ];
@endphp
@push('styles')
<link rel="stylesheet" href="{{ asset('css/rsrsOfficerDashboard.css') }}">
@endpush

<div class="container-fluid academic-dashboard-page px-3 px-lg-4 py-4" data-endpoint="{{ url('/road-officer/dashboard/data') }}">
    <div class="academic-dashboard-alert" id="academicDashboardAlert">
        Dashboard data failed to load. Please refresh and try again.
    </div>

    <section class="academic-dashboard-stats">
        @foreach ($statCards as $card)
            <article class="academic-stat-card {{ $card['tone'] }}">
                <div class="academic-stat-icon">
                    <i class="bi {{ $card['icon'] }}"></i>
                </div>
                <div class="academic-stat-content">
                    <div class="academic-stat-label">{{ $card['label'] }}</div>
                    <div class="academic-stat-value" data-stat="{{ $card['key'] }}">--</div>
                </div>
            </article>
        @endforeach
    </section>

    <section class="academic-dashboard-panels">
        <article class="academic-dashboard-panel">
            <div class="academic-panel-head">
                <div>
                    <div class="academic-panel-kicker">
                        <i class="bi bi-graph-up-arrow"></i>
                        Reporting trend
                    </div>
                    <h2 class="academic-panel-title">Reports submitted over time</h2>
                    <p class="academic-panel-description">
                        Follow how many road safety reports are being submitted across reporting periods.
                    </p>
                </div>
                <span class="academic-panel-badge">Reports</span>
            </div>
            <div class="academic-panel-body compact">
                <canvas id="olevelPerformanceTrendChart" height="150"></canvas>
                <div class="academic-chart-empty" id="olevelPerformanceTrendEmpty">No report trend data yet.</div>
            </div>
        </article>

        <article class="academic-dashboard-panel">
            <div class="academic-panel-head">
                <div>
                    <div class="academic-panel-kicker">
                        <i class="bi bi-graph-up-arrow"></i>
                        Field activity
                    </div>
                    <h2 class="academic-panel-title">Officer response activity</h2>
                    <p class="academic-panel-description">
                        Compare how officers are responding to incoming cases and review workloads.
                    </p>
                </div>
                <span class="academic-panel-badge">Officers</span>
            </div>
            <div class="academic-panel-body compact">
                <canvas id="alevelPerformanceTrendChart" height="150"></canvas>
                <div class="academic-chart-empty" id="alevelPerformanceTrendEmpty">No officer activity data yet.</div>
            </div>
        </article>

        <article class="academic-dashboard-panel">
            <div class="academic-panel-head">
                <div>
                    <div class="academic-panel-kicker">
                        <i class="bi bi-journal-richtext"></i>
                        Violation snapshot
                    </div>
                    <h2 class="academic-panel-title">Most reported violation types</h2>
                    <p class="academic-panel-description">
                        Quick comparison of violation types receiving the most report activity.
                    </p>
                </div>
                <span class="academic-panel-badge">Violations</span>
            </div>
            <div class="academic-panel-body compact">
                <canvas id="olevelSubjectSnapshotChart" height="150"></canvas>
                <div class="academic-chart-empty" id="olevelSubjectSnapshotEmpty">No violation type data yet.</div>
            </div>
        </article>

        <article class="academic-dashboard-panel">
            <div class="academic-panel-head">
                <div>
                    <div class="academic-panel-kicker">
                        <i class="bi bi-journal-richtext"></i>
                        Rule snapshot
                    </div>
                    <h2 class="academic-panel-title">Most matched road rules</h2>
                    <p class="academic-panel-description">
                        Review which road rules are being matched most often during case processing.
                    </p>
                </div>
                <span class="academic-panel-badge">Rules</span>
            </div>
            <div class="academic-panel-body compact">
                <canvas id="alevelSubjectSnapshotChart" height="150"></canvas>
                <div class="academic-chart-empty" id="alevelSubjectSnapshotEmpty">No road rule data yet.</div>
            </div>
        </article>

        <article class="academic-dashboard-panel">
            <div class="academic-panel-head">
                <div>
                    <div class="academic-panel-kicker">
                        <i class="bi bi-percent"></i>
                        Location risk
                    </div>
                    <h2 class="academic-panel-title">Highest report segments</h2>
                    <p class="academic-panel-description">
                        Compare road segments with the highest concentration of submitted cases.
                    </p>
                </div>
                <span class="academic-panel-badge">Segments</span>
            </div>
            <div class="academic-panel-body compact">
                <canvas id="olevelClassPassRateChart" height="150"></canvas>
                <div class="academic-chart-empty" id="olevelClassPassRateEmpty">No road segment data yet.</div>
            </div>
        </article>

        <article class="academic-dashboard-panel">
            <div class="academic-panel-head">
                <div>
                    <div class="academic-panel-kicker">
                        <i class="bi bi-percent"></i>
                        Severity watch
                    </div>
                    <h2 class="academic-panel-title">Hotspot severity overview</h2>
                    <p class="academic-panel-description">
                        Track hotspot severity changes and recurring danger areas from one view.
                    </p>
                </div>
                <span class="academic-panel-badge">Hotspots</span>
            </div>
            <div class="academic-panel-body compact">
                <canvas id="alevelClassPassRateChart" height="150"></canvas>
                <div class="academic-chart-empty" id="alevelClassPassRateEmpty">No hotspot severity data yet.</div>
            </div>
        </article>
    </section>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="{{ asset('js/rsrsOfficerDashboard.js') }}"></script>
@endsection
