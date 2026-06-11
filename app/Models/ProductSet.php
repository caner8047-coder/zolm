<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductSet extends Model
{
    protected $fillable = [
        'user_id',
        'parent_mp_product_id',
        'name',
        'status',
        'cost_mode',
        'logistics_mode',
        'totals_cache_json',
        'calculated_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'totals_cache_json' => 'array',
            'calculated_at' => 'datetime',
        ];
    }

    public const STATUS_ACTIVE = 'active';
    public const STATUS_DRAFT = 'draft';

    public const MODE_SUM_COMPONENTS = 'sum_components';
    public const MODE_MANUAL_PARENT = 'manual_parent';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parentProduct(): BelongsTo
    {
        return $this->belongsTo(MpProduct::class, 'parent_mp_product_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProductSetItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIVE => 'Aktif',
            self::STATUS_DRAFT => 'Taslak',
            default => $this->status ?: 'Bilinmiyor',
        };
    }

    public function getCostModeLabelAttribute(): string
    {
        return match ($this->cost_mode) {
            self::MODE_MANUAL_PARENT => 'Ürün kartından',
            default => 'Bileşenlerden',
        };
    }

    public function getLogisticsModeLabelAttribute(): string
    {
        return match ($this->logistics_mode) {
            self::MODE_MANUAL_PARENT => 'Ürün kartından',
            default => 'Bileşenlerden',
        };
    }
}
