<?php

namespace App\Http\Controllers\officer;

use App\Http\Controllers\Controller;
use App\Models\Hotspot;
use App\Models\Report;
use App\Models\RoadSegment;
use App\Models\SegmentTypeRule;
use App\Models\ViolationType;
use App\Services\MapConfigService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Officer-facing controller responsible for OfficerDashboardController actions inside the dashboard.
 */
class OfficerDashboardController extends Controller
{
    /**
     * Prepare the data needed to render the listing page.
     */
    public function index(MapConfigService $mapConfigService): View
    {
        // Build top summary cards for the officer dashboard.
        $stats = [
            [
                'label' => 'Reports',
                'value' => Report::count(),
                'icon' => 'bi-clipboard-data',
            ],
            [
                'label' => 'Road Segments',
                'value' => RoadSegment::count(),
                'icon' => 'bi-signpost-split',
            ],
            [
                'label' => 'Segment Type Rules',
                'value' => SegmentTypeRule::count(),
                'icon' => 'bi-shield-check',
            ],
            [
                'label' => 'Violation Types',
                'value' => ViolationType::count(),
                'icon' => 'bi-exclamation-diamond',
            ],
        ];

        $reportStatuses = Report::query()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'label' => $this->humanize($row->status ?: 'unknown'),
                'value' => (int) $row->total,
            ]);

        // Pull only the last 5 auto-speed reports linked to speed-limit rules for analytics.
        $autoSpeedReports = Report::query()
            ->join('rule_violations', 'rule_violations.report_id', '=', 'reports.id')
            ->select('reports.id', 'reports.reference_no', 'reports.description', 'reports.reported_at', 'reports.location_name')
            ->distinct()
            ->whereNotNull('reports.description')
            ->where('reports.description', 'like', 'Automatic overspeeding report:%')
            ->where('rule_violations.rule_type_snapshot', 'speed_limit')
            ->orderByDesc('reports.id')
            ->limit(5)
            ->get();

        // Parse recorded speed and speed-limit values from the auto-report description text.
        $speedSamples = [];
        $fallbackSpeedSamples = [];
        $excludedSampleCount = 0;
        foreach ($autoSpeedReports as $speedReport) {
            $description = (string) $speedReport->description;
            if (!preg_match('/([\d.]+)\s*km\/h recorded against a\s*([\d.]+)\s*km\/h/i', $description, $matches)) {
                $excludedSampleCount++;
                continue;
            }

            $speed = (float) ($matches[1] ?? 0);
            $limit = (float) ($matches[2] ?? 0);
            $hasInvalidValues = !is_finite($speed) || !is_finite($limit);
            $baseSample = [
                'speed' => $speed,
                'limit' => $limit,
                'reference' => (string) ($speedReport->reference_no ?: 'Report #'.$speedReport->id),
                'location' => (string) ($speedReport->location_name ?: 'Unknown segment'),
                'reported_at' => $speedReport->reported_at ? Carbon::parse($speedReport->reported_at)->format('d M Y, H:i') : 'N/A',
            ];
            if (!$hasInvalidValues && $speed > 0 && $limit > 0) {
                $fallbackSpeedSamples[] = $baseSample;
            }

            $isOutlier = $speed < 5 || $speed > 240 || $limit < 20 || $limit > 160;
            if ($hasInvalidValues || $isOutlier) {
                $excludedSampleCount++;
                continue;
            }

            $speedSamples[] = $baseSample;
        }

        // If strict-quality filtering removes everything, fallback to parsed positive samples for visibility.
        if (count($speedSamples) === 0 && count($fallbackSpeedSamples) > 0) {
            $speedSamples = $fallbackSpeedSamples;
        }

        // Reverse so chart progresses from older -> newer inside the selected sample window.
        $sampleCount = count($speedSamples);
        $averageSpeed = $sampleCount > 0 ? round(array_sum(array_column($speedSamples, 'speed')) / $sampleCount, 1) : 0.0;
        $averageLimit = $sampleCount > 0 ? round(array_sum(array_column($speedSamples, 'limit')) / $sampleCount, 1) : 0.0;
        $peakSpeed = $sampleCount > 0 ? round(max(array_column($speedSamples, 'speed')), 1) : 0.0;
        $orderedSamples = array_values(array_reverse($speedSamples));
        $lineLabels = [];
        $lineSpeedValues = [];
        $lineLimitValues = [];
        $lineViolationTotals = [];
        $linePointMeta = [];
        $runningViolations = 0;
        foreach ($orderedSamples as $index => $sample) {
            $runningViolations++;
            $reference = (string) $sample['reference'];
            $referenceParts = explode('-', $reference);
            $lineLabels[] = count($referenceParts) ? end($referenceParts) : $reference;
            $lineSpeedValues[] = round((float) $sample['speed'], 1);
            $lineLimitValues[] = round((float) $sample['limit'], 1);
            $lineViolationTotals[] = $runningViolations;
            $linePointMeta[] = [
                'reference' => $sample['reference'],
                'location' => $sample['location'],
                'reported_at' => $sample['reported_at'],
            ];
        }

        // Frontend-ready payload for the line chart and header metric.
        $speedAnalytics = [
            'sample_count' => $sampleCount,
            'excluded_sample_count' => $excludedSampleCount,
            'average_speed' => $averageSpeed,
            'average_limit' => $averageLimit,
            'peak_speed' => $peakSpeed,
            'line_labels' => $lineLabels,
            'line_speed_values' => $lineSpeedValues,
            'line_limit_values' => $lineLimitValues,
            'line_violation_totals' => $lineViolationTotals,
            'line_point_meta' => $linePointMeta,
        ];

        // Latest table preview for submitted reports.
        $recentReports = Report::query()
            ->with('violationType:id,name')
            ->latest('id')
            ->limit(10)
            ->get();

        // Lightweight report cards for the attention panel.
        $attentionReports = Report::query()
            ->with('violationType:id,name')
            ->latest('id')
            ->limit(6)
            ->get();

        // Latest hotspot records used by map/list sections.
        $hotspots = Hotspot::query()
            ->with('rule:id,rule_name')
            ->latest('id')
            ->limit(5)
            ->get();

        $hotspotPayload = $hotspots->map(fn (Hotspot $hotspot): array => [
            'id' => $hotspot->id,
            'name' => $hotspot->name ?: 'Unnamed hotspot',
            'lat' => (float) $hotspot->latitude,
            'lng' => (float) $hotspot->longitude,
            'radius' => (float) ($hotspot->radius_meters ?: 100),
            'frequency' => (int) ($hotspot->frequency ?: 0),
            'severity' => $hotspot->severity ?: 'medium',
            'rule' => $hotspot->rule?->rule_name,
            'updated' => optional($hotspot->last_updated_at ?? $hotspot->updated_at)->format('d M Y, H:i'),
        ])->values();

        // Aggregate report pressure per segment to find areas needing attention.
        $segmentViolationSummary = Report::query()
            ->join('rule_violations', 'rule_violations.report_id', '=', 'reports.id')
            ->join('road_segments', 'road_segments.id', '=', 'rule_violations.segment_id')
            ->whereNotNull('reports.latitude')
            ->whereNotNull('reports.longitude')
            ->selectRaw('
                road_segments.id as segment_id,
                road_segments.segment_name as segment_name,
                COUNT(DISTINCT reports.id) as violations_count,
                AVG(reports.latitude) as center_lat,
                AVG(reports.longitude) as center_lng,
                MAX(COALESCE(reports.reported_at, reports.created_at)) as last_reported_at
            ')
            ->groupBy('road_segments.id', 'road_segments.segment_name')
            ->orderByDesc('violations_count')
            ->limit(12)
            ->get();

        // Convert segment aggregates into ranked attention cards with severity.
        $topViolationsCount = (int) ($segmentViolationSummary->max('violations_count') ?? 0);
        $attentionSegments = $segmentViolationSummary->map(function ($row) use ($topViolationsCount) {
            $count = (int) $row->violations_count;
            $priority = 'low';
            $lastReportedAt = $row->last_reported_at
                ? Carbon::parse($row->last_reported_at)->format('d M Y, H:i')
                : 'N/A';

            if ($topViolationsCount > 0) {
                $ratio = $count / $topViolationsCount;
                if ($ratio >= 0.75) {
                    $priority = 'high';
                } elseif ($ratio >= 0.45) {
                    $priority = 'medium';
                }
            }

            return [
                'segment_id' => (int) $row->segment_id,
                'segment_name' => $row->segment_name ?: 'Unnamed segment',
                'violations_count' => $count,
                'priority' => $priority,
                'center_lat' => (float) $row->center_lat,
                'center_lng' => (float) $row->center_lng,
                'last_reported_at' => $lastReportedAt,
            ];
        })->values();

        // Build lightweight map points from ranked segments.
        $attentionHotspotPayload = $attentionSegments->map(function (array $segment): array {
            return [
                'id' => $segment['segment_id'],
                'name' => $segment['segment_name'],
                'lat' => $segment['center_lat'],
                'lng' => $segment['center_lng'],
                'frequency' => $segment['violations_count'],
                'severity' => $segment['priority'],
                'updated' => $segment['last_reported_at'],
            ];
        })->values();

        // Centralized map defaults and tile settings.
        $mapConfig = $mapConfigService->forFrontend();

        // Return the fully prepared officer dashboard view model.
        return view('officer.dashboard', compact(
            'stats',
            'reportStatuses',
            'speedAnalytics',
            'recentReports',
            'attentionReports',
            'hotspots',
            'hotspotPayload',
            'attentionSegments',
            'attentionHotspotPayload',
            'mapConfig',
        ));
    }

    /**
     * Handle the humanize workflow for this class.
     */

    protected function humanize(?string $value): string
    {
        // Turn snake_case status keys into a readable dashboard label.
        return str($value ?: 'unknown')->replace('_', ' ')->title()->value();
    }
}
