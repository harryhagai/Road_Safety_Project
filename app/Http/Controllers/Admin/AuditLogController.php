<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditTrail;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'action' => ['nullable', 'string', 'max:120'],
        ]);

        $logs = AuditTrail::query()
            ->when($validated['search'] ?? null, function ($query, string $search): void {
                $like = '%'.trim($search).'%';

                $query->where(function ($inner) use ($like): void {
                    $inner
                        ->where('actor_name', 'like', $like)
                        ->orWhere('subject_name', 'like', $like)
                        ->orWhere('description', 'like', $like)
                        ->orWhere('route_name', 'like', $like)
                        ->orWhere('url', 'like', $like)
                        ->orWhere('ip_address', 'like', $like);
                });
            })
            ->when($validated['action'] ?? null, fn ($query, string $action) => $query->where('action', $action))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.audit_logs.index', [
            'logs' => $logs,
            'filters' => $validated,
            'actions' => AuditTrail::query()
                ->select('action')
                ->distinct()
                ->orderBy('action')
                ->pluck('action'),
        ]);
    }
}
