<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoadSegment extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'segment_name',
        'segment_type',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Officer::class, 'created_by');
    }

    public function segmentType(): BelongsTo
    {
        return $this->belongsTo(SegmentType::class, 'segment_type_id');
    }

    public function roadRules(): HasMany
    {
        return $this->hasMany(RoadRule::class, 'segment_id');
    }

    public function getSegmentTypeNameAttribute(): ?string
    {
        return $this->segmentType?->name ?: $this->segment_type;
    }
}
