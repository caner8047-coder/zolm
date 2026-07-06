<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdCampaignProduct extends Model
{
    protected $fillable = [
        'campaign_id',
        'zolm_product_id',
        'marketplace_content_id',
        'marketplace_model_code',
        'product_name_snapshot',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AdCampaign::class, 'campaign_id');
    }

    public function zolmProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'zolm_product_id');
    }
}
