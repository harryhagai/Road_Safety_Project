<?php

namespace App\Http\Controllers\officer;

use App\Http\Controllers\Controller;
use App\Models\SegmentType;
use App\Models\SegmentTypeRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Officer-facing controller responsible for SegmentTypeController actions inside the dashboard.
 */
class SegmentTypeController extends Controller
{
    /**
     * Prepare the data needed to render the listing page.
     */
    public function index(): View
    {
        return view('officer.segment-types.index', [
            'segmentTypes' => SegmentType::query()
                ->withCount(['roadSegments', 'defaultRules'])
                ->with('defaultRules')
                ->latest()
                ->get(),
        ]);
    }

    /**
     * Validate the request and persist a new record.
     */

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:segment_types,name'],
            'description' => ['nullable', 'string', 'max:2000'],
            'speed_limit_kmh' => ['nullable', 'numeric', 'min:0', 'max:320'],
            'other_rules' => ['nullable', 'string', 'max:4000'],
        ]);

        DB::transaction(function () use ($validated, $request): void {
            $segmentType = SegmentType::create([
                'name' => $validated['name'],
                'slug' => $this->generateUniqueSlug($validated['name']),
                'description' => $validated['description'] ?: null,
                'is_active' => true,
            ]);

            $this->syncDefaultRules($segmentType, $validated);
        });

        return redirect()
            ->route('officer.segment-types.index')
            ->with('success', 'Segment type created successfully.');
    }

    /**
     * Apply validated changes to the selected record.
     */

    public function update(Request $request, SegmentType $segmentType): RedirectResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('segment_types', 'name')->ignore($segmentType->id),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'speed_limit_kmh' => ['nullable', 'numeric', 'min:0', 'max:320'],
            'other_rules' => ['nullable', 'string', 'max:4000'],
        ]);

        DB::transaction(function () use ($segmentType, $validated, $request): void {
            $segmentType->update([
                'name' => $validated['name'],
                'slug' => $segmentType->name === $validated['name']
                    ? $segmentType->slug
                    : $this->generateUniqueSlug($validated['name'], $segmentType->id),
                'description' => $validated['description'] ?: null,
                'is_active' => true,
            ]);

            $this->syncDefaultRules($segmentType, $validated);
        });

        return redirect()
            ->route('officer.segment-types.index')
            ->with('success', 'Segment type updated successfully.');
    }

    /**
     * Remove the selected record from storage.
     */

    public function destroy(SegmentType $segmentType): RedirectResponse
    {
        if ($segmentType->roadSegments()->exists()) {
            return redirect()
                ->route('officer.segment-types.index')
                ->with('error', 'This segment type is already used by saved road segments and cannot be deleted.');
        }

        $segmentType->delete();

        return redirect()
            ->route('officer.segment-types.index')
            ->with('success', 'Segment type deleted successfully.');
    }

    /**
     * Handle the generateUniqueSlug workflow for this class.
     */

    private function generateUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug !== '' ? $baseSlug : 'segment-type';
        $suffix = 2;

        while (
            SegmentType::query()
                ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = sprintf('%s-%d', $baseSlug !== '' ? $baseSlug : 'segment-type', $suffix);
            $suffix++;
        }

        return $slug;
    }

    private function syncDefaultRules(SegmentType $segmentType, array $validated): void
    {
        $rules = [];
        $sort = 1;

        $speedLimit = $validated['speed_limit_kmh'] ?? null;

        if ($speedLimit !== null && (float) $speedLimit > 0) {
            $speed = (float) $speedLimit;
            $rules[] = [
                'rule_name' => 'Speed limit',
                'rule_type' => 'speed_limit',
                'rule_value' => rtrim(rtrim(number_format($speed, 2, '.', ''), '0'), '.') . ' km/h',
                'description' => 'Maximum allowed speed for this segment type.',
                'is_active' => true,
                'sort_order' => $sort++,
            ];
        }

        $otherRules = collect(preg_split('/\r\n|\r|\n/', (string) ($validated['other_rules'] ?? '')))
            ->map(fn (string $line): string => trim($line))
            ->filter()
            ->values();

        foreach ($otherRules as $otherRule) {
            $rules[] = [
                'rule_name' => $otherRule,
                'rule_type' => 'other',
                'rule_value' => $otherRule,
                'description' => null,
                'is_active' => true,
                'sort_order' => $sort++,
            ];
        }

        $existingRules = $segmentType->defaultRules()
            ->get()
            ->keyBy(function (SegmentTypeRule $rule): string {
                return strtolower(sprintf('%s|%s', $rule->rule_type, trim($rule->rule_name)));
            });

        $seenRuleIds = [];

        foreach ($rules as $payload) {
            $key = strtolower(sprintf('%s|%s', $payload['rule_type'], trim((string) $payload['rule_name'])));
            $existing = $existingRules->get($key);

            if ($existing) {
                $existing->update($payload);
                $seenRuleIds[] = $existing->id;
                continue;
            }

            $created = $segmentType->defaultRules()->create($payload);
            $seenRuleIds[] = $created->id;
        }

        $segmentType->defaultRules()
            ->when($seenRuleIds !== [], fn ($query) => $query->whereNotIn('id', $seenRuleIds))
            ->delete();
    }
}
