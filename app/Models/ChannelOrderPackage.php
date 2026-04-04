<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChannelOrderPackage extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'channel_order_id',
        'external_package_id',
        'package_number',
        'package_status',
        'cargo_company',
        'cargo_tracking_number',
        'cargo_barcode',
        'cargo_desi',
        'shipment_provider',
        'shipped_at',
        'delivered_at',
        'last_synced_at',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'cargo_desi' => 'decimal:2',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'raw_payload' => 'array',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(ChannelOrder::class, 'channel_order_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ChannelOrderItem::class, 'channel_order_package_id');
    }

    public function actionRuns(): HasMany
    {
        return $this->hasMany(IntegrationOrderActionRun::class, 'channel_order_package_id');
    }
}
