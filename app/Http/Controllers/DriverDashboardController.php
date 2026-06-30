<?php

namespace App\Http\Controllers;

use App\Models\Report;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DriverDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $driver = $request->user();

        $reportsQuery = Report::query()
            ->where('driver_id', $driver->id);

        $reports = (clone $reportsQuery)
            ->with([
                'violationType:id,name',
                'ruleViolations.segment:id,segment_name',
            ])
            ->latest('reported_at')
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        $summary = [
            'total' => (clone $reportsQuery)->count(),
            'submitted' => (clone $reportsQuery)->where('status', 'submitted')->count(),
            'under_review' => (clone $reportsQuery)->where('status', 'under_review')->count(),
            'completed' => (clone $reportsQuery)->whereIn('status', ['verified', 'resolved'])->count(),
        ];

        return view('driver.dashboard', [
            'driver' => $driver,
            'reports' => $reports,
            'summary' => $summary,
        ]);
    }
}
