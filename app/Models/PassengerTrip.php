<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PassengerTrip extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_FAILED = 'failed';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_reference',
        'device_id',
        'route_name',
        'status',
        'started_at',
        'ended_at',
        'expires_at',
        'start_latitude',
        'start_longitude',
        'end_latitude',
        'end_longitude',
        'end_reason',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'expires_at' => 'datetime',
            'start_latitude' => 'decimal:7',
            'start_longitude' => 'decimal:7',
            'end_latitude' => 'decimal:7',
            'end_longitude' => 'decimal:7',
            'metadata' => 'array',
        ];
    }

    public function telemetry(): HasMany
    {
        return $this->hasMany(TripTelemetry::class, 'trip_id');
    }

    public function violations(): HasMany
    {
        return $this->hasMany(TripViolation::class, 'trip_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE && $this->expires_at?->isFuture();
    }
}
