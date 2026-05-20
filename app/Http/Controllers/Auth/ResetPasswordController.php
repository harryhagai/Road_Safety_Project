<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

/**
 * Auth controller that manages the ResetPasswordController flow for RSRS users.
 */
class ResetPasswordController extends Controller
{
    /**
     * Handle the showResetForm workflow for this class.
     */
    public function showResetForm(): RedirectResponse
    {
        return redirect()->route('password.request');
    }
}
