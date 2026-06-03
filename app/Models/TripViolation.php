<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripViolation extends Model
{
    use HasFactory;

    protected $table = 'trip_violations';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'trip_id',
        'report_id',
        'type',
        'description',
        'latitude',
        'longitude',
        'recorded_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'recorded_at' => 'datetime',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(PassengerTrip::class, 'trip_id');
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }
}
