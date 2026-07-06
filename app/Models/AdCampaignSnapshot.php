<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdCampaignSnapshot extends Model
{
    protected $fillable = [
        'campaign_id',
        'import_batch_id',
        'metric_type',
        'period_start',
        'period_end',
        'captured_at',
        'spend',
        'impressions',
        'clicks',
        'ctr',
        'sales_direct',
        'sales_indirect',
        'sales_total',
        'revenue_direct',
        'revenue_indirect',
        'revenue_total',
        'roas',
        'actual_cpc',
        'actual_gbm',
        'daily_budget',
        'remaining_budget',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'captured_at' => 'datetime',
        'spend' => 'decimal:2',
        'ctr' => 'decimal:4',
        'revenue_direct' => 'decimal:2',
        'revenue_indirect' => 'decimal:2',
        'revenue_total' => 'decimal:2',
        'roas' => 'decimal:4',
        'actual_cpc' => 'decimal:4',
        'actual_gbm' => 'decimal:4',
        'daily_budget' => 'decimal:2',
        'remaining_budget' => 'decimal:2',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AdCampaign::class);
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(AdImportBatch::class, 'import_batch_id');
    }
}
