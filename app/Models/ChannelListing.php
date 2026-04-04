<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChannelListing extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'channel_product_id',
        'mp_product_id',
        'listing_id',
        'listing_status',
        'sale_price',
        'list_price',
        'currency',
        'stock_quantity',
        'published_at',
        'last_price_sync_at',
        'last_stock_sync_at',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'sale_price' => 'decimal:2',
            'list_price' => 'decimal:2',
            'published_at' => 'datetime',
            'last_price_sync_at' => 'datetime',
            'last_stock_sync_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }

    public function channelProduct(): BelongsTo
    {
        return $this->belongsTo(ChannelProduct::class, 'channel_product_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(MpProduct::class, 'mp_product_id');
    }

    public function matchIssues(): HasMany
    {
        return $this->hasMany(ProductMatchIssue::class, 'channel_listing_id');
    }

    public function pushRuns(): HasMany
    {
        return $this->hasMany(IntegrationPushRun::class, 'channel_listing_id');
    }
}
