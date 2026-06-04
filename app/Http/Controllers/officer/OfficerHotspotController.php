<?php

namespace App\Http\Controllers\officer;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Services\MapConfigService;
use Illuminate\Contracts\View\View;

class OfficerHotspotController extends Controller
{
    public function index(MapConfigService $mapConfigService): View
    {
        $reports = Report::query()
            ->with([
                'officer:id,full_name',
                'ruleViolations.segment:id,segment_name',
                'violationType:id,name',
            ])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->latest('reported_at')
            ->latest('created_at')
            ->get();

        $groupedPoints = $reports->groupBy(
            fn (Report $report): string => sprintf('%.4f,%.4f', (float) $report->latitude, (float) $report->longitude)
        );

        $averageReports = $groupedPoints->isNotEmpty()
            ? (float) $groupedPoints->avg(fn ($items): int => $items->count())
            : 0.0;

        $reportPayload = $groupedPoints
            ->map(function ($items, string $coordinateKey): array {
                [$lat, $lng] = array_map('floatval', explode(',', $coordinateKey));
                $count = $items->count();
                $tone = $this->hotspotToneForReports($items);
                $latest = $items
                    ->sortByDesc(fn (Report $report) => $report->reported_at ?? $report->created_at)
                    ->first();
                $typeSummary = $items
                    ->pluck('violationType.name')
                    ->filter()
                    ->countBy()
                    ->sortDesc()
                    ->keys()
                    ->take(3)
                    ->values();
                $reportSummary = $items
                    ->sortByDesc(fn (Report $report) => $report->reported_at ?? $report->created_at)
                    ->map(fn (Report $report): array => [
                        'id' => $report->id,
                        'reference' => $report->reference_no ?: 'Report #'.$report->id,
                        'type' => $report->violationType?->name ?: 'Unassigned',
                        'status' => str($report->status ?: 'unknown')->replace('_', ' ')->title()->value(),
                        'priority' => str($report->priority ?: 'normal')->replace('_', ' ')->title()->value(),
                        'description' => $report->description ?: 'No description provided.',
                        'location' => $report->location_name ?: 'Unknown location',
                        'lat' => (float) $report->latitude,
                        'lng' => (float) $report->longitude,
                        'reportedAt' => optional($report->reported_at ?? $report->created_at)->format('d M Y, H:i') ?: 'N/A',
                        'createdAt' => optional($report->created_at)->format('d M Y, H:i') ?: 'N/A',
                        'reviewedAt' => optional($report->reviewed_at)->format('d M Y, H:i') ?: 'Not reviewed',
                        'officer' => $report->officer?->full_name ?: 'Unassigned',
                        'officerNotes' => $report->officer_notes ?: 'No officer notes yet.',
                        'rules' => $report->ruleViolations
                            ->map(fn ($ruleViolation): array => [
                                'name' => $ruleViolation->rule_name_snapshot ?: 'Unlinked rule',
                                'type' => str($ruleViolation->rule_type_snapshot ?: 'unknown')->replace('_', ' ')->title()->value(),
                                'value' => $ruleViolation->rule_value_snapshot ?: 'N/A',
                                'description' => $ruleViolation->rule_description_snapshot ?: 'No rule description.',
                                'segment' => $ruleViolation->segment?->segment_name ?: 'No segment linked',
                                'source' => $ruleViolation->matched_automatically ? 'Automatic' : 'Manual',
                                'confidence' => $ruleViolation->confidence_score !== null
                                    ? number_format((float) $ruleViolation->confidence_score, 2)
                                    : 'N/A',
                                'verifiedAt' => optional($ruleViolation->verified_at)->format('d M Y, H:i') ?: 'Not verified',
                            ])
                            ->values(),
                        'url' => route('officer.reports.show', $report),
                    ])
                    ->values();

                return [
                    'lat' => $lat,
                    'lng' => $lng,
                    'count' => $count,
                    'tone' => $tone,
                    'label' => $this->hotspotLabelForTone($tone),
                    'location' => $latest?->location_name ?: 'Unknown location',
                    'lastReportedAt' => optional($latest?->reported_at ?? $latest?->created_at)->format('d M Y, H:i') ?: 'N/A',
                    'types' => $typeSummary,
                    'reports' => $reportSummary,
                ];
            })
            ->sortByDesc('count')
            ->values();

        $dangerPoints = $reportPayload->where('tone', 'danger')->count();
        $warningPoints = $reportPayload->where('tone', 'warning')->count();

        return view('officer.hotspots.index', [
            'reports' => $reports,
            'reportPayload' => $reportPayload,
            'averageReports' => round($averageReports, 2),
            'dangerPoints' => $dangerPoints,
            'warningPoints' => $warningPoints,
            'mapConfig' => $mapConfigService->forFrontend(),
        ]);
    }

    private function hotspotToneForReports($reports): string
    {
        if ($reports->contains(fn (Report $report): bool => $this->isOverspeedingReport($report))) {
            return 'danger';
        }

        if ($reports->contains(fn (Report $report): bool => $this->isNoParkingReport($report))) {
            return 'warning';
        }

        return 'primary';
    }

    private function hotspotLabelForTone(string $tone): string
    {
        return match ($tone) {
            'danger' => 'Overspeeding reports',
            'warning' => 'No parking reports',
            default => 'Report point',
        };
    }

    private function isOverspeedingReport(Report $report): bool
    {
        $searchText = $this->reportSearchText($report);

        return str_contains($searchText, 'overspeed')
            || str_contains($searchText, 'over speed')
            || str_contains($searchText, 'speeding');
    }

    private function isNoParkingReport(Report $report): bool
    {
        $searchText = $this->reportSearchText($report);

        return str_contains($searchText, 'no parking')
            || str_contains($searchText, 'no-parking')
            || str_contains($searchText, 'parking prohibited')
            || str_contains($searchText, 'prohibited parking');
    }

    private function reportSearchText(Report $report): string
    {
        return strtolower(implode(' ', [
            $report->violationType?->name,
            $report->description,
        ]));
    }
}
