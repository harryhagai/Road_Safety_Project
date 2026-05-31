<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleTelemetry extends Model
{
    use HasFactory;

    protected $table = 'vehicle_telemetry';

    protected $primaryKey = 'telemetry_id';

    protected $fillable = [
        'citizen_device_no',
        'latitude',
        'longitude',
        'current_speed',
        'heading',
        'segment_id',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'current_speed' => 'decimal:2',
            'heading' => 'decimal:2',
        ];
    }

    public function segment(): BelongsTo
    {
        return $this->belongsTo(RoadSegment::class, 'segment_id');
    }
}
