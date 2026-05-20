{{-- Officer module view for index within the road safety dashboard. --}}

@extends('layouts.officerDashboardLayout')

@php
    $statusLabel = fn (?string $status) => str($status ?: 'unknown')->replace('_', ' ')->title();
    $statusTone = fn (?string $status) => match ($status) {
        'verified', 'resolved' => 'success',
        'under_review' => 'warning',
        'rejected' => 'danger',
        default => 'muted',
    };
@endphp

@section('page_header_actions')
    <a href="{{ route('officer.dashboard') }}" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
        <i class="bi bi-speedometer2" aria-hidden="true"></i>
        <span>Dashboard</span>
    </a>
@endsection

@section('content')
<div class="container-fluid px-3 px-lg-4 py-4 officer-reports-page">
    @if (session('success'))
        <div class="alert alert-success d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
            <article class="report-stat">
                <i class="bi bi-clipboard-data report-stat__watermark" aria-hidden="true"></i>
                <span>Total reports</span>
                <strong>{{ number_format($summary['total']) }}</strong>
            </article>
        </div>
        <div class="col-6 col-xl-3">
            <article class="report-stat">
                <i class="bi bi-cpu report-stat__watermark" aria-hidden="true"></i>
                <span>Automatic</span>
                <strong>{{ number_format($summary['automatic']) }}</strong>
            </article>
        </div>
        <div class="col-6 col-xl-3">
            <article class="report-stat">
                <i class="bi bi-send-check report-stat__watermark" aria-hidden="true"></i>
                <span>Submitted</span>
                <strong>{{ number_format($summary['submitted']) }}</strong>
            </article>
        </div>
        <div class="col-6 col-xl-3">
            <article class="report-stat">
                <i class="bi bi-shield-check report-stat__watermark" aria-hidden="true"></i>
                <span>Verified</span>
                <strong>{{ number_format($summary['verified']) }}</strong>
            </article>
        </div>
    </div>

    <section class="report-panel mb-4">
        <form method="GET" action="{{ route('officer.reports.index') }}" class="row g-3 align-items-end">
            <div class="col-12 col-lg-4">
                <label class="form-label" for="search">Search</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
                    <input type="search" class="form-control" id="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Reference, segment, location">
                </div>
            </div>
            <div class="col-12 col-md-4 col-lg-2">
                <label class="form-label" for="status">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $statusLabel($status) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-4 col-lg-2">
                <label class="form-label" for="violation_type_id">Violation</label>
                <select class="form-select" id="violation_type_id" name="violation_type_id">
                    <option value="">All types</option>
                    @foreach ($violationTypes as $type)
                        <option value="{{ $type->id }}" @selected((string) ($filters['violation_type_id'] ?? '') === (string) $type->id)>{{ $type->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-4 col-lg-2">
                <label class="form-label" for="source">Source</label>
                <select class="form-select" id="source" name="source">
                    <option value="">All sources</option>
                    <option value="automatic" @selected(($filters['source'] ?? '') === 'automatic')>Automatic</option>
                    <option value="manual" @selected(($filters['source'] ?? '') === 'manual')>Manual</option>
                </select>
            </div>
            <div class="col-12 col-lg-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-fill d-inline-flex align-items-center justify-content-center gap-2">
                    <i class="bi bi-funnel" aria-hidden="true"></i>
                    <span>Filter</span>
                </button>
                <a href="{{ route('officer.reports.index') }}" class="btn btn-outline-secondary" title="Clear filters" aria-label="Clear filters">
                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                </a>
            </div>
        </form>
    </section>

    <section class="report-panel">
        <div class="report-panel__header">
            <div>
                <span class="report-panel__eyebrow">Officer Review</span>
                <h3>Submitted reports</h3>
            </div>
            <span class="report-count">{{ number_format($reports->total()) }} result{{ $reports->total() === 1 ? '' : 's' }}</span>
        </div>

        <div class="table-responsive">
            <table class="table report-table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Violation</th>
                        <th>Segment / Location</th>
                        <th>Source</th>
                        <th>Status</th>
                        <th>Priority</th>
                        <th>Reported</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($reports as $report)
                        @php
                            $automaticMatch = $report->ruleViolations->firstWhere('matched_automatically', true);
                            $firstRuleViolation = $automaticMatch ?: $report->ruleViolations->first();
                            $segmentName = $firstRuleViolation?->rule?->segment?->segment_name;
                        @endphp
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $report->reference_no }}</div>
                                <small class="text-muted">#{{ $report->id }}</small>
                            </td>
                            <td>{{ $report->violationType?->name ?? 'Unassigned' }}</td>
                            <td>
                                <div>{{ $segmentName ?: ($report->location_name ?: 'Unknown location') }}</div>
                                <small class="text-muted">{{ number_format((float) $report->latitude, 5) }}, {{ number_format((float) $report->longitude, 5) }}</small>
                            </td>
                            <td>
                                <span class="report-badge report-badge--{{ $automaticMatch ? 'info' : 'muted' }}">
                                    <i class="bi {{ $automaticMatch ? 'bi-cpu' : 'bi-person-lines-fill' }}" aria-hidden="true"></i>
                                    {{ $automaticMatch ? 'Automatic' : 'Manual' }}
                                </span>
                            </td>
                            <td>
                                <span class="report-badge report-badge--{{ $statusTone($report->status) }}">{{ $statusLabel($report->status) }}</span>
                            </td>
                            <td>{{ $statusLabel($report->priority) }}</td>
                            <td>{{ optional($report->reported_at)->format('d M Y, H:i') ?? optional($report->created_at)->format('d M Y, H:i') }}</td>
                            <td class="text-end">
                                <a href="{{ route('officer.reports.show', $report) }}" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-2">
                                    <i class="bi bi-eye" aria-hidden="true"></i>
                                    <span>Open</span>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-5">No reports match the current filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">
            {{ $reports->links() }}
        </div>
    </section>
</div>
@endsection

@push('styles')
<style>
    .officer-reports-page {
        color: #232c3a;
    }

    .report-stat,
    .report-panel {
        background: #fff;
        border: 1px solid rgba(35, 44, 58, 0.1);
        box-shadow: 0 16px 36px rgba(27, 34, 48, 0.07);
    }

    .report-stat {
        min-height: 112px;
        border-radius: 18px;
        padding: 1rem;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        overflow: hidden;
        position: relative;
    }

    .report-stat span,
    .report-panel__eyebrow {
        display: block;
        color: #667085;
        font-size: 0.72rem;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        font-weight: 700;
    }

    .report-stat strong {
        color: #232c3a;
        font-size: 2rem;
        line-height: 1;
    }

    .report-stat span,
    .report-stat strong {
        position: relative;
        z-index: 1;
    }

    .report-stat__watermark {
        position: absolute;
        right: -0.45rem;
        bottom: -0.9rem;
        color: #232c3a;
        font-size: 5.6rem;
        line-height: 1;
        opacity: 0.055;
        pointer-events: none;
        transform: rotate(-8deg);
    }

    .report-panel {
        border-radius: 20px;
        padding: 1.2rem;
    }

    .report-panel__header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .report-panel__header h3 {
        margin: 0.2rem 0 0;
        font-size: 1.05rem;
    }

    .report-count {
        color: #667085;
        font-size: 0.88rem;
        font-weight: 700;
    }

    .report-table {
        --bs-table-bg: transparent;
        --bs-table-border-color: rgba(35, 44, 58, 0.08);
        font-size: 0.88rem;
    }

    .report-table thead th {
        color: #667085;
        font-size: 0.72rem;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .report-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        border-radius: 999px;
        padding: 0.34rem 0.65rem;
        font-size: 0.74rem;
        line-height: 1;
        font-weight: 700;
        white-space: nowrap;
    }

    .report-badge--info {
        background: rgba(30, 94, 255, 0.12);
        color: #1e40af;
    }

    .report-badge--success {
        background: rgba(22, 163, 74, 0.12);
        color: #15803d;
    }

    .report-badge--warning {
        background: rgba(245, 158, 11, 0.16);
        color: #92400e;
    }

    .report-badge--danger {
        background: rgba(220, 38, 38, 0.12);
        color: #b91c1c;
    }

    .report-badge--muted {
        background: rgba(71, 85, 105, 0.12);
        color: #475569;
    }

    @media (max-width: 767.98px) {
        .report-panel {
            padding: 1rem;
        }

        .report-panel__header {
            flex-direction: column;
        }
    }
</style>
@endpush
