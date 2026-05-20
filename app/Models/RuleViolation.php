<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent model representing the RuleViolation domain record in RSRS.
 */
class RuleViolation extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'report_id',
        'rule_id',
        'matched_automatically',
        'confidence_score',
        'verified_by',
        'verified_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'matched_automatically' => 'boolean',
            'confidence_score' => 'decimal:2',
            'verified_at' => 'datetime',
        ];
    }

    /**
     * Handle the report workflow for this class.
     */

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    /**
     * Handle the rule workflow for this class.
     */

    public function rule(): BelongsTo
    {
        return $this->belongsTo(RoadRule::class, 'rule_id');
    }

    /**
     * Handle the verifier workflow for this class.
     */

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(Officer::class, 'verified_by');
    }
}
