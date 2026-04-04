<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChannelProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'external_product_id',
        'external_parent_id',
        'stock_code',
        'barcode',
        'title',
        'brand',
        'category_name',
        'vat_rate',
        'raw_payload',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'vat_rate' => 'decimal:2',
            'raw_payload' => 'array',
            'last_synced_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }

    public function listings(): HasMany
    {
        return $this->hasMany(ChannelListing::class, 'channel_product_id');
    }
}
