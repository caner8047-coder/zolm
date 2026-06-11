<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CrmCustomerLedgerEntry extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'gross_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'commission_rate' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'cargo_amount' => 'decimal:2',
            'cost_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'profit_amount' => 'decimal:2',
            'purchased_at' => 'datetime',
            'payload_json' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(CrmContact::class, 'contact_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(ChannelOrder::class, 'channel_order_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(ChannelOrderItem::class, 'channel_order_item_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(MpProduct::class, 'mp_product_id');
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class, 'recipe_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function marginPercent(): ?float
    {
        $gross = (float) $this->gross_amount;

        if ($gross <= 0) {
            return null;
        }

        return round(((float) $this->profit_amount / $gross) * 100, 1);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'completed' => 'Tamamlandı',
            'pending' => 'Bekliyor',
            'returned' => 'İade',
            'cancelled' => 'İptal',
            default => $this->status ?: 'Bilinmiyor',
        };
    }

    public function statusTone(): string
    {
        return match ($this->status) {
            'completed' => 'success',
            'pending' => 'warning',
            'returned', 'cancelled' => 'danger',
            default => 'default',
        };
    }

    public function sourceLabel(): string
    {
        return match ($this->source_type) {
            'marketplace_order_item' => 'Pazaryeri',
            'manual' => 'Manuel',
            default => $this->source_type ?: 'Kaynak yok',
        };
    }

    public function profitTone(): string
    {
        return match (true) {
            (float) $this->profit_amount < 0 => 'danger',
            (float) $this->profit_amount < 100 => 'warning',
            default => 'success',
        };
    }
}
