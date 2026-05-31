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
        <div class="col-12 col-xl-8">
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
                    <p>{{ $report->description }}</p>
                </div>

                <div class="report-detail-block">
                    <h4>Location</h4>
                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <span class="report-detail-label">Name</span>
                            <div class="fw-semibold">{{ $report->location_name ?: 'Unknown location' }}</div>
                        </div>
                        <div class="col-6 col-md-4">
                            <span class="report-detail-label">Latitude</span>
                            <div class="fw-semibold">{{ number_format((float) $report->latitude, 7) }}</div>
                        </div>
                        <div class="col-6 col-md-4">
                            <span class="report-detail-label">Longitude</span>
                            <div class="fw-semibold">{{ number_format((float) $report->longitude, 7) }}</div>
                        </div>
                    </div>
                    <a
                        class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-2 mt-3"
                        href="https://www.google.com/maps?q={{ $report->latitude }},{{ $report->longitude }}"
                        target="_blank"
                        rel="noopener"
                    >
                        <i class="bi bi-map" aria-hidden="true"></i>
                        <span>Open map</span>
                    </a>
                </div>
            </section>
        </div>

        <div class="col-12 col-xl-4">
            <section class="report-detail-panel h-100">
                <div class="report-detail-header">
                    <div>
                        <span class="report-detail-eyebrow">Officer Action</span>
                        <h3>Review status</h3>
                    </div>
                </div>

                <form method="POST" action="{{ route('officer.reports.update', $report) }}" class="d-grid gap-3">
                    @csrf
                    @method('PUT')

                    <div>
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

                    <div>
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

                    <div>
                        <label for="officer_notes" class="form-label">Officer notes</label>
                        <textarea id="officer_notes" name="officer_notes" class="form-control @error('officer_notes') is-invalid @enderror" rows="6" placeholder="Add verification notes or action taken.">{{ old('officer_notes', $report->officer_notes) }}</textarea>
                        @error('officer_notes')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <button type="submit" class="btn btn-primary d-inline-flex align-items-center justify-content-center gap-2">
                        <i class="bi bi-save" aria-hidden="true"></i>
                        <span>Save review</span>
                    </button>
                </form>
            </section>
        </div>

        <div class="col-12">
            <section class="report-detail-panel">
                <div class="report-detail-header">
                    <div>
                        <span class="report-detail-eyebrow">Rule Matching</span>
                        <h3>Matched rules</h3>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table report-rule-table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Rule</th>
                                <th>Segment</th>
                                <th>Type</th>
                                <th>Value</th>
                                <th>Confidence</th>
                                <th>Match</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($report->ruleViolations as $ruleViolation)
                                <tr>
                                    <td>{{ $ruleViolation->rule_name_snapshot ?: ($ruleViolation->rule?->rule_name ?? 'Rule removed') }}</td>
                                    <td>{{ $ruleViolation->segment?->segment_name ?? 'Not linked' }}</td>
                                    <td>{{ $statusLabel($ruleViolation->rule_type_snapshot ?: $ruleViolation->rule?->rule_type) }}</td>
                                    <td>{{ $ruleViolation->rule_value_snapshot ?: ($ruleViolation->rule?->rule_value ?: 'N/A') }}</td>
                                    <td>{{ $ruleViolation->confidence_score ? number_format((float) $ruleViolation->confidence_score, 2) . '%' : 'N/A' }}</td>
                                    <td>
                                        <span class="report-badge report-badge--{{ $ruleViolation->matched_automatically ? 'info' : 'muted' }}">
                                            {{ $ruleViolation->matched_automatically ? 'Automatic' : 'Manual' }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">No rule match has been attached to this report.</td>
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
    .officer-report-detail-page {
        color: #232c3a;
    }

    .report-detail-panel {
        background: #fff;
        border: 1px solid rgba(35, 44, 58, 0.1);
        border-radius: 20px;
        padding: 1.25rem;
        box-shadow: 0 16px 36px rgba(27, 34, 48, 0.07);
    }

    .report-detail-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .report-detail-eyebrow,
    .report-detail-label {
        display: block;
        color: #667085;
        font-size: 0.72rem;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        font-weight: 700;
    }

    .report-detail-header h3,
    .report-detail-block h4 {
        margin: 0.2rem 0 0;
        color: #232c3a;
    }

    .report-detail-header h3 {
        font-size: 1.1rem;
    }

    .report-detail-block h4 {
        font-size: 0.98rem;
        margin-bottom: 0.45rem;
    }

    .report-detail-block p {
        margin: 0;
        color: #475569;
    }

    .report-detail-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 0.85rem;
    }

    .report-detail-grid > div {
        border: 1px solid rgba(35, 44, 58, 0.08);
        border-radius: 14px;
        padding: 0.9rem;
        background: rgba(35, 44, 58, 0.03);
    }

    .report-detail-grid span {
        display: block;
        color: #667085;
        font-size: 0.75rem;
        font-weight: 700;
    }

    .report-detail-grid strong {
        display: block;
        margin-top: 0.25rem;
        color: #232c3a;
        font-size: 0.92rem;
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

    .report-rule-table {
        --bs-table-bg: transparent;
        --bs-table-border-color: rgba(35, 44, 58, 0.08);
        font-size: 0.88rem;
    }

    .report-rule-table thead th {
        color: #667085;
        font-size: 0.72rem;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        white-space: nowrap;
    }

    @media (max-width: 991.98px) {
        .report-detail-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 575.98px) {
        .report-detail-header {
            flex-direction: column;
        }

        .report-detail-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
@endpush
