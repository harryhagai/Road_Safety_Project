<?php

namespace App\Http\Controllers\officer;

use App\Http\Controllers\Controller;
use App\Models\EvidenceFile;
use App\Models\Hotspot;
use App\Models\Officer;
use App\Models\Report;
use App\Models\RoadRule;
use App\Models\RoadSegment;
use App\Models\RuleViolation;
use App\Models\ViolationType;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

class OfficerDashboardController extends Controller
{
    public function index(): View
    {
        $stats = [
            [
                'label' => 'Reports',
                'value' => Report::count(),
                'icon' => 'bi-clipboard-data',
                'tone' => 'blue',
            ],
            [
                'label' => 'Road Segments',
                'value' => RoadSegment::count(),
                'icon' => 'bi-signpost-split',
                'tone' => 'slate',
            ],
            [
                'label' => 'Road Rules',
                'value' => RoadRule::count(),
                'icon' => 'bi-shield-check',
                'tone' => 'teal',
            ],
            [
                'label' => 'Violation Types',
                'value' => ViolationType::count(),
                'icon' => 'bi-exclamation-diamond',
                'tone' => 'amber',
            ],
        ];

        $summaryTiles = [
            [
                'label' => 'Hotspots',
                'value' => Hotspot::count(),
                'icon' => 'bi-geo-alt',
            ],
            [
                'label' => 'Evidence Files',
                'value' => EvidenceFile::count(),
                'icon' => 'bi-paperclip',
            ],
            [
                'label' => 'Rule Matches',
                'value' => RuleViolation::count(),
                'icon' => 'bi-link-45deg',
            ],
            [
                'label' => 'Officers',
                'value' => Officer::count(),
                'icon' => 'bi-people',
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

        $ruleTypes = RoadRule::query()
            ->select('rule_type', DB::raw('count(*) as total'))
            ->groupBy('rule_type')
            ->orderByDesc('total')
            ->limit(6)
            ->get()
            ->map(fn ($row) => [
                'label' => $this->humanize($row->rule_type ?: 'general'),
                'value' => (int) $row->total,
            ]);

        $violationTypes = ViolationType::query()
            ->withCount('reports')
            ->orderByDesc('reports_count')
            ->limit(6)
            ->get()
            ->map(fn (ViolationType $type) => [
                'label' => $type->name,
                'value' => (int) $type->reports_count,
                'active' => $type->is_active,
            ]);

        $recentReports = Report::query()
            ->with('violationType:id,name')
            ->latest('id')
            ->limit(5)
            ->get();

        $recentSegments = RoadSegment::query()
            ->with('segmentType:id,name')
            ->withCount('roadRules')
            ->latest('id')
            ->limit(5)
            ->get();

        $recentRules = RoadRule::query()
            ->with('segment:id,segment_name')
            ->latest('id')
            ->limit(5)
            ->get();

        $hotspots = Hotspot::query()
            ->with('rule:id,rule_name')
            ->latest('id')
            ->limit(5)
            ->get();

        return view('officer.dashboard', compact(
            'stats',
            'summaryTiles',
            'reportStatuses',
            'ruleTypes',
            'violationTypes',
            'recentReports',
            'recentSegments',
            'recentRules',
            'hotspots',
        ));
    }

    protected function humanize(?string $value): string
    {
        return str($value ?: 'unknown')->replace('_', ' ')->title()->value();
    }
}
