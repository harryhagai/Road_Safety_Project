<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Auth controller that manages the RegisterController flow for RSRS users.
 */
class RegisterController extends Controller
{
    /**
     * Handle the showRegistrationForm workflow for this class.
     */
    public function showRegistrationForm(): RedirectResponse
    {
        return redirect()->route('login')
            ->with('status', 'Self-registration is disabled. Please contact the system administrator.');
    }

    /**
     * Handle the register workflow for this class.
     */

    public function register(Request $request): RedirectResponse
    {
        return redirect()->route('login')
            ->with('status', 'Self-registration is disabled. Please contact the system administrator.');
    }
}
