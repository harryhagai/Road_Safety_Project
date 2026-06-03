<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripTelemetry extends Model
{
    use HasFactory;

    protected $table = 'trip_telemetry';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'trip_id',
        'recorded_at',
        'latitude',
        'longitude',
        'speed_kmh',
        'accuracy_meters',
        'battery_level',
        'network_type',
    ];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'speed_kmh' => 'decimal:2',
            'accuracy_meters' => 'decimal:2',
            'battery_level' => 'integer',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(PassengerTrip::class, 'trip_id');
    }
}
