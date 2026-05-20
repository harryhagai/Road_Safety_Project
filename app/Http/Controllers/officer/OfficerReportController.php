<?php

namespace App\Http\Controllers\officer;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\ViolationType;
use Illuminate\Contracts\View\View;
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

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'violation_type_id' => ['nullable', 'integer', 'exists:violation_types,id'],
            'source' => ['nullable', Rule::in(['automatic', 'manual'])],
        ]);

        $reports = Report::query()
            ->with([
                'violationType:id,name',
                'ruleViolations.rule.segment:id,segment_name',
            ])
            ->when($validated['search'] ?? null, function ($query, string $search) {
                $like = '%'.trim($search).'%';

                $query->where(function ($innerQuery) use ($like) {
                    $innerQuery
                        ->where('reference_no', 'like', $like)
                        ->orWhere('location_name', 'like', $like)
                        ->orWhere('description', 'like', $like)
                        ->orWhereHas('violationType', fn ($typeQuery) => $typeQuery->where('name', 'like', $like))
                        ->orWhereHas('ruleViolations.rule.segment', fn ($segmentQuery) => $segmentQuery->where('segment_name', 'like', $like));
                });
            })
            ->when($validated['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($validated['violation_type_id'] ?? null, fn ($query, int $typeId) => $query->where('violation_type_id', $typeId))
            ->when(($validated['source'] ?? null) === 'automatic', fn ($query) => $query->whereHas('ruleViolations', fn ($ruleQuery) => $ruleQuery->where('matched_automatically', true)))
            ->when(($validated['source'] ?? null) === 'manual', fn ($query) => $query->whereDoesntHave('ruleViolations', fn ($ruleQuery) => $ruleQuery->where('matched_automatically', true)))
            ->latest('reported_at')
            ->latest('id')
            ->paginate(12)
            ->withQueryString();

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
            'ruleViolations.rule.segment:id,segment_name,segment_type,boundary_coordinates,length_km,description',
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
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Report updated successfully.');
    }

    public static function labelStatus(string $status): string
    {
        return str($status)->replace('_', ' ')->title()->value();
    }
}
