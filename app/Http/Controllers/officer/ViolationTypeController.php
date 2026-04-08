<?php

namespace App\Http\Controllers\officer;

use App\Http\Controllers\Controller;
use App\Models\ViolationType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ViolationTypeController extends Controller
{
    public function index(): View
    {
        return view('officer.violation-types.index', [
            'violationTypes' => ViolationType::query()
                ->latest()
                ->get(),
        ]);
    }

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

    public function destroy(ViolationType $violationType): RedirectResponse
    {
        $violationType->delete();

        return redirect()
            ->route('officer.violation-types.index')
            ->with('success', 'Violation type deleted successfully.');
    }
}
