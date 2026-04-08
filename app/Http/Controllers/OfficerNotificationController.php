<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OfficerNotificationController extends Controller
{
    public function index(Request $request): View
    {
        return view('officer.notifications.index', [
            'notifications' => $this->notificationSource($request)?->latest()->paginate(12) ?? collect(),
        ]);
    }

    public function dropdownData(Request $request): JsonResponse
    {
        $source = $this->notificationSource($request);
        $notifications = $source ? $source->latest()->limit(6)->get() : collect();
        $unreadCount = $source ? $source->unread()->count() : 0;

        return response()->json([
            'unreadCount' => $unreadCount,
            'viewAllUrl' => url('/road-officer/notifications'),
            'markAllReadUrl' => url('/road-officer/notifications/mark-all-read'),
            'notifications' => $notifications->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'title' => $notification->title ?? 'Notification',
                    'message' => $notification->message ?? '',
                    'status' => $notification->status ?? 'read',
                    'status_label' => ucfirst($notification->status ?? 'read'),
                    'time' => optional($notification->created_at)?->diffForHumans() ?? 'Just now',
                    'open_url' => url('/road-officer/notifications/' . $notification->id),
                ];
            })->values(),
        ]);
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $source = $this->notificationSource($request);

        if ($source && method_exists($source, 'unread')) {
            $source->unread()->update(['status' => 'read']);
        }

        return back()->with('success', 'Notifications updated.');
    }

    public function show(Request $request, string $notificationId): RedirectResponse|View
    {
        $source = $this->notificationSource($request);

        if (! $source) {
            return redirect()
                ->route('officer.notifications.index')
                ->with('error', 'Notification details are not available yet.');
        }

        $notification = $source->find($notificationId);

        if (! $notification) {
            return redirect()
                ->route('officer.notifications.index')
                ->with('error', 'Notification not found.');
        }

        if (($notification->status ?? null) === 'unread') {
            $notification->update(['status' => 'read']);
        }

        return view('officer.notifications.show', [
            'notification' => $notification,
        ]);
    }

    private function notificationSource(Request $request): mixed
    {
        $officer = $request->user();

        return $officer && method_exists($officer, 'systemNotifications')
            ? $officer->systemNotifications()
            : null;
    }
}
