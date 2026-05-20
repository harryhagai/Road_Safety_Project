<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent model representing the ContactMessage domain record in RSRS.
 */
class ContactMessage extends Model
{
    use HasFactory;

    public const STATUS_NEW = 'new';
    public const STATUS_IN_REVIEW = 'in_review';
    public const STATUS_RESPONDED = 'responded';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_ARCHIVED = 'archived';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'reference_no',
        'name',
        'email',
        'phone',
        'subject',
        'message',
        'status',
        'officer_id',
        'read_at',
        'responded_at',
        'resolved_at',
        'response_notes',
        'ip_address',
        'user_agent',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
            'responded_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_NEW => 'New',
            self::STATUS_IN_REVIEW => 'In Review',
            self::STATUS_RESPONDED => 'Responded',
            self::STATUS_RESOLVED => 'Resolved',
            self::STATUS_ARCHIVED => 'Archived',
        ];
    }

    /**
     * Handle the officer workflow for this class.
     */

    public function officer(): BelongsTo
    {
        return $this->belongsTo(Officer::class);
    }
}
