<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class DriverAuthController extends Controller
{
    public function showLoginForm(): RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route(Auth::user()->dashboardRouteName());
        }

        return redirect()->route('login');
    }

    public function login(Request $request): RedirectResponse
    {
        return app(LoginController::class)->login($request);
    }

    public function showRegistrationForm(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route(Auth::user()->dashboardRouteName());
        }

        return view('auth.driver-register');
    }

    public function register(Request $request): RedirectResponse
    {
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
            'plate_number' => Str::upper(
                preg_replace('/\s+/', ' ', trim((string) $request->input('plate_number'))) ?? ''
            ),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'email' => ['required', 'email', 'max:191', 'unique:users,email'],
            'vehicle_name' => ['required', 'string', 'max:191'],
            'plate_number' => ['required', 'string', 'max:50', 'unique:users,plate_number'],
            'organization' => ['required', 'string', 'max:191'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $driver = User::create([
            ...$validated,
            'role' => User::ROLE_DRIVER,
        ]);

        event(new Registered($driver));
        Auth::login($driver);
        $request->session()->regenerate();

        $driver->forceFill(['last_login_at' => now()])->save();

        return redirect()->route('driver.dashboard')
            ->with('success', 'Driver account created. Your reports will now include your driver identity.');
    }

    public function logout(Request $request): RedirectResponse
    {
        return app(LoginController::class)->logout($request);
    }
}
