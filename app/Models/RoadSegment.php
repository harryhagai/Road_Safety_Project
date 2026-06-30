<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Eloquent model representing the RoadSegment domain record in RSRS.
 */
class RoadSegment extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'segment_name',
        'segment_type_id',
        'boundary_coordinates',
        'length_km',
        'description',
        'created_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'boundary_coordinates' => 'array',
            'length_km' => 'decimal:2',
        ];
    }

    /**
     * Handle the creator workflow for this class.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Handle the segmentType workflow for this class.
     */
    public function segmentType(): BelongsTo
    {
        return $this->belongsTo(SegmentType::class, 'segment_type_id');
    }

    /**
     * Handle the getSegmentTypeNameAttribute workflow for this class.
     */
    public function getSegmentTypeNameAttribute(): ?string
    {
        return $this->segmentType?->name;
    }
}
