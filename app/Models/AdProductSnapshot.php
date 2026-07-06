<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdProductSnapshot extends Model
{
    protected $fillable = [
        'campaign_id',
        'ad_campaign_product_id',
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
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AdCampaign::class);
    }

    public function adCampaignProduct(): BelongsTo
    {
        return $this->belongsTo(AdCampaignProduct::class);
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(AdImportBatch::class, 'import_batch_id');
    }
}
