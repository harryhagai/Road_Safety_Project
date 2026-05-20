<?php

namespace App\Providers;

use App\Models\ContactMessage;
use App\Models\EvidenceFile;
use App\Models\Hotspot;
use App\Models\Officer;
use App\Models\Report;
use App\Models\RoadRule;
use App\Models\RoadSegment;
use App\Models\RuleViolation;
use App\Models\User;
use App\Models\ViolationType;
use App\Observers\SensitiveActivityObserver;
use App\Services\AuditTrailService;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider that boots container bindings related to AppServiceProvider.
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (str_contains(config('app.url'), 'ngrok-free.dev') || isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            URL::forceScheme('https');
        }

        Schema::defaultStringLength(191);
        Paginator::useBootstrapFive();

        User::observe(SensitiveActivityObserver::class);
        Officer::observe(SensitiveActivityObserver::class);
        ContactMessage::observe(SensitiveActivityObserver::class);
        Report::observe(SensitiveActivityObserver::class);
        RoadRule::observe(SensitiveActivityObserver::class);
        RoadSegment::observe(SensitiveActivityObserver::class);
        ViolationType::observe(SensitiveActivityObserver::class);
        RuleViolation::observe(SensitiveActivityObserver::class);
        Hotspot::observe(SensitiveActivityObserver::class);
        EvidenceFile::observe(SensitiveActivityObserver::class);

        Event::listen(Login::class, function (Login $event): void {
            app(AuditTrailService::class)->logAuthEvent('login', $event->user, [
                'guard' => $event->guard,
            ]);
        });

        Event::listen(Logout::class, function (Logout $event): void {
            app(AuditTrailService::class)->logAuthEvent('logout', $event->user, [
                'guard' => $event->guard,
            ]);
        });

        Event::listen(Registered::class, function (Registered $event): void {
            $user = $event->user;

            app(AuditTrailService::class)->logAuthEvent('registered', $user, [
                'registered_user_email' => $user->email ?? null,
            ]);
        });

        Event::listen(Failed::class, function (Failed $event): void {
            app(AuditTrailService::class)->logAuthEvent('failed', null, [
                'guard' => $event->guard,
                'email' => $event->credentials['email'] ?? null,
            ]);
        });

        Event::listen(PasswordReset::class, function (PasswordReset $event): void {
            app(AuditTrailService::class)->logAuthEvent('password_reset', $event->user, [
                'email' => $event->user->email ?? null,
            ]);
        });
    }
}
