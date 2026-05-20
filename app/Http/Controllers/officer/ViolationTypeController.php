<?php

namespace App\Http\Controllers\officer;

use App\Http\Controllers\Controller;
use App\Models\ViolationType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Officer-facing controller responsible for ViolationTypeController actions inside the dashboard.
 */
class ViolationTypeController extends Controller
{
    /**
     * Prepare the data needed to render the listing page.
     */
    public function index(): View
    {
        return view('officer.violation-types.index', [
            'violationTypes' => ViolationType::query()
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
            'name' => ['required', 'string', 'max:255', 'unique:violation_types,name'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        ViolationType::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?: null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()
            ->route('officer.violation-types.index')
            ->with('success', 'Violation type created successfully.');
    }

    /**
     * Apply validated changes to the selected record.
     */

    public function update(Request $request, ViolationType $violationType): RedirectResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('violation_types', 'name')->ignore($violationType->id),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $violationType->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?: null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('officer.violation-types.index')
            ->with('success', 'Violation type updated successfully.');
    }

    /**
     * Remove the selected record from storage.
     */

    public function destroy(ViolationType $violationType): RedirectResponse
    {
        $violationType->delete();

        return redirect()
            ->route('officer.violation-types.index')
            ->with('success', 'Violation type deleted successfully.');
    }
}
