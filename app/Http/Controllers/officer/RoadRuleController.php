<?php

namespace App\Http\Controllers\officer;

use App\Http\Controllers\Controller;
use App\Models\RoadRule;
use App\Models\RoadSegment;
use App\Services\MapConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RoadRuleController extends Controller
{
    public function index(MapConfigService $mapConfigService): View
    {
        $initialSegmentPage = $this->segmentResultsQuery()->paginate(10);
        $segments = collect($initialSegmentPage->items())->map(
            fn (RoadSegment $segment) => $this->formatSegment($segment)
        )->values();

        return view('officer.road-rules.index', [
            'mapConfig' => $mapConfigService->forFrontend(),
            'segments' => $segments,
            'rules' => collect(),
            'initialSegmentPagination' => [
                'current_page' => $initialSegmentPage->currentPage(),
                'last_page' => $initialSegmentPage->lastPage(),
                'per_page' => $initialSegmentPage->perPage(),
                'total' => $initialSegmentPage->total(),
                'has_more' => $initialSegmentPage->hasMorePages(),
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $segmentPage = $this->segmentResultsQuery($validated['search'] ?? null)->paginate(
            perPage: 10,
            columns: ['*'],
            pageName: 'page',
            page: (int) ($validated['page'] ?? 1)
        );

        return response()->json([
            'items' => collect($segmentPage->items())->map(
                fn (RoadSegment $segment) => $this->formatSegment($segment)
            )->values(),
            'meta' => [
                'current_page' => $segmentPage->currentPage(),
                'last_page' => $segmentPage->lastPage(),
                'per_page' => $segmentPage->perPage(),
                'total' => $segmentPage->total(),
                'has_more' => $segmentPage->hasMorePages(),
                'search' => $validated['search'] ?? '',
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'rule_type' => ['required', 'string', 'max:100'],
            'rule_value' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'location_name' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'segment_id' => ['required', 'exists:road_segments,id'],
        ]);

        $segment = RoadSegment::findOrFail($validated['segment_id']);
        $coordinates = data_get($segment->boundary_coordinates, 'geometry.coordinates', []);

        if (! is_array($coordinates) || count($coordinates) < 2) {
            return back()
                ->withInput()
                ->with('error', 'Selected segment does not have valid geometry.');
        }

        $start = $coordinates[0];
        $end = $coordinates[count($coordinates) - 1];
        $ruleTypeLabel = str($validated['rule_type'])->replace('_', ' ')->title()->value();
        $generatedRuleName = trim(implode(' - ', array_filter([
            $segment->segment_name,
            $ruleTypeLabel,
            $validated['rule_value'] ?: null,
        ])));

        RoadRule::create([
            'rule_name' => $generatedRuleName,
            'rule_type' => $validated['rule_type'],
            'rule_value' => $validated['rule_value'] ?: null,
            'description' => $validated['description'] ?: null,
            'location_name' => $validated['location_name'] ?: $segment->segment_name,
            'effective_from' => null,
            'effective_to' => null,
            'is_active' => $request->boolean('is_active', true),
            'segment_id' => $segment->id,
            'created_by' => $request->user()?->id,
            'latitude_start' => $start[1] ?? null,
            'longitude_start' => $start[0] ?? null,
            'latitude_end' => $end[1] ?? null,
            'longitude_end' => $end[0] ?? null,
        ]);

        return redirect()
            ->route('officer.road-rules.index')
            ->with('success', 'Road rule saved successfully.');
    }

    protected function segmentResultsQuery(?string $search = null)
    {
        return RoadSegment::query()
            ->with([
                'segmentType:id,name',
                'roadRules' => function ($query) {
                    $query->latest()->select([
                        'id',
                        'segment_id',
                        'rule_name',
                        'rule_type',
                        'rule_value',
                        'location_name',
                        'description',
                        'effective_from',
                        'effective_to',
                        'is_active',
                    ]);
                },
            ])
            ->when($search, function ($query, $searchTerm) {
                $like = '%' . trim($searchTerm) . '%';

                $query->where(function ($innerQuery) use ($like) {
                    $innerQuery
                        ->where('segment_name', 'like', $like)
                        ->orWhere('segment_type', 'like', $like)
                        ->orWhere('description', 'like', $like)
                        ->orWhereHas('segmentType', function ($segmentTypeQuery) use ($like) {
                            $segmentTypeQuery->where('name', 'like', $like);
                        })
                        ->orWhereHas('roadRules', function ($ruleQuery) use ($like) {
                            $ruleQuery
                                ->where('rule_name', 'like', $like)
                                ->orWhere('rule_type', 'like', $like)
                                ->orWhere('rule_value', 'like', $like)
                                ->orWhere('location_name', 'like', $like);
                        });
                });
            })
            ->withCount('roadRules')
            ->orderBy('segment_name');
    }

    protected function formatSegment(RoadSegment $segment): array
    {
        return [
            'id' => $segment->id,
            'segment_name' => $segment->segment_name,
            'segment_type' => $segment->segment_type_name,
            'description' => $segment->description,
            'length_km' => $segment->length_km,
            'boundary_coordinates' => $segment->boundary_coordinates,
            'road_rules_count' => $segment->road_rules_count ?? $segment->roadRules->count(),
            'rules' => $segment->roadRules->map(function (RoadRule $rule) use ($segment) {
                return [
                    'id' => $rule->id,
                    'rule_name' => $rule->rule_name,
                    'rule_type' => $rule->rule_type,
                    'rule_value' => $rule->rule_value,
                    'description' => $rule->description,
                    'location_name' => $rule->location_name,
                    'effective_from' => optional($rule->effective_from)?->format('d M Y H:i'),
                    'effective_to' => optional($rule->effective_to)?->format('d M Y H:i'),
                    'is_active' => $rule->is_active,
                    'segment_id' => $rule->segment_id,
                    'segment_name' => $segment->segment_name,
                ];
            })->values(),
        ];
    }
}
