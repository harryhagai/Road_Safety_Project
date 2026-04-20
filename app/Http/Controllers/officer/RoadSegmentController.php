<?php

namespace App\Http\Controllers\officer;

use App\Http\Controllers\Controller;
use App\Models\RoadSegment;
use App\Models\SegmentType;
use App\Services\MapConfigService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class RoadSegmentController extends Controller
{
    public function index(MapConfigService $mapConfigService): View
    {
        $segments = RoadSegment::query()
            ->with('segmentType:id,name')
            ->latest()
            ->get()
            ->map(function (RoadSegment $segment) {
                return [
                    'id' => $segment->id,
                    'segment_name' => $segment->segment_name,
                    'segment_type' => $segment->segment_type_name,
                    'description' => $segment->description,
                    'length_km' => $segment->length_km,
                    'boundary_coordinates' => $segment->boundary_coordinates,
                    'created_at' => optional($segment->created_at)?->format('d M Y, H:i'),
                ];
            });

        return view('officer.road-segments.index', [
            'mapConfig' => $mapConfigService->forFrontend(),
            'segments' => $segments,
            'segmentTypes' => SegmentType::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'segment_name' => ['required', 'string', 'max:255'],
            'segment_type_id' => ['nullable', 'exists:segment_types,id'],
            'description' => ['nullable', 'string', 'max:2000'],
            'length_km' => ['nullable', 'numeric', 'min:0'],
            'boundary_coordinates' => ['required', 'json'],
        ]);

        $geometry = json_decode($validated['boundary_coordinates'], true);

        if (! is_array($geometry)) {
            return back()
                ->withInput()
                ->with('error', 'Invalid road segment geometry payload.');
        }

        $coordinates = data_get($geometry, 'geometry.coordinates', []);

        if (! is_array($coordinates) || count($coordinates) < 2) {
            return back()
                ->withInput()
                ->with('error', 'A road segment needs at least two map points.');
        }

        $segmentName = $this->generateUniqueSegmentName($validated['segment_name']);
        $segmentType = ! empty($validated['segment_type_id'])
            ? SegmentType::query()->find($validated['segment_type_id'])
            : null;

        RoadSegment::create([
            'segment_name' => $segmentName,
            'segment_type' => $segmentType?->name,
            'segment_type_id' => $segmentType?->id,
            'description' => $validated['description'] ?: null,
            'length_km' => $validated['length_km'] ?: null,
            'boundary_coordinates' => $geometry,
            'created_by' => $request->user()?->id,
        ]);

        return redirect()
            ->route('officer.road-segments.index')
            ->with('success', 'Road segment saved successfully.');
    }

    private function generateUniqueSegmentName(string $candidate): string
    {
        $baseName = trim($candidate);

        if (! RoadSegment::query()->where('segment_name', $baseName)->exists()) {
            return $baseName;
        }

        $suffix = 2;

        do {
            $nextCandidate = sprintf('%s %s', $baseName, Str::of($suffix)->prepend('(')->append(')'));
            $exists = RoadSegment::query()->where('segment_name', $nextCandidate)->exists();
            $suffix++;
        } while ($exists);

        return $nextCandidate;
    }
}
