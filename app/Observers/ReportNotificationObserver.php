<?php

namespace App\Observers;

use App\Models\Report;
use App\Models\SystemNotification;
use App\Models\User;

class ReportNotificationObserver
{
    public function created(Report $report): void
    {
        $report->loadMissing('violationType:id,name');

        $recipients = User::query()
            ->where('is_active', true)
            ->whereIn('role', [User::ROLE_ADMIN, User::ROLE_ROAD_OFFICER])
            ->get(['id']);

        foreach ($recipients as $recipient) {
            SystemNotification::create([
                'recipient_id' => $recipient->id,
                'type' => 'new_report',
                'title' => 'New incident report submitted',
                'message' => sprintf(
                    '%s was reported at %s. Priority: %s.',
                    $report->violationType?->name ?? 'A violation',
                    $report->location_name ?: 'an unmapped location',
                    str($report->priority ?: 'normal')->replace('_', ' ')->title()
                ),
                'action_url' => route('officer.reports.show', $report),
                'data' => [
                    'report_id' => $report->id,
                    'reference_no' => $report->reference_no,
                    'reporter_type' => $report->reporter_type,
                    'priority' => $report->priority,
                    'status' => $report->status,
                ],
            ]);
        }
    }
}
