<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Eloquent model representing the AuditTrail domain record in RSRS.
 */
class AuditTrail extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'actor_type',
        'actor_id',
        'actor_name',
        'action',
        'description',
        'subject_type',
        'subject_id',
        'subject_name',
        'route_name',
        'method',
        'url',
        'ip_address',
        'user_agent',
        'status_code',
        'old_values',
        'new_values',
        'metadata',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'metadata' => 'array',
        ];
    }

    /**
     * Handle the actor workflow for this class.
     */

    public function actor(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Handle the subject workflow for this class.
     */

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
