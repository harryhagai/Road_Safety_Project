<?php

namespace App\Http\Controllers\officer;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\ViolationType;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Officer-facing controller responsible for OfficerReportController actions inside the dashboard.
 */
class OfficerReportController extends Controller
{
    private const STATUSES = [
        'submitted',
        'under_review',
        'verified',
        'resolved',
        'rejected',
    ];

    /**
     * Prepare the data needed to render the listing page.
     */
    public function index(Request $request): View|JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'violation_type_id' => ['nullable', 'integer', 'exists:violation_types,id'],
            'source' => ['nullable', Rule::in(['automatic', 'manual', 'driver', 'passenger'])],
        ]);

        $reports = Report::query()
            ->with([
                'violationType:id,name',
                'driver:id,name,email,vehicle_name,plate_number,organization',
                'ruleViolations.segment:id,segment_name',
            ])
            ->when($validated['search'] ?? null, function ($query, string $search) {
                $like = '%'.trim($search).'%';

                $query->where(function ($innerQuery) use ($like) {
                    $innerQuery
                        ->where('reference_no', 'like', $like)
                        ->orWhere('location_name', 'like', $like)
                        ->orWhere('description', 'like', $like)
                        ->orWhere('bus_operator', 'like', $like)
                        ->orWhere('bus_plate_number', 'like', $like)
                        ->orWhere('bus_route', 'like', $like)
                        ->orWhereHas('driver', function ($driverQuery) use ($like) {
                            $driverQuery
                                ->where('name', 'like', $like)
                                ->orWhere('email', 'like', $like)
                                ->orWhere('vehicle_name', 'like', $like)
                                ->orWhere('plate_number', 'like', $like)
                                ->orWhere('organization', 'like', $like);
                        })
                        ->orWhereHas('violationType', fn ($typeQuery) => $typeQuery->where('name', 'like', $like))
                        ->orWhereHas('ruleViolations.segment', fn ($segmentQuery) => $segmentQuery->where('segment_name', 'like', $like))
                        ->orWhereHas('ruleViolations', function ($ruleViolationQuery) use ($like) {
                            $ruleViolationQuery
                                ->where('rule_name_snapshot', 'like', $like)
                                ->orWhere('rule_type_snapshot', 'like', $like)
                                ->orWhere('rule_value_snapshot', 'like', $like);
                        });
                });
            })
            ->when($validated['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($validated['violation_type_id'] ?? null, fn ($query, int $typeId) => $query->where('violation_type_id', $typeId))
            ->when(($validated['source'] ?? null) === 'automatic', fn ($query) => $query->whereHas('ruleViolations', fn ($ruleQuery) => $ruleQuery->where('matched_automatically', true)))
            ->when(($validated['source'] ?? null) === 'manual', fn ($query) => $query->whereDoesntHave('ruleViolations', fn ($ruleQuery) => $ruleQuery->where('matched_automatically', true)))
            ->when(($validated['source'] ?? null) === 'driver', fn ($query) => $query->where('reporter_type', 'driver'))
            ->when(($validated['source'] ?? null) === 'passenger', fn ($query) => $query->where('reporter_type', 'passenger'))
            ->latest('reported_at')
            ->latest('id')
            ->paginate(12)
            ->withQueryString();

        if ($request->boolean('lazy')) {
            return response()->json([
                'rows_html' => $reports->isEmpty()
                    ? ''
                    : view('officer.reports.partials.rows', [
                        'reports' => $reports,
                        'showEmptyState' => false,
                    ])->render(),
                'next_page_url' => $reports->nextPageUrl(),
                'has_more_pages' => $reports->hasMorePages(),
                'current_page' => $reports->currentPage(),
            ]);
        }

        $summary = [
            'total' => Report::count(),
            'automatic' => Report::whereHas('ruleViolations', fn ($query) => $query->where('matched_automatically', true))->count(),
            'submitted' => Report::where('status', 'submitted')->count(),
            'verified' => Report::where('status', 'verified')->count(),
        ];

        return view('officer.reports.index', [
            'reports' => $reports,
            'summary' => $summary,
            'statuses' => self::STATUSES,
            'violationTypes' => ViolationType::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'filters' => $validated,
        ]);
    }

    /**
     * Load and return the detailed view for the requested record.
     */
    public function show(Report $report): View
    {
        $report->load([
            'violationType:id,name,description',
            'driver:id,name,email,vehicle_name,plate_number,organization',
            'ruleViolations.segment:id,segment_name,segment_type_id,boundary_coordinates,length_km,description',
            'ruleViolations.segment.segmentType:id,name',
            'ruleViolations.rule:id,rule_name,rule_type,rule_value',
        ]);

        return view('officer.reports.show', [
            'report' => $report,
            'statuses' => self::STATUSES,
        ]);
    }

    /**
     * Apply validated changes to the selected record.
     */
    public function update(Request $request, Report $report): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(self::STATUSES)],
            'priority' => ['required', Rule::in(['normal', 'medium', 'high'])],
            'officer_notes' => ['nullable', 'string', 'max:3000'],
        ]);

        $report->update([
            'status' => $validated['status'],
            'priority' => $validated['priority'],
            'officer_notes' => $validated['officer_notes'] ?? null,
            'officer_id' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Report updated successfully.');
    }

    public static function labelStatus(string $status): string
    {
        return str($status)->replace('_', ' ')->title()->value();
    }
}
