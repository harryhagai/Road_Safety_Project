<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'recipient_id',
        'type',
        'title',
        'message',
        'status',
        'read_at',
        'action_url',
        'data',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
            'data' => 'array',
        ];
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('status', 'unread');
    }

    public function markAsRead(): void
    {
        if ($this->status === 'read') {
            return;
        }

        $this->forceFill([
            'status' => 'read',
            'read_at' => now(),
        ])->save();
    }
}
