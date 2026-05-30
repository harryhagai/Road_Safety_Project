<?php

namespace App\Http\Controllers\officer;

use App\Http\Controllers\Controller;
use App\Models\RoadSegment;
use Illuminate\Contracts\View\View;

class OfficerTelemetryMonitoringController extends Controller
{
    public function index(): View
    {
        $segments = RoadSegment::query()
            ->select(['id', 'segment_name', 'boundary_coordinates'])
            ->whereNotNull('boundary_coordinates')
            ->get();

        return view('officer.telemetry-monitoring.index', [
            'segments' => $segments,
        ]);
    }
}
