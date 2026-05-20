<?php

namespace App\Http\Controllers\officer;

use App\Http\Controllers\Controller;
use App\Models\SegmentType;
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
                ->withCount('roadSegments')
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
            'is_active' => ['nullable', 'boolean'],
        ]);

        SegmentType::create([
            'name' => $validated['name'],
            'slug' => $this->generateUniqueSlug($validated['name']),
            'description' => $validated['description'] ?: null,
            'is_active' => $request->boolean('is_active', true),
        ]);

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
            'is_active' => ['nullable', 'boolean'],
        ]);

        $segmentType->update([
            'name' => $validated['name'],
            'slug' => $segmentType->name === $validated['name']
                ? $segmentType->slug
                : $this->generateUniqueSlug($validated['name'], $segmentType->id),
            'description' => $validated['description'] ?: null,
            'is_active' => $request->boolean('is_active'),
        ]);

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
}
