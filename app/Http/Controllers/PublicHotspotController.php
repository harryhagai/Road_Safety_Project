<?php

namespace App\Http\Controllers;

use App\Models\Hotspot;
use App\Services\MapConfigService;
use Illuminate\View\View;

class PublicHotspotController extends Controller
{
    public function index(MapConfigService $mapConfigService): View
    {
        $hotspots = Hotspot::query()
            ->with('rule:id,rule_name,rule_type')
            ->orderByRaw("CASE severity WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 WHEN 'low' THEN 4 ELSE 5 END")
            ->orderByDesc('frequency')
            ->latest('id')
            ->get();

        return view('hotspots.index', [
            'hotspots' => $hotspots,
            'mapConfig' => $mapConfigService->forFrontend(),
            'hotspotPayload' => $hotspots->map(fn (Hotspot $hotspot): array => [
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
}
