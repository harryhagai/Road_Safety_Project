<?php

namespace App\Providers;

use App\Models\ContactMessage;
use App\Models\EvidenceFile;
use App\Models\Hotspot;
use App\Models\MailSetting;
use App\Models\Report;
use App\Models\RoadSegment;
use App\Models\RuleViolation;
use App\Models\SegmentTypeRule;
use App\Models\User;
use App\Models\ViolationType;
use App\Observers\ReportNotificationObserver;
use App\Observers\SensitiveActivityObserver;
use App\Services\AuditTrailService;
use App\Services\MailSettingService;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
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

        $this->configureRateLimiters();

        Schema::defaultStringLength(191);
        Paginator::useBootstrapFive();

        app(MailSettingService::class)->applyActiveSetting();

        User::observe(SensitiveActivityObserver::class);
        MailSetting::observe(SensitiveActivityObserver::class);
        ContactMessage::observe(SensitiveActivityObserver::class);
        Report::observe(ReportNotificationObserver::class);
        Report::observe(SensitiveActivityObserver::class);
        SegmentTypeRule::observe(SensitiveActivityObserver::class);
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

    private function configureRateLimiters(): void
    {
        RateLimiter::for('auto-speed-evaluate', function (Request $request): Limit {
            return Limit::perMinute(360)->by($this->rateLimitKey($request, 'auto-speed-evaluate'));
        });

        RateLimiter::for('auto-speed-submit', function (Request $request): Limit {
            return Limit::perMinute(60)->by($this->rateLimitKey($request, 'auto-speed-submit'));
        });

        RateLimiter::for('driver-report-submit', function (Request $request): Limit {
            return Limit::perMinute(60)->by($this->rateLimitKey($request, 'driver-report-submit'));
        });
    }

    private function rateLimitKey(Request $request, string $scope): string
    {
        $userId = $request->user()?->getAuthIdentifier();

        if ($userId) {
            return $scope.':user:'.$userId;
        }

        $sessionId = $request->hasSession() ? $request->session()->getId() : null;

        if ($sessionId) {
            return $scope.':session:'.$sessionId;
        }

        return $scope.':ip:'.$request->ip();
    }
}
