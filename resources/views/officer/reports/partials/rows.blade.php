@php
    $statusLabel = fn (?string $status) => str($status ?: 'unknown')->replace('_', ' ')->title();
    $statusTone = fn (?string $status) => match ($status) {
        'verified', 'resolved' => 'success',
        'under_review' => 'warning',
        'rejected' => 'danger',
        default => 'muted',
    };
@endphp

@forelse ($reports as $report)
    @php
        $automaticMatch = $report->ruleViolations->firstWhere('matched_automatically', true);
        $firstRuleViolation = $automaticMatch ?: $report->ruleViolations->first();
        $segmentName = $firstRuleViolation?->segment?->segment_name;
    @endphp
    <tr>
        <td>
            <div class="fw-semibold">{{ $report->reference_no }}</div>
            <small class="text-muted">#{{ $report->id }}</small>
        </td>
        <td>{{ $report->violationType?->name ?? 'Unassigned' }}</td>
        <td>
            @if ($report->reporter_type === 'passenger')
                <div class="fw-semibold">{{ $report->bus_operator ?: 'Passenger report' }}</div>
                <small class="text-muted">{{ $report->bus_plate_number ?: 'Plate not provided' }}</small>
            @elseif ($report->driver)
                <div class="fw-semibold">{{ $report->driver->name }}</div>
                <small class="text-muted">ID #{{ $report->driver->id }} · {{ $report->driver->plate_number }}</small>
            @else
                <span class="text-muted">Legacy / unidentified</span>
            @endif
        </td>
        <td>
            <div>{{ $segmentName ?: ($report->location_name ?: 'Unknown location') }}</div>
            <small class="text-muted">{{ number_format((float) $report->latitude, 5) }}, {{ number_format((float) $report->longitude, 5) }}</small>
        </td>
        <td>
            <span class="report-badge report-badge--{{ $automaticMatch ? 'info' : 'muted' }}">
                <i class="bi {{ $report->reporter_type === 'passenger' ? 'bi-person-walking' : ($automaticMatch ? 'bi-cpu' : 'bi-person-lines-fill') }}" aria-hidden="true"></i>
                {{ $report->reporter_type === 'passenger' ? 'Passenger' : ($automaticMatch ? 'Automatic' : 'Manual') }}
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
    @if ($showEmptyState ?? false)
        <tr data-empty-row="true">
            <td colspan="9" class="text-center text-muted py-5">No reports match the current filters.</td>
        </tr>
    @endif
@endforelse
