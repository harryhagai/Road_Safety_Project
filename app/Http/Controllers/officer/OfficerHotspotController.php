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
            ->with('violationType:id,name')
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
                    ->take(5)
                    ->map(fn (Report $report): array => [
                        'reference' => $report->reference_no ?: 'Report #'.$report->id,
                        'type' => $report->violationType?->name ?: 'Unassigned',
                        'status' => str($report->status ?: 'unknown')->replace('_', ' ')->title()->value(),
                        'reportedAt' => optional($report->reported_at ?? $report->created_at)->format('d M Y, H:i') ?: 'N/A',
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
