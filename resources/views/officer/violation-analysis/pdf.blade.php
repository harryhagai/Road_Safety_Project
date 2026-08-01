@php
    $logoRelative = 'img/road-safety-favicon.svg';
    $logoPath = public_path($logoRelative);
    $logoSrc = asset($logoRelative);
    if (file_exists($logoPath)) {
        $logoContents = @file_get_contents($logoPath);
        if ($logoContents !== false) {
            $logoMime = @mime_content_type($logoPath) ?: 'image/svg+xml';
            $logoSrc = 'data:' . $logoMime . ';base64,' . base64_encode($logoContents);
        }
    }

    $periodText = (!empty($filters['date_from']) || !empty($filters['date_to']))
        ? (($filters['date_from'] ?? 'Start') . ' to ' . ($filters['date_to'] ?? 'Today'))
        : 'All available records';

    $statusLabel = static fn (?string $value) => str($value ?: 'unknown')->replace(['_', '-'], ' ')->title();
    $segmentMax = max((int) $topSegments->max('value'), 1);
@endphp

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>RSRS Violation Analysis PDF</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 18mm 12mm;
        }

        body {
            font-family: Cambria, Georgia, "Times New Roman", serif;
            font-size: 12px;
            color: #000;
            margin: 0;
            padding: 0;
            background: #fff;
        }

        .page {
            page-break-after: always;
            page-break-inside: avoid;
            padding: 10px 15px;
        }

        .page:last-child {
            page-break-after: auto;
        }

        .header {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
            margin-bottom: 10px;
        }

        .header-table,
        .summary-table,
        .analytics-table,
        .rank-table,
        .trend-table,
        .main-table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-table td {
            border: none;
            vertical-align: middle;
            padding: 0;
        }

        .logo {
            width: 65px;
            height: auto;
        }

        .system-title {
            text-align: center;
            font-size: 24px;
            font-weight: 900;
            text-transform: uppercase;
            margin: 8px 0 4px;
        }

        .report-title {
            text-align: center;
            font-size: 18px;
            font-weight: 700;
            text-transform: uppercase;
            margin: 6px 0;
        }

        .meta {
            text-align: center;
            font-size: 14px;
            margin: 4px 0;
        }

        th,
        td {
            border: 1px solid #000;
            padding: 6px 8px;
            text-align: center;
            vertical-align: middle;
            font-size: 12px;
        }

        thead th {
            font-weight: bold;
            text-transform: uppercase;
        }

        .section-title {
            margin: 18px 0 8px;
            font-size: 14px;
            font-weight: 800;
            border-bottom: 2px solid #000;
            padding-bottom: 3px;
            text-transform: uppercase;
        }

        .left-col {
            text-align: left;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .split {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }

        .split td {
            width: 50%;
            border: none;
            vertical-align: top;
            padding: 0 6px;
        }

        .bar-track {
            display: inline-block;
            width: 120px;
            height: 8px;
            border: 1px solid #000;
            vertical-align: middle;
            text-align: left;
        }

        .bar-fill {
            display: block;
            height: 8px;
            background: #000;
        }

        .badge {
            display: inline-block;
            min-width: 68px;
            padding: 2px 5px;
            border: 1px solid #000;
            font-size: 10px;
            text-transform: uppercase;
        }

        .footer {
            text-align: center;
            font-size: 12px;
            border-top: 1px solid #ccc;
            padding: 6px 0;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="header">
            <table class="header-table">
                <tr>
                    <td style="width: 70px;"><img src="{{ $logoSrc }}" alt="RSRS Logo" class="logo"></td>
                    <td>
                        <div class="system-title">Road Safety Reporting System</div>
                        <div class="report-title">Violation Analysis Report</div>
                        <div class="meta">
                            PERIOD: {{ $periodText }}
                            | GENERATED: {{ $generatedAt->format('d M Y, H:i') }}
                            | BY: {{ $generatedBy }}
                        </div>
                    </td>
                    <td style="width: 70px; text-align: right;"><img src="{{ $logoSrc }}" alt="RSRS Logo" class="logo"></td>
                </tr>
            </table>
        </div>

        <div class="section-title">Executive Summary</div>
        <table class="summary-table" style="width: 82%; margin: 0 auto 10px;">
            <thead>
                <tr>
                    <th>Total Reports</th>
                    <th>Automatic</th>
                    <th>Verified Rate</th>
                    <th>High Priority</th>
                    <th>Reviewed Rate</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ number_format($summary['total']) }}</td>
                    <td>{{ number_format($summary['automatic']) }} ({{ number_format($summary['automatic_ratio'], 1) }}%)</td>
                    <td>{{ number_format($summary['verification_rate'], 1) }}%</td>
                    <td>{{ number_format($summary['high_priority']) }}</td>
                    <td>{{ number_format($summary['review_rate'], 1) }}%</td>
                </tr>
            </tbody>
        </table>

        <div class="section-title">Top 5 Affected Segments</div>
        <table class="rank-table">
            <thead>
                <tr>
                    <th class="left-col">Segment / Location</th>
                    <th>Reports</th>
                    <th>Pressure</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($topSegments as $segment)
                    <tr>
                        <td class="left-col">{{ $segment['label'] }}</td>
                        <td>{{ number_format($segment['value']) }}</td>
                        <td><span class="bar-track"><span class="bar-fill" style="width: {{ round(($segment['value'] / $segmentMax) * 100) }}%;"></span></span></td>
                    </tr>
                @empty
                    <tr><td colspan="3">No segment data available.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="page">
        <div class="header">
            <table class="header-table">
                <tr>
                    <td style="width: 70px;"><img src="{{ $logoSrc }}" alt="RSRS Logo" class="logo"></td>
                    <td>
                        <div class="system-title">Road Safety Reporting System</div>
                        <div class="report-title">Recent Violation Reports</div>
                        <div class="meta">PERIOD: {{ $periodText }} | GENERATED: {{ $generatedAt->format('d M Y, H:i') }}</div>
                    </td>
                    <td style="width: 70px; text-align: right;"><img src="{{ $logoSrc }}" alt="RSRS Logo" class="logo"></td>
                </tr>
            </table>
        </div>

        <div class="section-title">Detailed Reports Table</div>
        <table class="main-table">
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Reference</th>
                    <th class="left-col">Violation Type</th>
                    <th class="left-col">Location / Segment</th>
                    <th>Status</th>
                    <th>Priority</th>
                    <th>Date & Time</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($recentReports as $report)
                    @php
                        $segmentName = $report->ruleViolations->pluck('segment.segment_name')->filter()->first();
                    @endphp
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $report->reference_no ?: 'Report #' . $report->id }}</td>
                        <td class="left-col">{{ $report->violationType?->name ?? 'Unassigned' }}</td>
                        <td class="left-col">{{ $segmentName ?: ($report->location_name ?: 'N/A') }}</td>
                        <td><span class="badge">{{ $statusLabel($report->status) }}</span></td>
                        <td><span class="badge">{{ $statusLabel($report->priority) }}</span></td>
                        <td>{{ optional($report->reported_at)->format('d M Y, H:i') ?? optional($report->created_at)->format('d M Y, H:i') ?? 'N/A' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7">No reports match the selected filters.</td></tr>
                @endforelse
            </tbody>
        </table>

        <div class="footer">
            Road Safety Reporting System | Internal analysis report | Generated automatically
        </div>
    </div>
</body>
</html>
