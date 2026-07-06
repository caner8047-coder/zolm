<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceProductMapping extends Model
{
    protected $fillable = [
        'user_id',
        'ad_account_id',
        'marketplace_content_id',
        'marketplace_model_code',
        'barcode',
        'sku',
        'product_name_snapshot',
        'zolm_product_id',
        'match_method',
        'status',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function adAccount(): BelongsTo
    {
        return $this->belongsTo(AdAccount::class);
    }

    public function zolmProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'zolm_product_id');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForAccount($query, int $adAccountId)
    {
        return $query->where('ad_account_id', $adAccountId);
    }
}
