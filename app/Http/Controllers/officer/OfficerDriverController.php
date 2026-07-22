<?php

namespace App\Http\Controllers\officer;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class OfficerDriverController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $status = $validated['status'] ?? null;

        $driversQuery = User::query()
            ->where('role', User::ROLE_DRIVER)
            ->withCount('reports')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($innerQuery) use ($search): void {
                    $innerQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('plate_number', 'like', "%{$search}%")
                        ->orWhere('vehicle_name', 'like', "%{$search}%")
                        ->orWhere('organization', 'like', "%{$search}%");
                });
            })
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->latest();

        return view('officer.drivers.index', [
            'drivers' => $driversQuery->paginate(12)->withQueryString(),
            'totalDrivers' => User::where('role', User::ROLE_DRIVER)->count(),
            'activeDrivers' => User::where('role', User::ROLE_DRIVER)->where('is_active', true)->count(),
            'inactiveDrivers' => User::where('role', User::ROLE_DRIVER)->where('is_active', false)->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->normalizeDriverInput($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'email' => ['required', 'email', 'max:191', 'unique:users,email'],
            'vehicle_name' => ['required', 'string', 'max:191'],
            'plate_number' => ['required', 'string', 'max:50', 'unique:users,plate_number'],
            'organization' => ['required', 'string', 'max:191'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'is_active' => ['nullable', 'boolean'],
        ]);

        User::create([
            ...$validated,
            'role' => User::ROLE_DRIVER,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()
            ->route('officer.drivers.index')
            ->with('success', 'Driver account created successfully.');
    }

    public function update(Request $request, User $driver): RedirectResponse
    {
        $this->ensureDriver($driver);
        $this->normalizeDriverInput($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'email' => [
                'required',
                'email',
                'max:191',
                Rule::unique('users', 'email')->ignore($driver->id),
            ],
            'vehicle_name' => ['required', 'string', 'max:191'],
            'plate_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('users', 'plate_number')->ignore($driver->id),
            ],
            'organization' => ['required', 'string', 'max:191'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $driver->update([
            ...$validated,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('officer.drivers.index')
            ->with('success', 'Driver account updated successfully.');
    }

    public function resetPassword(Request $request, User $driver): RedirectResponse
    {
        $this->ensureDriver($driver);

        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $driver->forceFill([
            'password' => Hash::make($validated['password']),
        ])->save();

        return redirect()
            ->route('officer.drivers.index')
            ->with('success', 'Driver password reset successfully.');
    }

    public function updateStatus(Request $request, User $driver): RedirectResponse
    {
        $this->ensureDriver($driver);

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $driver->update([
            'is_active' => (bool) $validated['is_active'],
        ]);

        return redirect()
            ->route('officer.drivers.index')
            ->with('success', $driver->is_active
                ? 'Driver account activated successfully.'
                : 'Driver account inactivated successfully.');
    }

    public function destroy(User $driver): RedirectResponse
    {
        $this->ensureDriver($driver);
        $driver->delete();

        return redirect()
            ->route('officer.drivers.index')
            ->with('success', 'Driver account deleted successfully.');
    }

    private function normalizeDriverInput(Request $request): void
    {
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
            'plate_number' => Str::upper(
                preg_replace('/\s+/', ' ', trim((string) $request->input('plate_number'))) ?? ''
            ),
        ]);
    }

    private function ensureDriver(User $driver): void
    {
        abort_unless($driver->isDriver(), 404);
    }
}
