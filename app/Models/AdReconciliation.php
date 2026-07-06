<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdReconciliation extends Model
{
    protected $fillable = [
        'user_id',
        'campaign_id',
        'summary_import_batch_id',
        'detail_import_batch_id',
        'comparison_type',
        'campaign_spend',
        'detail_spend',
        'spend_difference',
        'campaign_revenue',
        'detail_revenue',
        'revenue_difference',
        'campaign_sales',
        'detail_sales',
        'sales_difference',
        'difference_percent',
        'status',
        'evidence',
        'calculated_at',
    ];

    protected $casts = [
        'campaign_spend' => 'decimal:2',
        'detail_spend' => 'decimal:2',
        'spend_difference' => 'decimal:2',
        'campaign_revenue' => 'decimal:2',
        'detail_revenue' => 'decimal:2',
        'revenue_difference' => 'decimal:2',
        'difference_percent' => 'decimal:4',
        'evidence' => 'array',
        'calculated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AdCampaign::class);
    }

    public function summaryImportBatch(): BelongsTo
    {
        return $this->belongsTo(AdImportBatch::class, 'summary_import_batch_id');
    }

    public function detailImportBatch(): BelongsTo
    {
        return $this->belongsTo(AdImportBatch::class, 'detail_import_batch_id');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
