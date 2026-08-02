{{-- Officer violation analysis dashboard and PDF export entry point. --}}

@extends('layouts.officerDashboardLayout')

@php
    $statusLabel = fn (?string $status) => str($status ?: 'unknown')->replace('_', ' ')->title();
    $chartPayload = [
        'trend' => $movementTrend,
        'segments' => $topSegments,
    ];
@endphp

@section('page_header_actions')
    <a href="{{ route('officer.violation-analysis.pdf', request()->query()) }}" class="btn btn-outline-danger d-inline-flex align-items-center gap-2" data-no-spinner data-analysis-pdf-download>
        <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i>
        <span>Download PDF</span>
    </a>
@endsection

@section('content')
<div class="container-fluid px-3 px-lg-4 py-4 officer-analysis-page">
    <section class="officer-analysis-panel officer-analysis-panel--filters mb-4">
        <form method="GET" action="{{ route('officer.violation-analysis.index') }}" class="row g-3 align-items-end" data-analysis-filter-form>
            <div class="col-12 col-md-6 col-xl-2">
                <label class="form-label" for="date_from">From</label>
                <input type="date" class="form-control" id="date_from" name="date_from" value="{{ $filters['date_from'] ?? '' }}" data-analysis-auto-filter>
            </div>
            <div class="col-12 col-md-6 col-xl-2">
                <label class="form-label" for="date_to">To</label>
                <input type="date" class="form-control" id="date_to" name="date_to" value="{{ $filters['date_to'] ?? '' }}" data-analysis-auto-filter>
            </div>
            <div class="col-12 col-md-4 col-xl-2">
                <label class="form-label" for="status">Status</label>
                <select class="form-select" id="status" name="status" data-analysis-auto-filter>
                    <option value="">All statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $statusLabel($status) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-4 col-xl-3">
                <label class="form-label" for="violation_type_id">Violation type</label>
                <select class="form-select" id="violation_type_id" name="violation_type_id" data-analysis-auto-filter>
                    <option value="">All violation types</option>
                    @foreach ($violationTypes as $type)
                        <option value="{{ $type->id }}" @selected((string) ($filters['violation_type_id'] ?? '') === (string) $type->id)>{{ $type->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-4 col-xl-2">
                <label class="form-label" for="segment_id">Segment</label>
                <select class="form-select" id="segment_id" name="segment_id" data-analysis-auto-filter>
                    <option value="">All segments</option>
                    @foreach ($roadSegments as $segment)
                        <option value="{{ $segment->id }}" @selected((string) ($filters['segment_id'] ?? '') === (string) $segment->id)>{{ $segment->segment_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-4 col-xl-1 d-flex gap-2">
                <a href="{{ route('officer.violation-analysis.index') }}" class="btn btn-outline-secondary flex-fill d-inline-flex align-items-center justify-content-center gap-2">
                    <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
                    <span>Reset</span>
                </a>
            </div>
        </form>
    </section>

    <div class="officer-analysis-stats">
        <article class="officer-analysis-stat">
            <span class="officer-analysis-stat__icon"><i class="bi bi-clipboard-data" aria-hidden="true"></i></span>
            <span>Total reports</span>
            <strong>{{ number_format($summary['total']) }}</strong>
        </article>
        <article class="officer-analysis-stat">
            <span class="officer-analysis-stat__icon"><i class="bi bi-cpu" aria-hidden="true"></i></span>
            <span>Automatic</span>
            <strong>{{ number_format($summary['automatic']) }}</strong>
        </article>
        <article class="officer-analysis-stat">
            <span class="officer-analysis-stat__icon"><i class="bi bi-shield-check" aria-hidden="true"></i></span>
            <span>Verified / resolved</span>
            <strong>{{ number_format($summary['verification_rate'], 1) }}%</strong>
        </article>
        <article class="officer-analysis-stat">
            <span class="officer-analysis-stat__icon"><i class="bi bi-exclamation-octagon" aria-hidden="true"></i></span>
            <span>High priority</span>
            <strong>{{ number_format($summary['high_priority']) }}</strong>
        </article>
    </div>

    <div class="officer-analysis-grid">
        <section class="officer-analysis-panel officer-analysis-panel--double">
            <div class="officer-analysis-panel__head">
                <div>
                    <span>Violation movement</span>
                    <h3>Parking vs overspeeding</h3>
                </div>
                <strong>{{ number_format($summary['review_rate'], 1) }}% reviewed</strong>
            </div>
            <div class="officer-analysis-chart officer-analysis-chart--trend">
                @if ($dailyTrend->isNotEmpty())
                    <canvas id="violationTrendChart"></canvas>
                @else
                    <div class="officer-analysis-empty">No trend data for the selected filters.</div>
                @endif
            </div>
        </section>

        <section class="officer-analysis-panel officer-analysis-panel--compact">
            <div class="officer-analysis-panel__head">
                <div>
                    <span>Mapped pressure</span>
                    <h3>Top 5 segments</h3>
                </div>
            </div>
            <div class="officer-analysis-chart">
                @if ($topSegments->isNotEmpty())
                    <canvas id="violationSegmentsChart"></canvas>
                @else
                    <div class="officer-analysis-empty">No segment data available.</div>
                @endif
            </div>
        </section>
    </div>

    <section class="officer-analysis-panel mt-4">
        <div class="officer-analysis-panel__head">
            <div>
                <span>Latest cases</span>
                <h3>Recent matching reports</h3>
            </div>
            <span class="officer-analysis-generated">Generated {{ $generatedAt->format('d M Y, H:i') }}</span>
        </div>
        <div class="table-responsive">
            <table class="table officer-analysis-table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Violation</th>
                        <th>Segment</th>
                        <th>Reporter / vehicle</th>
                        <th>Status</th>
                        <th>Priority</th>
                        <th>Reported</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentReports as $report)
                        @php
                            $segmentName = $report->ruleViolations->pluck('segment.segment_name')->filter()->first();
                        @endphp
                        <tr>
                            <td>{{ $report->reference_no ?: 'Report #' . $report->id }}</td>
                            <td>{{ $report->violationType?->name ?? 'Unassigned' }}</td>
                            <td>{{ $segmentName ?: ($report->location_name ?: 'N/A') }}</td>
                            <td>{{ $report->driver?->name ?? $report->bus_operator ?? $report->reporter_type ?? 'N/A' }}</td>
                            <td><span class="officer-analysis-badge">{{ $statusLabel($report->status) }}</span></td>
                            <td>{{ $statusLabel($report->priority) }}</td>
                            <td>{{ optional($report->reported_at)->format('d M Y, H:i') ?? optional($report->created_at)->format('d M Y, H:i') ?? 'N/A' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No reports match the selected filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/rsrsViolationAnalysis.css') }}?v={{ filemtime(public_path('css/rsrsViolationAnalysis.css')) }}">
@endpush

@push('scripts')
    <script>
        window.rsrsViolationAnalysis = @json($chartPayload);
    </script>
    <script>
        (() => {
            const form = document.querySelector('[data-analysis-filter-form]');
            if (!form) return;

            form.querySelectorAll('[data-analysis-auto-filter]').forEach((field) => {
                field.addEventListener('change', () => {
                    form.requestSubmit();
                });
            });
        })();
    </script>
    <script>
        (() => {
            const link = document.querySelector('[data-analysis-pdf-download]');
            if (!link) return;

            const originalHtml = link.innerHTML;

            const setLoading = (loading) => {
                link.classList.toggle('disabled', loading);
                link.setAttribute('aria-busy', loading ? 'true' : 'false');

                if (!loading) {
                    link.innerHTML = originalHtml;
                    return;
                }

                link.innerHTML = `
                    <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                    <span>Downloading...</span>
                `;
            };

            const filenameFromResponse = (response) => {
                const disposition = response.headers.get('Content-Disposition') || '';
                const utfMatch = disposition.match(/filename\*=UTF-8''([^;]+)/i);
                const quotedMatch = disposition.match(/filename="?([^"]+)"?/i);

                if (utfMatch?.[1]) {
                    return decodeURIComponent(utfMatch[1]);
                }

                return quotedMatch?.[1] || 'rsrs-violation-analysis.pdf';
            };

            link.addEventListener('click', async (event) => {
                event.preventDefault();

                if (link.classList.contains('disabled')) {
                    return;
                }

                setLoading(true);

                try {
                    const response = await fetch(link.href, {
                        credentials: 'same-origin',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    if (!response.ok) {
                        throw new Error('PDF download failed.');
                    }

                    const blob = await response.blob();
                    const objectUrl = window.URL.createObjectURL(blob);
                    const downloadLink = document.createElement('a');
                    downloadLink.href = objectUrl;
                    downloadLink.download = filenameFromResponse(response);
                    document.body.appendChild(downloadLink);
                    downloadLink.click();
                    downloadLink.remove();
                    window.URL.revokeObjectURL(objectUrl);
                } catch (error) {
                    window.location.href = link.href;
                } finally {
                    setLoading(false);
                }
            });
        })();
    </script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script src="{{ asset('js/rsrsViolationAnalysis.js') }}?v={{ filemtime(public_path('js/rsrsViolationAnalysis.js')) }}"></script>
@endpush
