<?php

namespace App\Http\Controllers\officer;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Officer-facing controller responsible for ContactMessageController actions inside the dashboard.
 */
class ContactMessageController extends Controller
{
    /**
     * Prepare the data needed to render the listing page.
     */
    public function index(Request $request): View
    {
        $status = $request->query('status');
        $search = trim((string) $request->query('search'));

        $messages = ContactMessage::query()
            ->with('officer')
            ->when(is_string($status) && array_key_exists($status, ContactMessage::statuses()), fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('reference_no', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('subject', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('officer.contact-messages.index', [
            'messages' => $messages,
            'statuses' => ContactMessage::statuses(),
            'statusCounts' => ContactMessage::query()
                ->selectRaw('status, count(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status'),
        ]);
    }

    /**
     * Load and return the detailed view for the requested record.
     */

    public function show(ContactMessage $contactMessage): View
    {
        if ($contactMessage->read_at === null) {
            $contactMessage->forceFill(['read_at' => now()])->save();
        }

        return view('officer.contact-messages.show', [
            'contactMessage' => $contactMessage->load('officer'),
            'statuses' => ContactMessage::statuses(),
        ]);
    }

    /**
     * Apply validated changes to the selected record.
     */

    public function update(Request $request, ContactMessage $contactMessage): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys(ContactMessage::statuses()))],
            'response_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $status = $validated['status'];
        $now = now();

        $contactMessage->fill([
            'status' => $status,
            'response_notes' => $validated['response_notes'] ?? null,
            'officer_id' => $request->user()?->id,
            'read_at' => $contactMessage->read_at ?? $now,
        ]);

        if ($status === ContactMessage::STATUS_RESPONDED && $contactMessage->responded_at === null) {
            $contactMessage->responded_at = $now;
        }

        if ($status === ContactMessage::STATUS_RESOLVED && $contactMessage->resolved_at === null) {
            $contactMessage->resolved_at = $now;
            $contactMessage->responded_at ??= $now;
        }

        $contactMessage->save();

        return redirect()
            ->route('officer.contact-messages.show', $contactMessage)
            ->with('success', 'Contact message updated successfully.');
    }

    /**
     * Remove the selected record from storage.
     */

    public function destroy(ContactMessage $contactMessage): RedirectResponse
    {
        $contactMessage->delete();

        return redirect()
            ->route('officer.contact-messages.index')
            ->with('success', 'Contact message deleted successfully.');
    }
}
