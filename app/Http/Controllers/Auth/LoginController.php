<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Auth controller that manages the LoginController flow for RSRS users.
 */
class LoginController extends Controller
{
    /**
     * Handle the showLoginForm workflow for this class.
     */
    public function showLoginForm(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route(Auth::user()->dashboardRouteName());
        }

        return view('auth.login');
    }

    /**
     * @throws ValidationException
     */
    public function login(Request $request): RedirectResponse
    {
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
        ]);

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $remember = $request->boolean('remember');
        if (! Auth::attempt($credentials, $remember)) {
            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        $request->session()->regenerate();

        /** @var User $account */
        $account = Auth::user();

        if (! $account->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'This account is inactive. Please contact a road officer.',
            ]);
        }

        $account->forceFill([
            'last_login_at' => now(),
        ])->save();

        return redirect()->intended(route($account->dashboardRouteName()))
            ->with('success', $account->isDriver()
                ? 'Welcome back, '.$account->name.'. Tracking and reporting are ready.'
                : 'Welcome back, '.$account->name.'.');
    }

    /**
     * Handle the logout workflow for this class.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('status', 'You have been logged out successfully.');
    }
}
