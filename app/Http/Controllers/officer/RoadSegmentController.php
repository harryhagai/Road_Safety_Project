<?php

namespace App\Http\Controllers\officer;

use App\Http\Controllers\Controller;
use App\Models\RoadRule;
use App\Models\RoadSegment;
use App\Models\SegmentType;
use App\Services\MapConfigService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Officer-facing controller responsible for RoadSegmentController actions inside the dashboard.
 */
class RoadSegmentController extends Controller
{
    /**
     * Prepare the data needed to render the listing page.
     */
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
            'segmentTypesWithRules' => SegmentType::query()
                ->where('is_active', true)
                ->with(['defaultRules' => function ($query) {
                    $query->select('id', 'segment_type_id', 'rule_name', 'rule_type', 'rule_value', 'description', 'is_active', 'sort_order')
                        ->orderBy('sort_order');
                }])
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    /**
     * Validate the request and persist a new record.
     */

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
            ? SegmentType::query()->with('defaultRules')->find($validated['segment_type_id'])
            : null;

        DB::transaction(function () use ($segmentName, $segmentType, $validated, $geometry, $request): void {
            $segment = RoadSegment::create([
                'segment_name' => $segmentName,
                'segment_type' => $segmentType?->name,
                'segment_type_id' => $segmentType?->id,
                'description' => $validated['description'] ?: null,
                'length_km' => $validated['length_km'] ?: null,
                'boundary_coordinates' => $geometry,
                'created_by' => $request->user()?->id,
            ]);

            if (! $segmentType || $segmentType->defaultRules->isEmpty()) {
                return;
            }

            $coordinates = data_get($geometry, 'geometry.coordinates', []);
            $first = is_array($coordinates) && count($coordinates) > 0 ? $coordinates[0] : null;
            $last = is_array($coordinates) && count($coordinates) > 1 ? $coordinates[count($coordinates) - 1] : null;
            $latStart = is_array($first) && isset($first[1]) ? (float) $first[1] : null;
            $lngStart = is_array($first) && isset($first[0]) ? (float) $first[0] : null;
            $latEnd = is_array($last) && isset($last[1]) ? (float) $last[1] : null;
            $lngEnd = is_array($last) && isset($last[0]) ? (float) $last[0] : null;

            foreach ($segmentType->defaultRules as $template) {
                RoadRule::create([
                    'rule_name' => $template->rule_name,
                    'rule_type' => $template->rule_type,
                    'rule_value' => $template->rule_value,
                    'description' => $template->description,
                    'location_name' => $segment->segment_name,
                    'latitude_start' => $latStart,
                    'longitude_start' => $lngStart,
                    'latitude_end' => $latEnd,
                    'longitude_end' => $lngEnd,
                    'is_active' => (bool) $template->is_active,
                    'segment_id' => $segment->id,
                    'created_by' => $request->user()?->id,
                ]);
            }
        });

        return redirect()
            ->route('officer.road-segments.index')
            ->with('success', 'Road segment saved successfully.');
    }

    /**
     * Update editable metadata for an existing road segment.
     */
    public function update(Request $request, RoadSegment $roadSegment): RedirectResponse
    {
        $validated = $request->validate([
            'segment_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('road_segments', 'segment_name')->ignore($roadSegment->id),
            ],
            'segment_type_id' => ['nullable', 'exists:segment_types,id'],
            'description' => ['nullable', 'string', 'max:2000'],
            'length_km' => ['nullable', 'numeric', 'min:0'],
        ]);

        $segmentType = ! empty($validated['segment_type_id'])
            ? SegmentType::query()->find($validated['segment_type_id'])
            : null;

        $roadSegment->update([
            'segment_name' => trim($validated['segment_name']),
            'segment_type_id' => $segmentType?->id,
            'segment_type' => $segmentType?->name,
            'description' => $validated['description'] ?: null,
            'length_km' => $validated['length_km'] ?: null,
        ]);

        return redirect()
            ->route('officer.road-rules.index')
            ->with('success', 'Road segment updated successfully.');
    }

    /**
     * Delete a road segment record.
     */
    public function destroy(RoadSegment $roadSegment): RedirectResponse
    {
        $roadSegment->delete();

        return redirect()
            ->route('officer.road-rules.index')
            ->with('success', 'Road segment deleted successfully.');
    }

    /**
     * Handle the generateUniqueSegmentName workflow for this class.
     */

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
