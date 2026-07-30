<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\User;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function index(): View
    {
        return view('admin.dashboard', [
            'totalUsers' => User::count(),
            'activeUsers' => User::where('is_active', true)->count(),
            'admins' => User::where('role', User::ROLE_ADMIN)->count(),
            'roadOfficers' => User::where('role', User::ROLE_ROAD_OFFICER)->count(),
            'drivers' => User::where('role', User::ROLE_DRIVER)->count(),
            'passengers' => User::where('role', User::ROLE_PASSENGER)->count(),
            'reports' => Report::count(),
        ]);
    }
}
