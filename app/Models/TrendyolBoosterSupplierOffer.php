<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrendyolBoosterSupplierOffer extends Model
{
    use HasFactory;

    protected $fillable = [
        'trendyol_booster_supplier_research_id',
        'user_id',
        'scan_uuid',
        'offer_key',
        'platform',
        'platform_label',
        'seller_name',
        'seller_id',
        'external_product_id',
        'title',
        'source_url',
        'source_url_hash',
        'sale_price',
        'previous_sale_price',
        'price_delta',
        'stock',
        'previous_stock',
        'estimated_sales',
        'availability',
        'match_score',
        'match_status',
        'source_type',
        'rank',
        'raw_payload',
        'observed_at',
    ];

    protected function casts(): array
    {
        return [
            'sale_price' => 'decimal:2',
            'previous_sale_price' => 'decimal:2',
            'price_delta' => 'decimal:2',
            'stock' => 'integer',
            'previous_stock' => 'integer',
            'estimated_sales' => 'integer',
            'match_score' => 'integer',
            'rank' => 'integer',
            'raw_payload' => 'array',
            'observed_at' => 'datetime',
        ];
    }

    public function research(): BelongsTo
    {
        return $this->belongsTo(TrendyolBoosterSupplierResearch::class, 'trendyol_booster_supplier_research_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
