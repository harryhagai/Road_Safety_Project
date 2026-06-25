<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MailSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MailSettingController extends Controller
{
    private const PURPOSES = [
        'password_reset' => 'Forgot Password / Reset Links',
        'parent_results' => 'Parent Results & Academic Reports',
        'application_issues' => 'Application Issues',
        'general' => 'General Outgoing Mail',
    ];

    public function content(): View
    {
        return view('admin.mail_settings', [
            'mailSettings' => MailSetting::query()->latest()->paginate(10),
            'purposes' => self::PURPOSES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedMailSetting($request);
        $validated['is_active'] = $request->boolean('is_active');

        if ($validated['is_active']) {
            MailSetting::query()
                ->where('purpose', $validated['purpose'])
                ->update(['is_active' => false]);
        }

        MailSetting::create($validated);

        return redirect()
            ->route('admin.mail_settings.content')
            ->with('success', 'Mail setting created successfully.');
    }

    public function update(Request $request, MailSetting $mailSetting): RedirectResponse
    {
        $validated = $this->validatedMailSetting($request);
        $validated['is_active'] = $request->boolean('is_active');

        if (($validated['password'] ?? null) === null) {
            unset($validated['password']);
        }

        if ($validated['is_active']) {
            MailSetting::query()
                ->whereKeyNot($mailSetting->getKey())
                ->where('purpose', $validated['purpose'])
                ->update(['is_active' => false]);
        }

        $mailSetting->update($validated);

        return redirect()
            ->route('admin.mail_settings.content')
            ->with('success', 'Mail setting updated successfully.');
    }

    public function destroy(MailSetting $mailSetting): RedirectResponse
    {
        $mailSetting->delete();

        return redirect()
            ->route('admin.mail_settings.content')
            ->with('success', 'Mail setting deleted successfully.');
    }

    public function activate(MailSetting $mailSetting): RedirectResponse
    {
        MailSetting::query()
            ->where('purpose', $mailSetting->purpose)
            ->update(['is_active' => false]);
        $mailSetting->update(['is_active' => true]);

        return redirect()
            ->route('admin.mail_settings.content')
            ->with('success', 'Mail setting activated successfully.');
    }

    /**
     * @return array{name: string, purpose: string, mailer: string, scheme: string|null, host: string, port: int, username: string|null, password: string|null, from_address: string, from_name: string}
     */
    private function validatedMailSetting(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'purpose' => ['required', 'string', 'max:191'],
            'purpose_other' => ['nullable', 'string', 'max:191'],
            'mailer' => ['required', Rule::in(['smtp'])],
            'scheme' => ['nullable', Rule::in(['smtp', 'smtps'])],
            'host' => ['required', 'string', 'max:191'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'username' => ['nullable', 'string', 'max:191'],
            'password' => ['nullable', 'string', 'max:1000'],
            'from_address' => ['required', 'email', 'max:191'],
            'from_name' => ['required', 'string', 'max:191'],
        ]);

        $validated['purpose'] = $this->resolvePurpose($validated['purpose'], $validated['purpose_other'] ?? null);
        unset($validated['purpose_other']);

        return $validated;
    }

    private function resolvePurpose(string $purpose, ?string $purposeOther): string
    {
        if ($purpose !== 'other') {
            return $purpose;
        }

        $customPurpose = trim((string) $purposeOther);

        if ($customPurpose === '') {
            return 'general';
        }

        return str($customPurpose)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->limit(191, '');
    }
}
