<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InfluencerProductSale extends Model
{
    protected $fillable = [
        'campaign_id',
        'influencer_profile_id',
        'zolm_product_id',
        'product_name_snapshot',
        'sale_date',
        'revenue_total',
        'sales_total',
        'link_visits_that_day',
        'import_batch_id',
    ];

    protected $casts = [
        'sale_date' => 'date',
        'revenue_total' => 'decimal:2',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AdCampaign::class);
    }

    public function influencerProfile(): BelongsTo
    {
        return $this->belongsTo(InfluencerProfile::class);
    }

    public function zolmProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'zolm_product_id');
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(AdImportBatch::class, 'import_batch_id');
    }
}
