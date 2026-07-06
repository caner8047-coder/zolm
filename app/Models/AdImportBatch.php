<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdImportBatch extends Model
{
    protected $fillable = [
        'user_id',
        'ad_account_id',
        'channel_code',
        'import_type',
        'status',
        'report_period_start',
        'report_period_end',
        'exported_at',
        'uploaded_by_user_id',
        'source_filename',
        'storage_path',
        'file_hash',
        'source_fingerprint',
        'campaign_id_context',
        'duplicate_of_batch_id',
        'row_count',
        'valid_row_count',
        'invalid_row_count',
        'error_summary',
        'metadata',
    ];

    protected $casts = [
        'report_period_start' => 'date',
        'report_period_end' => 'date',
        'exported_at' => 'datetime',
        'error_summary' => 'array',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function adAccount(): BelongsTo
    {
        return $this->belongsTo(AdAccount::class);
    }

    public function uploadedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function campaignContext(): BelongsTo
    {
        return $this->belongsTo(AdCampaign::class, 'campaign_id_context');
    }

    public function duplicateOfBatch(): BelongsTo
    {
        return $this->belongsTo(AdImportBatch::class, 'duplicate_of_batch_id');
    }

    public function adImportRows(): HasMany
    {
        return $this->hasMany(AdImportRow::class, 'batch_id');
    }

    public function adCampaignSnapshots(): HasMany
    {
        return $this->hasMany(AdCampaignSnapshot::class, 'import_batch_id');
    }

    public function adProductSnapshots(): HasMany
    {
        return $this->hasMany(AdProductSnapshot::class, 'import_batch_id');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function isPending(): bool
    {
        return in_array($this->status, ['uploaded', 'parsing']);
    }

    public function isCompleted(): bool
    {
        return $this->status === 'imported';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isDuplicate(): bool
    {
        return $this->status === 'duplicate';
    }
}
