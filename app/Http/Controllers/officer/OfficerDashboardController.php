<?php

namespace App\Http\Controllers\officer;

use App\Http\Controllers\Controller;
use App\Models\Hotspot;
use App\Models\Report;
use App\Models\RoadRule;
use App\Models\RoadSegment;
use App\Models\ViolationType;
use App\Services\MapConfigService;
use Illuminate\Contracts\View\View;
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
                'label' => 'Road Rules',
                'value' => RoadRule::count(),
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

        $recentReports = Report::query()
            ->with('violationType:id,name')
            ->latest('id')
            ->limit(5)
            ->get();

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
        $mapConfig = $mapConfigService->forFrontend();

        return view('officer.dashboard', compact(
            'stats',
            'reportStatuses',
            'recentReports',
            'hotspots',
            'hotspotPayload',
            'mapConfig',
        ));
    }

    /**
     * Handle the humanize workflow for this class.
     */

    protected function humanize(?string $value): string
    {
        return str($value ?: 'unknown')->replace('_', ' ')->title()->value();
    }
}
