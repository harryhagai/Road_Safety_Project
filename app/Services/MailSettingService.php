<?php

namespace App\Services;

use App\Models\MailSetting;
use Illuminate\Support\Facades\Schema;
use Throwable;

class MailSettingService
{
    public function applyActiveSetting(string $purpose = 'password_reset'): void
    {
        try {
            if (! Schema::hasTable('mail_settings')) {
                return;
            }

            $setting = $this->activeSettingForPurpose($purpose);

            if (! $setting) {
                return;
            }

            config([
                ...$this->configForSetting($setting),
            ]);
        } catch (Throwable) {
            return;
        }
    }

    public function applySetting(MailSetting $setting): void
    {
        config($this->configForSetting($setting));
    }

    public function activeSettingForPurpose(string $purpose = 'password_reset'): ?MailSetting
    {
        $setting = MailSetting::query()
            ->where('purpose', $purpose)
            ->where('is_active', true)
            ->latest('id')
            ->first();

        if ($setting) {
            return $setting;
        }

        return MailSetting::query()
            ->where('purpose', 'general')
            ->where('is_active', true)
            ->latest('id')
            ->first();
    }

    private function mailerScheme(?string $scheme): ?string
    {
        return match ($scheme) {
            'ssl' => 'smtps',
            'tls' => 'smtp',
            '' => null,
            default => $scheme,
        };
    }

    private function configForSetting(MailSetting $setting): array
    {
        return [
            'mail.default' => $setting->mailer,
            "mail.mailers.{$setting->mailer}.transport" => $setting->mailer,
            "mail.mailers.{$setting->mailer}.scheme" => $this->mailerScheme($setting->scheme),
            "mail.mailers.{$setting->mailer}.host" => $setting->host,
            "mail.mailers.{$setting->mailer}.port" => $setting->port,
            "mail.mailers.{$setting->mailer}.username" => $setting->username ?: null,
            "mail.mailers.{$setting->mailer}.password" => $setting->password ?: null,
            'mail.from.address' => $setting->from_address,
            'mail.from.name' => $setting->from_name,
        ];
    }
}
