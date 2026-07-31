<?php

namespace App\Http\Controllers\officer;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Officer-facing controller responsible for OfficerNotificationController actions inside the dashboard.
 */
class OfficerNotificationController extends Controller
{
    /**
     * Prepare the data needed to render the listing page.
     */
    public function index(Request $request): View
    {
        return view('officer.notifications.index', [
            'notifications' => $this->notificationSource($request)?->latest()->paginate(12) ?? collect(),
        ]);
    }

    /**
     * Handle the dropdownData workflow for this class.
     */

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
                    'action_url' => $notification->action_url,
                ];
            })->values(),
        ]);
    }

    /**
     * Handle the markAllRead workflow for this class.
     */

    public function markAllRead(Request $request): RedirectResponse|JsonResponse
    {
        $source = $this->notificationSource($request);

        if ($source && method_exists($source, 'unread')) {
            $source->unread()->update([
                'status' => 'read',
                'read_at' => now(),
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'Notifications updated.');
    }

    /**
     * Load and return the detailed view for the requested record.
     */

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

        if (method_exists($notification, 'markAsRead')) {
            $notification->markAsRead();
        } elseif (($notification->status ?? null) === 'unread') {
            $notification->update([
                'status' => 'read',
                'read_at' => now(),
            ]);
        }

        if (filled($notification->action_url)) {
            return redirect()->to($notification->action_url);
        }

        return view('officer.notifications.show', [
            'notification' => $notification,
        ]);
    }

    /**
     * Handle the notificationSource workflow for this class.
     */

    private function notificationSource(Request $request): mixed
    {
        $officer = $request->user();

        return $officer && method_exists($officer, 'systemNotifications')
            ? $officer->systemNotifications()
            : null;
    }
}
