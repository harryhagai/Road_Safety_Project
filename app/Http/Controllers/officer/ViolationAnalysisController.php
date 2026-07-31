<?php

namespace App\Http\Controllers\officer;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\ViolationType;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ViolationAnalysisController extends Controller
{
    private const STATUSES = [
        'submitted',
        'under_review',
        'verified',
        'resolved',
        'rejected',
    ];

    public function index(Request $request): View
    {
        $data = $this->analysisData($request);

        return view('officer.violation-analysis.index', $data);
    }

    private function analysisData(Request $request): array
    {
        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'violation_type_id' => ['nullable', 'integer', 'exists:violation_types,id'],
        ]);

        $baseQuery = $this->filteredReports($filters);
        $totalReports = (clone $baseQuery)->count();
        $automaticReports = (clone $baseQuery)
            ->whereHas('ruleViolations', fn (Builder $query) => $query->where('matched_automatically', true))
            ->count();
        $verifiedReports = (clone $baseQuery)->whereIn('status', ['verified', 'resolved'])->count();
        $highPriorityReports = (clone $baseQuery)->where('priority', 'high')->count();
        $reviewedReports = (clone $baseQuery)->whereNotNull('reviewed_at')->count();

        $summary = [
            'total' => $totalReports,
            'automatic' => $automaticReports,
            'manual' => max($totalReports - $automaticReports, 0),
            'automatic_ratio' => $totalReports > 0 ? round(($automaticReports / $totalReports) * 100, 1) : 0,
            'manual_ratio' => $totalReports > 0 ? round(((max($totalReports - $automaticReports, 0)) / $totalReports) * 100, 1) : 0,
            'verified' => $verifiedReports,
            'high_priority' => $highPriorityReports,
            'reviewed' => $reviewedReports,
            'verification_rate' => $totalReports > 0 ? round(($verifiedReports / $totalReports) * 100, 1) : 0,
            'review_rate' => $totalReports > 0 ? round(($reviewedReports / $totalReports) * 100, 1) : 0,
        ];

        $topSegments = (clone $baseQuery)
            ->join('rule_violations', 'rule_violations.report_id', '=', 'reports.id')
            ->join('road_segments', 'road_segments.id', '=', 'rule_violations.segment_id')
            ->whereNull('road_segments.deleted_at')
            ->select('road_segments.segment_name', DB::raw('COUNT(DISTINCT reports.id) as total'))
            ->groupBy('road_segments.id', 'road_segments.segment_name')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'label' => $row->segment_name ?: 'Unnamed segment',
                'value' => (int) $row->total,
            ]);

        $dailyTrendRows = (clone $baseQuery)
            ->leftJoin('violation_types', 'violation_types.id', '=', 'reports.violation_type_id')
            ->selectRaw("
                DATE(COALESCE(reports.reported_at, reports.created_at)) as report_date,
                SUM(CASE
                    WHEN LOWER(COALESCE(violation_types.name, '')) LIKE '%parking%'
                      OR LOWER(COALESCE(reports.description, '')) LIKE '%parking%'
                      OR EXISTS (
                        SELECT 1
                        FROM rule_violations rv
                        WHERE rv.report_id = reports.id
                          AND (
                            LOWER(COALESCE(rv.rule_name_snapshot, '')) LIKE '%parking%'
                            OR LOWER(COALESCE(rv.rule_type_snapshot, '')) LIKE '%parking%'
                            OR LOWER(COALESCE(rv.rule_description_snapshot, '')) LIKE '%parking%'
                          )
                      )
                    THEN 1 ELSE 0
                END) as parking_total,
                SUM(CASE
                    WHEN LOWER(COALESCE(violation_types.name, '')) LIKE '%speed%'
                      OR LOWER(COALESCE(reports.description, '')) LIKE '%speed%'
                      OR LOWER(COALESCE(reports.description, '')) LIKE '%overspeed%'
                      OR EXISTS (
                        SELECT 1
                        FROM rule_violations rv
                        WHERE rv.report_id = reports.id
                          AND (
                            LOWER(COALESCE(rv.rule_name_snapshot, '')) LIKE '%speed%'
                            OR LOWER(COALESCE(rv.rule_type_snapshot, '')) LIKE '%speed%'
                            OR LOWER(COALESCE(rv.rule_description_snapshot, '')) LIKE '%speed%'
                          )
                      )
                    THEN 1 ELSE 0
                END) as overspeeding_total
            ")
            ->groupBy('report_date')
            ->orderByDesc('report_date')
            ->limit(31)
            ->get()
            ->reverse()
            ->values();

        $dailyTrend = $dailyTrendRows->map(fn ($row) => [
            'label' => Carbon::parse($row->report_date)->format('d M'),
            'parking' => (int) $row->parking_total,
            'overspeeding' => (int) $row->overspeeding_total,
            'value' => (int) $row->parking_total + (int) $row->overspeeding_total,
        ]);

        $movementTrend = [
            'labels' => $dailyTrend->pluck('label')->values(),
            'parking_values' => $dailyTrend->pluck('parking')->values(),
            'overspeeding_values' => $dailyTrend->pluck('overspeeding')->values(),
        ];

        $recentReports = (clone $baseQuery)
            ->with([
                'violationType:id,name',
                'driver:id,name,plate_number,vehicle_name',
                'ruleViolations.segment:id,segment_name',
            ])
            ->latest('reported_at')
            ->latest('id')
            ->limit(12)
            ->get();

        return [
            'filters' => $filters,
            'statuses' => self::STATUSES,
            'violationTypes' => ViolationType::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'summary' => $summary,
            'topSegments' => $topSegments,
            'dailyTrend' => $dailyTrend,
            'movementTrend' => $movementTrend,
            'recentReports' => $recentReports,
            'generatedAt' => now(),
            'generatedBy' => $request->user()?->name ?? 'Road Safety Officer',
        ];
    }

    private function filteredReports(array $filters): Builder
    {
        return Report::query()
            ->when($filters['date_from'] ?? null, function (Builder $query, string $dateFrom) {
                $query->whereRaw('COALESCE(reports.reported_at, reports.created_at) >= ?', [
                    Carbon::parse($dateFrom)->startOfDay()->toDateTimeString(),
                ]);
            })
            ->when($filters['date_to'] ?? null, function (Builder $query, string $dateTo) {
                $query->whereRaw('COALESCE(reports.reported_at, reports.created_at) <= ?', [
                    Carbon::parse($dateTo)->endOfDay()->toDateTimeString(),
                ]);
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['violation_type_id'] ?? null, fn (Builder $query, int $typeId) => $query->where('violation_type_id', $typeId));
    }
}
