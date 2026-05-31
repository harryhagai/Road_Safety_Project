<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Eloquent model representing the Report domain record in RSRS.
 */
class Report extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'reference_no',
        'violation_type_id',
        'description',
        'latitude',
        'longitude',
        'location_name',
        'status',
        'priority',
        'reported_at',
        'officer_id',
        'reviewed_at',
        'officer_notes',
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
            'reported_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * Handle the violationType workflow for this class.
     */

    public function violationType(): BelongsTo
    {
        return $this->belongsTo(ViolationType::class);
    }

    /**
     * Handle the officer workflow for this class.
     */

    public function officer(): BelongsTo
    {
        return $this->belongsTo(Officer::class);
    }

    /**
     * Handle the evidenceFiles workflow for this class.
     */

    public function evidenceFiles(): HasMany
    {
        return $this->hasMany(EvidenceFile::class);
    }

    /**
     * Handle the ruleViolations workflow for this class.
     */

    public function ruleViolations(): HasMany
    {
        return $this->hasMany(RuleViolation::class);
    }

    /**
     * Handle the violatedRules workflow for this class.
     */

    public function violatedRules(): BelongsToMany
    {
        return $this->belongsToMany(SegmentTypeRule::class, 'rule_violations', 'report_id', 'segment_type_rule_id')
            ->withPivot(['matched_automatically', 'confidence_score', 'verified_by', 'verified_at'])
            ->withTimestamps();
    }
}
