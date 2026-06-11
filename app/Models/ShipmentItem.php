<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentItem extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'expected_pieces' => 'integer',
            'expected_desi' => 'decimal:2',
            'expected_cost' => 'decimal:2',
            'meta_json' => 'array',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(ChannelOrderItem::class, 'channel_order_item_id');
    }

    public function claimItem(): BelongsTo
    {
        return $this->belongsTo(ChannelClaimItem::class, 'channel_claim_item_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(MpProduct::class, 'mp_product_id');
    }
}
