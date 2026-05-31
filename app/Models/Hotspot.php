<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent model representing the Hotspot domain record in RSRS.
 */
class Hotspot extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'latitude',
        'longitude',
        'radius_meters',
        'frequency',
        'severity',
        'segment_id',
        'segment_type_rule_id',
        'last_updated_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'radius_meters' => 'decimal:2',
            'last_updated_at' => 'datetime',
        ];
    }

    /**
     * Handle the rule workflow for this class.
     */

    public function rule(): BelongsTo
    {
        return $this->belongsTo(SegmentTypeRule::class, 'segment_type_rule_id');
    }

    public function segment(): BelongsTo
    {
        return $this->belongsTo(RoadSegment::class, 'segment_id');
    }
}
