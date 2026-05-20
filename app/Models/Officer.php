<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Eloquent model representing the Officer domain record in RSRS.
 */
class Officer extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'full_name',
        'email',
        'password',
        'role',
        'last_login_at',
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
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Handle the reports workflow for this class.
     */

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    /**
     * Handle the createdRoadSegments workflow for this class.
     */

    public function createdRoadSegments(): HasMany
    {
        return $this->hasMany(RoadSegment::class, 'created_by');
    }

    /**
     * Handle the createdRoadRules workflow for this class.
     */

    public function createdRoadRules(): HasMany
    {
        return $this->hasMany(RoadRule::class, 'created_by');
    }

    /**
     * Handle the verifiedRuleViolations workflow for this class.
     */

    public function verifiedRuleViolations(): HasMany
    {
        return $this->hasMany(RuleViolation::class, 'verified_by');
    }
}
