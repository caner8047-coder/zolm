<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaStockWaitlist extends Model
{
    use HasFactory;

    const STATUS_WAITING = 'waiting';
    const STATUS_NOTIFIED = 'notified';
    const STATUS_CONVERTED = 'converted';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'store_id', 'contact_id', 'product_id', 'variation_id',
        'wc_product_id', 'wc_variation_id', 'status',
        'requested_at', 'notified_at', 'notified_outbox_id',
        'available_stock_snapshot', 'related_order_id',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'notified_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(WaContact::class, 'contact_id');
    }

    public function scopeWaiting($query)
    {
        return $query->where('status', self::STATUS_WAITING);
    }
}
