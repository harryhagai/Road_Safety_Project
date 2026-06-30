@extends('layouts.app')

@section('title', 'Driver Dashboard - RSRS')

@php
    $statusLabel = fn (?string $status) => str($status ?: 'unknown')->replace('_', ' ')->title();
    $statusTone = fn (?string $status) => match ($status) {
        'verified', 'resolved' => 'success',
        'under_review' => 'warning',
        'rejected' => 'danger',
        default => 'primary',
    };
@endphp

@section('content')
    <div class="driver-dashboard container-xl px-3 py-4">
        @if (session('success'))
            <div class="alert alert-success driver-dashboard__alert" role="status">{{ session('success') }}</div>
        @endif

        <section class="driver-dashboard__hero">
            <div>
                <span class="driver-dashboard__eyebrow"><i class="bi bi-person-check"></i> Identified driver</span>
                <h1>Welcome, {{ $driver->name }}</h1>
                <p>Track your vehicle and review reports submitted under your driver account.</p>
                <div class="driver-dashboard__actions">
                    <a href="{{ route('home') }}" class="btn btn-dark">
                        <i class="bi bi-geo-alt-fill"></i> Open tracking map
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-dark">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </button>
                    </form>
                </div>
            </div>

            <div class="driver-dashboard__vehicle">
                <div><span>Driver ID</span><strong>#{{ $driver->id }}</strong></div>
                <div><span>Vehicle</span><strong>{{ $driver->vehicle_name }}</strong></div>
                <div><span>Plate number</span><strong>{{ $driver->plate_number }}</strong></div>
                <div><span>Organization</span><strong>{{ $driver->organization }}</strong></div>
            </div>
        </section>

        <section class="driver-dashboard__stats">
            <article><span>Total reports</span><strong>{{ number_format($summary['total']) }}</strong></article>
            <article><span>Submitted</span><strong>{{ number_format($summary['submitted']) }}</strong></article>
            <article><span>Under review</span><strong>{{ number_format($summary['under_review']) }}</strong></article>
            <article><span>Completed</span><strong>{{ number_format($summary['completed']) }}</strong></article>
        </section>

        <section class="driver-dashboard__reports">
            <div class="driver-dashboard__section-head">
                <div>
                    <span>My reports</span>
                    <h2>Reports linked to your driver ID</h2>
                </div>
                <span class="driver-dashboard__count">{{ number_format($reports->total()) }} report{{ $reports->total() === 1 ? '' : 's' }}</span>
            </div>

            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Violation</th>
                            <th>Location</th>
                            <th>Status</th>
                            <th>Priority</th>
                            <th>Reported</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($reports as $report)
                            @php
                                $automaticMatch = $report->ruleViolations->firstWhere('matched_automatically', true);
                                $firstRuleViolation = $automaticMatch ?: $report->ruleViolations->first();
                                $segmentName = $firstRuleViolation?->segment?->segment_name;
                            @endphp
                            <tr>
                                <td>
                                    <strong>{{ $report->reference_no }}</strong>
                                    <small>#{{ $report->id }}</small>
                                </td>
                                <td>{{ $report->violationType?->name ?? 'Unassigned' }}</td>
                                <td>
                                    <strong>{{ $segmentName ?: ($report->location_name ?: 'Unknown location') }}</strong>
                                    <small>{{ number_format((float) $report->latitude, 5) }}, {{ number_format((float) $report->longitude, 5) }}</small>
                                </td>
                                <td><span class="badge text-bg-{{ $statusTone($report->status) }}">{{ $statusLabel($report->status) }}</span></td>
                                <td>{{ $statusLabel($report->priority) }}</td>
                                <td>{{ optional($report->reported_at)->format('d M Y, H:i') ?? optional($report->created_at)->format('d M Y, H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="driver-dashboard__empty">
                                    <i class="bi bi-clipboard2-check"></i>
                                    <strong>No reports yet</strong>
                                    <span>Reports submitted while you are logged in will appear here.</span>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($reports->hasPages())
                <div class="driver-dashboard__pagination">{{ $reports->links() }}</div>
            @endif
        </section>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/rsrsDriverDashboard.css') }}?v={{ filemtime(public_path('css/rsrsDriverDashboard.css')) }}">
@endpush
