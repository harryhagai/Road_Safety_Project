<?php

namespace App\Http\Controllers\officer;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Officer-facing controller responsible for OfficerProfileController actions inside the dashboard.
 */
class OfficerProfileController extends Controller
{
    /**
     * Load and return the detailed view for the requested record.
     */
    public function show(Request $request): View
    {
        return view('officer.profile', [
            'officer' => $request->user(),
        ]);
    }

    /**
     * Apply validated changes to the selected record.
     */

    public function update(Request $request): RedirectResponse
    {
        $officer = $request->user();

        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('officers', 'email')->ignore($officer->id),
            ],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $officer->full_name = $validated['full_name'];
        $officer->email = $validated['email'];

        if (!empty($validated['password'])) {
            $officer->password = Hash::make($validated['password']);
        }

        $officer->save();

        return redirect()
            ->route('officer.profile.show')
            ->with('success', 'Your profile has been updated successfully.');
    }
}
