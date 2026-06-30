<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent model representing the EvidenceFile domain record in RSRS.
 */
class EvidenceFile extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'report_id',
        'file_name',
        'file_path',
        'file_data',
        'file_type',
        'file_size',
    ];

    protected $hidden = [
        'file_data',
    ];

    /**
     * Handle the report workflow for this class.
     */
    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }
}
