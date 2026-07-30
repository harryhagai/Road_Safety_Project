<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'role' => ['nullable', Rule::in(User::ROLES)],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));

        $users = User::query()
            ->withCount(['reports', 'submittedReports', 'reviewedReports'])
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
            ->when(($validated['role'] ?? null), fn ($query, $role) => $query->where('role', $role))
            ->when(($validated['status'] ?? null) === 'active', fn ($query) => $query->where('is_active', true))
            ->when(($validated['status'] ?? null) === 'inactive', fn ($query) => $query->where('is_active', false))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'roles' => User::ROLES,
            'totalUsers' => User::count(),
            'activeUsers' => User::where('is_active', true)->count(),
            'inactiveUsers' => User::where('is_active', false)->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->normalizeInput($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'email' => ['required', 'email', 'max:191', 'unique:users,email'],
            'role' => ['required', Rule::in(User::ROLES)],
            'vehicle_name' => ['nullable', 'string', 'max:191'],
            'plate_number' => ['nullable', 'string', 'max:50', 'unique:users,plate_number'],
            'organization' => ['nullable', 'string', 'max:191'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'is_active' => ['nullable', 'boolean'],
        ]);

        User::create([
            ...$validated,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User account created successfully.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->normalizeInput($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'email' => [
                'required',
                'email',
                'max:191',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'role' => ['required', Rule::in(User::ROLES)],
            'vehicle_name' => ['nullable', 'string', 'max:191'],
            'plate_number' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('users', 'plate_number')->ignore($user->id),
            ],
            'organization' => ['nullable', 'string', 'max:191'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if ($request->user()->is($user)) {
            $validated['role'] = User::ROLE_ADMIN;
            $validated['is_active'] = true;
        } else {
            $validated['is_active'] = $request->boolean('is_active');
        }

        $user->update($validated);

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User account updated successfully.');
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user->forceFill([
            'password' => Hash::make($validated['password']),
        ])->save();

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User password reset successfully.');
    }

    public function updateStatus(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        if ($request->user()->is($user)) {
            return redirect()
                ->route('admin.users.index')
                ->with('error', 'You cannot inactivate your own admin account.');
        }

        $user->update([
            'is_active' => (bool) $validated['is_active'],
        ]);

        return redirect()
            ->route('admin.users.index')
            ->with('success', $user->is_active
                ? 'User account activated successfully.'
                : 'User account inactivated successfully.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->is($user)) {
            return redirect()
                ->route('admin.users.index')
                ->with('error', 'You cannot delete your own admin account.');
        }

        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User account deleted successfully.');
    }

    private function normalizeInput(Request $request): void
    {
        $plateNumber = Str::upper(
            preg_replace('/\s+/', ' ', trim((string) $request->input('plate_number'))) ?? ''
        );

        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
            'plate_number' => $plateNumber !== '' ? $plateNumber : null,
        ]);
    }
}
