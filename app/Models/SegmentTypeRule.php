<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SegmentTypeRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'segment_type_id',
        'rule_name',
        'rule_type',
        'rule_value',
        'description',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function segmentType(): BelongsTo
    {
        return $this->belongsTo(SegmentType::class, 'segment_type_id');
    }

}
