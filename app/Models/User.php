<?php

namespace App\Models;

use App\Notifications\RsrsResetPasswordNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Eloquent model representing the User domain record in RSRS.
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const ROLE_PASSENGER = 'passenger';

    public const ROLE_DRIVER = 'driver';

    public const ROLE_ROAD_OFFICER = 'road_officer';

    public const ROLE_ADMIN = 'admin';

    public const ROLES = [
        self::ROLE_PASSENGER,
        self::ROLE_DRIVER,
        self::ROLE_ROAD_OFFICER,
        self::ROLE_ADMIN,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'role',
        'is_active',
        'vehicle_name',
        'plate_number',
        'organization',
        'last_login_at',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class, 'driver_id');
    }

    public function submittedReports(): HasMany
    {
        return $this->hasMany(Report::class, 'submitted_by_user_id');
    }

    public function reviewedReports(): HasMany
    {
        return $this->hasMany(Report::class, 'officer_id');
    }

    public function createdRoadSegments(): HasMany
    {
        return $this->hasMany(RoadSegment::class, 'created_by');
    }

    public function verifiedRuleViolations(): HasMany
    {
        return $this->hasMany(RuleViolation::class, 'verified_by');
    }

    public function handledContactMessages(): HasMany
    {
        return $this->hasMany(ContactMessage::class, 'officer_id');
    }

    public function systemNotifications(): HasMany
    {
        return $this->hasMany(SystemNotification::class, 'recipient_id');
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new RsrsResetPasswordNotification($token));
    }

    public function isDriver(): bool
    {
        return $this->role === self::ROLE_DRIVER;
    }

    public function isPassenger(): bool
    {
        return $this->role === self::ROLE_PASSENGER;
    }

    public function isRoadOfficer(): bool
    {
        return $this->role === self::ROLE_ROAD_OFFICER;
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function canAccessOfficerWorkspace(): bool
    {
        return $this->isRoadOfficer() || $this->isAdmin();
    }

    public function dashboardRouteName(): string
    {
        return match ($this->role) {
            self::ROLE_DRIVER => 'driver.dashboard',
            self::ROLE_ROAD_OFFICER => 'officer.dashboard',
            self::ROLE_ADMIN => 'admin.dashboard',
            default => 'home',
        };
    }
}
