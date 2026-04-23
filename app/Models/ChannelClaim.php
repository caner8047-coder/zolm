<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChannelClaim extends Model
{
    protected $guarded = ['id'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'created_date' => 'datetime',
        'last_synced_at' => 'datetime',
        'raw_payload' => 'array',
    ];

    /**
     * @var array<string, string>
     */
    public const STATUS_LABELS = [
        'pending' => 'Bekliyor (Müşteri Kargoya Verecek)',
        'shipped' => 'Kargoya Verildi',
        'in_transit' => 'Yolda',
        'delivered' => 'Teslim Edildi (Karar Bekliyor)',
        'approved' => 'Onaylandı (İade Edildi)',
        'rejected' => 'Reddedildi (Ürün Geri Gönderildi)',
        'cancelled' => 'İptal Edildi',
        'unresolved' => 'Sorunlu (İhtilaf)',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ChannelClaimItem::class, 'claim_id');
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    /**
     * @return array<string, string>
     */
    public function statusBadgeColor(): array
    {
        return match ($this->status) {
            'pending', 'shipped', 'in_transit' => ['bg' => 'bg-amber-50', 'text' => 'text-amber-700', 'ring' => 'ring-amber-200'],
            'delivered' => ['bg' => 'bg-blue-50', 'text' => 'text-blue-700', 'ring' => 'ring-blue-200'], // Aksiyon beklenir!
            'approved' => ['bg' => 'bg-emerald-50', 'text' => 'text-emerald-700', 'ring' => 'ring-emerald-200'],
            'rejected', 'unresolved' => ['bg' => 'bg-red-50', 'text' => 'text-red-700', 'ring' => 'ring-red-200'],
            'cancelled' => ['bg' => 'bg-slate-50', 'text' => 'text-slate-500', 'ring' => 'ring-slate-200'],
            default => ['bg' => 'bg-slate-50', 'text' => 'text-slate-500', 'ring' => 'ring-slate-200'],
        };
    }
}
