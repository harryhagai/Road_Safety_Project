<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditTrail;
use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function index(): View
    {
        $auditTrendRows = AuditTrail::query()
            ->selectRaw('DATE(created_at) as audit_date, COUNT(*) as total')
            ->where('created_at', '>=', now()->subDays(13)->startOfDay())
            ->groupBy('audit_date')
            ->orderBy('audit_date')
            ->get()
            ->keyBy('audit_date');

        $auditTrend = collect(range(13, 0))
            ->map(function (int $daysAgo) use ($auditTrendRows): array {
                $date = now()->subDays($daysAgo)->toDateString();

                return [
                    'label' => Carbon::parse($date)->format('d M'),
                    'value' => (int) ($auditTrendRows->get($date)?->total ?? 0),
                ];
            })
            ->values();

        $recentAuditLogs = AuditTrail::query()
            ->latest('id')
            ->limit(5)
            ->get(['actor_name', 'action', 'description', 'created_at']);

        return view('admin.dashboard', [
            'totalUsers' => User::count(),
            'activeUsers' => User::where('is_active', true)->count(),
            'admins' => User::where('role', User::ROLE_ADMIN)->count(),
            'roadOfficers' => User::where('role', User::ROLE_ROAD_OFFICER)->count(),
            'drivers' => User::where('role', User::ROLE_DRIVER)->count(),
            'passengers' => User::where('role', User::ROLE_PASSENGER)->count(),
            'reports' => Report::count(),
            'auditTrend' => $auditTrend,
            'recentAuditLogs' => $recentAuditLogs,
        ]);
    }
}
