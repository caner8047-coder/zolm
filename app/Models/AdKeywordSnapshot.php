<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdKeywordSnapshot extends Model
{
    protected $fillable = [
        'campaign_id',
        'import_batch_id',
        'keyword',
        'normalized_keyword',
        'match_type',
        'period_start',
        'period_end',
        'captured_at',
        'spend',
        'impressions',
        'clicks',
        'ctr',
        'sales_total',
        'revenue_total',
        'roas',
        'recommended_gbm',
        'selected_gbm',
        'actual_gbm',
        'actual_cpc',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'captured_at' => 'datetime',
        'spend' => 'decimal:2',
        'ctr' => 'decimal:4',
        'revenue_total' => 'decimal:2',
        'roas' => 'decimal:4',
        'recommended_gbm' => 'decimal:4',
        'selected_gbm' => 'decimal:4',
        'actual_gbm' => 'decimal:4',
        'actual_cpc' => 'decimal:4',
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
