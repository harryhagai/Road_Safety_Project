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
        $violations = Report::query()
            ->with('violationType:id,name')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where(function ($query) {
                $query
                    ->whereHas('violationType', fn ($typeQuery) => $typeQuery->where('name', 'Overspeeding'))
                    ->orWhere('description', 'like', 'Automatic overspeeding report:%');
            })
            ->latest('reported_at')
            ->get();

        $groupedPoints = $violations->groupBy(
            fn (Report $report): string => sprintf('%.4f,%.4f', (float) $report->latitude, (float) $report->longitude)
        );

        $averageViolations = $groupedPoints->isNotEmpty()
            ? (float) $groupedPoints->avg(fn ($items): int => $items->count())
            : 0.0;

        $violationPayload = $groupedPoints
            ->map(function ($items, string $coordinateKey) use ($averageViolations): array {
                [$lat, $lng] = array_map('floatval', explode(',', $coordinateKey));
                $count = $items->count();
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

                return [
                    'lat' => $lat,
                    'lng' => $lng,
                    'count' => $count,
                    'level' => $count > $averageViolations ? 'critical' : 'warning',
                    'location' => $latest?->location_name ?: 'Unknown location',
                    'lastReportedAt' => optional($latest?->reported_at ?? $latest?->created_at)->format('d M Y, H:i') ?: 'N/A',
                    'types' => $typeSummary,
                ];
            })
            ->sortByDesc('count')
            ->values();

        $criticalPoints = $violationPayload->where('level', 'critical')->count();
        $warningPoints = $violationPayload->where('level', 'warning')->count();

        return view('officer.hotspots.index', [
            'violations' => $violations,
            'violationPayload' => $violationPayload,
            'averageViolations' => round($averageViolations, 2),
            'criticalPoints' => $criticalPoints,
            'warningPoints' => $warningPoints,
            'mapConfig' => $mapConfigService->forFrontend(),
        ]);
    }
}
