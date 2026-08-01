<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Services\MapConfigService;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Web controller that coordinates the PublicHotspotController request lifecycle.
 */
class PublicHotspotController extends Controller
{
    /**
     * Prepare the data needed to render the listing page.
     */
    public function index(MapConfigService $mapConfigService): View
    {
        $reports = Report::query()
            ->with('violationType:id,name')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->latest('id')
            ->limit(300)
            ->get();

        $hotspots = $reports
            ->groupBy(fn (Report $report): string => round((float) $report->latitude, 5).'|'.round((float) $report->longitude, 5))
            ->map(function ($items, string $key): object {
                [$latitude, $longitude] = array_map('floatval', explode('|', $key));
                $latest = $items->sortByDesc(fn (Report $report) => $report->reported_at ?? $report->created_at)->first();
                $frequency = $items->count();
                $violationType = $items
                    ->map(fn (Report $report) => $report->violationType?->name)
                    ->filter()
                    ->countBy()
                    ->sortDesc()
                    ->keys()
                    ->first();

                return (object) [
                    'id' => (int) $latest->id,
                    'name' => $latest->location_name ?: 'Reported road point',
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'radius_meters' => 100,
                    'frequency' => $frequency,
                    'severity' => $this->severityForFrequency($frequency),
                    'rule' => (object) ['rule_name' => $violationType ?: 'Reported violation'],
                    'last_updated_at' => $latest->reported_at ? Carbon::parse($latest->reported_at) : $latest->created_at,
                    'updated_at' => $latest->updated_at,
                ];
            })
            ->sortBy(fn (object $hotspot): int => match ($hotspot->severity) {
                'high' => 1,
                'medium' => 2,
                default => 3,
            })
            ->sortByDesc('frequency')
            ->values();

        return view('hotspots.index', [
            'hotspots' => $hotspots,
            'mapConfig' => $mapConfigService->forFrontend(),
            'hotspotPayload' => $hotspots->map(fn (object $hotspot): array => [
                'id' => $hotspot->id,
                'name' => $hotspot->name ?: 'Unnamed hotspot',
                'lat' => (float) $hotspot->latitude,
                'lng' => (float) $hotspot->longitude,
                'radius' => (float) $hotspot->radius_meters,
                'frequency' => (int) $hotspot->frequency,
                'severity' => $hotspot->severity ?: 'medium',
                'rule' => $hotspot->rule?->rule_name,
                'updated' => optional($hotspot->last_updated_at ?? $hotspot->updated_at)->format('d M Y, H:i'),
            ])->values(),
        ]);
    }

    private function severityForFrequency(int $frequency): string
    {
        if ($frequency >= 5) {
            return 'high';
        }

        if ($frequency >= 2) {
            return 'medium';
        }

        return 'low';
    }
}
