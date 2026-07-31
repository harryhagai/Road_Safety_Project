<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Violation Analysis Report</title>
    @php
        $segmentMax = max((int) $topSegments->max('value'), 1);
        $trendMax = max(
            (int) $dailyTrend->max('parking'),
            (int) $dailyTrend->max('overspeeding'),
            1
        );
    @endphp
    <style>
        @page { margin: 24px 26px; }
        body { font-family: DejaVu Sans, sans-serif; color: #172033; font-size: 10.5px; line-height: 1.35; }
        h1, h2, h3 { margin: 0; color: #12345d; }
        h1 { font-size: 20px; }
        h2 { font-size: 13px; margin-bottom: 7px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border-bottom: 1px solid #d9e2ef; padding: 5px 5px; text-align: left; vertical-align: top; }
        th { background: #eef4ff; color: #34465f; font-size: 8.5px; text-transform: uppercase; }
        .meta { color: #5f6f85; margin-top: 5px; }
        .section { margin-top: 13px; page-break-inside: avoid; }
        .stats { width: 100%; margin-top: 12px; }
        .stat { width: 24%; display: inline-block; border: 1px solid #d9e2ef; padding: 7px; margin-right: 4px; box-sizing: border-box; border-radius: 4px; }
        .stat span { display: block; color: #5f6f85; font-size: 8px; text-transform: uppercase; }
        .stat strong { display: block; margin-top: 3px; font-size: 15px; color: #12345d; }
        .grid { width: 100%; }
        .col { width: 49%; display: inline-block; vertical-align: top; margin-right: 1%; }
        .pill { display: inline-block; padding: 3px 7px; border-radius: 12px; background: #eef4ff; color: #174ea6; }
        .chart-row { margin-bottom: 6px; }
        .chart-label { width: 34%; display: inline-block; color: #34465f; white-space: nowrap; overflow: hidden; }
        .chart-track { width: 52%; display: inline-block; height: 9px; background: #edf2f7; vertical-align: middle; }
        .chart-fill { height: 9px; background: #174ea6; }
        .chart-value { width: 10%; display: inline-block; text-align: right; color: #12345d; font-weight: bold; }
        .chart-fill--red { background: #b91c1c; }
        .trend-bars { height: 92px; border-left: 1px solid #d9e2ef; border-bottom: 1px solid #d9e2ef; padding: 0 4px; white-space: nowrap; }
        .trend-bar-wrap { display: inline-block; width: 20px; height: 86px; margin-right: 2px; vertical-align: bottom; position: relative; }
        .trend-bar { display: block; width: 6px; margin: 0 auto; position: absolute; bottom: 0; }
        .trend-bar--parking { background: #0f766e; left: 3px; }
        .trend-bar--speed { background: #b91c1c; left: 11px; }
        .trend-label { display: inline-block; width: 22px; font-size: 6.5px; color: #5f6f85; text-align: center; margin-top: 3px; }
        .legend { margin-top: 5px; color: #5f6f85; }
        .legend-item { display: inline-block; margin-right: 12px; }
        .legend-dot { display: inline-block; width: 8px; height: 8px; margin-right: 4px; }
        .legend-dot--parking { background: #0f766e; }
        .legend-dot--speed { background: #b91c1c; }
    </style>
</head>
<body>
    <h1>Violation Analysis Report</h1>
    <div class="meta">
        Generated {{ $generatedAt->format('d M Y, H:i') }}
        @if (!empty($filters['date_from']) || !empty($filters['date_to']))
            | Period: {{ $filters['date_from'] ?? 'Start' }} to {{ $filters['date_to'] ?? 'Today' }}
        @endif
    </div>

    <div class="stats">
        <div class="stat"><span>Total reports</span><strong>{{ number_format($summary['total']) }}</strong></div>
        <div class="stat"><span>Automatic</span><strong>{{ number_format($summary['automatic']) }}</strong></div>
        <div class="stat"><span>Verified rate</span><strong>{{ number_format($summary['verification_rate'], 1) }}%</strong></div>
        <div class="stat"><span>High priority</span><strong>{{ number_format($summary['high_priority']) }}</strong></div>
    </div>

    <div class="section">
        <h2>Parking vs Overspeeding Trend</h2>
        @if ($dailyTrend->isNotEmpty())
            <div class="trend-bars">
                @foreach ($dailyTrend as $item)
                    <span class="trend-bar-wrap">
                        <span class="trend-bar trend-bar--parking" style="height: {{ $item['parking'] > 0 ? max(4, round(($item['parking'] / $trendMax) * 86)) : 0 }}px;"></span>
                        <span class="trend-bar trend-bar--speed" style="height: {{ $item['overspeeding'] > 0 ? max(4, round(($item['overspeeding'] / $trendMax) * 86)) : 0 }}px;"></span>
                    </span>
                @endforeach
            </div>
            <div class="legend">
                <span class="legend-item"><span class="legend-dot legend-dot--parking"></span>Parking</span>
                <span class="legend-item"><span class="legend-dot legend-dot--speed"></span>Overspeeding</span>
            </div>
            <div>
                @foreach ($dailyTrend as $item)
                    <span class="trend-label">{{ $item['label'] }}</span>
                @endforeach
            </div>
        @else
            <p class="meta">No trend data for the selected filters.</p>
        @endif
    </div>

    <div class="section">
        <h2>Top 5 Segments</h2>
        @forelse ($topSegments as $item)
            <div class="chart-row">
                <span class="chart-label">{{ $item['label'] }}</span>
                <span class="chart-track"><span class="chart-fill chart-fill--red" style="width: {{ round(($item['value'] / $segmentMax) * 100) }}%;"></span></span>
                <span class="chart-value">{{ number_format($item['value']) }}</span>
            </div>
        @empty
            <p class="meta">No segment data available.</p>
        @endforelse
    </div>

    <div class="section">
        <h2>Recent Matching Reports</h2>
        <table>
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Violation</th>
                    <th>Status</th>
                    <th>Priority</th>
                    <th>Reported</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($recentReports as $report)
                    <tr>
                        <td>{{ $report->reference_no ?: 'Report #' . $report->id }}</td>
                        <td>{{ $report->violationType?->name ?? 'Unassigned' }}</td>
                        <td><span class="pill">{{ str($report->status ?: 'unknown')->replace('_', ' ')->title() }}</span></td>
                        <td>{{ str($report->priority ?: 'unknown')->replace('_', ' ')->title() }}</td>
                        <td>{{ optional($report->reported_at)->format('d M Y, H:i') ?? optional($report->created_at)->format('d M Y, H:i') ?? 'N/A' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5">No reports match the selected filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</body>
</html>
