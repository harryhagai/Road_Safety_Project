<?php

namespace Database\Seeders;

use App\Models\MailSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RsrsMailSettingSeeder extends Seeder
{
    public function run(): void
    {
        config([
            'database.connections.imported_mail_mysql' => [
                'driver' => 'mysql',
                'host' => env('IMPORT_MAIL_DB_HOST', env('DB_HOST', '127.0.0.1')),
                'port' => env('IMPORT_MAIL_DB_PORT', env('DB_PORT', '3306')),
                'database' => env('IMPORT_MAIL_DB_DATABASE', 'mail_source_db'),
                'username' => env('IMPORT_MAIL_DB_USERNAME', env('DB_USERNAME', 'root')),
                'password' => env('IMPORT_MAIL_DB_PASSWORD', env('DB_PASSWORD', '')),
                'unix_socket' => env('DB_SOCKET', ''),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'prefix_indexes' => true,
                'strict' => true,
                'engine' => null,
            ],
        ]);

        $settings = DB::connection('imported_mail_mysql')
            ->table('site_settings')
            ->where('group', 'mail')
            ->pluck('value', 'key');

        if ($settings->isEmpty()) {
            $this->command?->warn('No imported mail settings were found.');

            return;
        }

        $scheme = match ($settings->get('mail_encryption')) {
            'ssl' => 'smtps',
            'tls' => 'smtp',
            default => null,
        };

        MailSetting::query()
            ->where('purpose', 'password_reset')
            ->update(['is_active' => false]);

        MailSetting::query()->updateOrCreate(
            [
                'purpose' => 'password_reset',
                'name' => 'RSRS Password Reset SMTP',
            ],
            [
                'mailer' => $settings->get('mail_mailer', 'smtp'),
                'scheme' => $scheme,
                'host' => $settings->get('mail_host', '127.0.0.1'),
                'port' => (int) $settings->get('mail_port', 2525),
                'username' => $settings->get('mail_username'),
                'password' => $settings->get('mail_password'),
                'from_address' => $settings->get('mail_from_address'),
                'from_name' => 'Road Safety Reporting System',
                'is_active' => true,
            ]
        );

        DB::purge('imported_mail_mysql');
    }
}
