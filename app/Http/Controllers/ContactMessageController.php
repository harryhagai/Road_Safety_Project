<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Web controller that coordinates the ContactMessageController request lifecycle.
 */
class ContactMessageController extends Controller
{
    /**
     * Display the form used to capture a new record.
     */
    public function create(): View
    {
        return view('contact');
    }

    /**
     * Validate the request and persist a new record.
     */

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'website' => ['nullable', Rule::prohibitedIf(fn (): bool => filled($request->input('website')))],
        ]);

        ContactMessage::create([
            'reference_no' => $this->makeReferenceNumber(),
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'subject' => $validated['subject'],
            'message' => $validated['message'],
            'status' => ContactMessage::STATUS_NEW,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
        ]);

        return redirect()
            ->route('contact')
            ->with('status', 'Thank you. Your message has been received and will be reviewed by a road officer.');
    }

    /**
     * Handle the makeReferenceNumber workflow for this class.
     */

    private function makeReferenceNumber(): string
    {
        do {
            $referenceNo = 'MSG-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (ContactMessage::where('reference_no', $referenceNo)->exists());

        return $referenceNo;
    }
}
