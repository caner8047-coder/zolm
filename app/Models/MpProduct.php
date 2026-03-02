<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use App\Models\User;

class MpProduct extends Model
{
    protected $fillable = [
        'user_id',
        // Tanımlayıcılar
        'barcode',
        'stock_code',
        'model_code',
        'partner_id',
        // Ürün bilgileri
        'product_name',
        'color',
        'size',
        'dimension',
        'gender',
        'brand',
        'category_name',
        'description',
        // Maliyet & Fiyat
        'cogs',
        'packaging_cost',
        'vat_rate',
        'market_price',
        'sale_price',
        'buybox_price',
        'commission_rate',
        // Stok & Lojistik
        'stock_quantity',
        'cargo_cost',
        'pieces',
        'desi',
        'otv_rate',
        // Durum
        'status',
        'variant',
        'platforms',
        'category_id',
        // Trendyol
        'image_url',
        'image_urls',
        'shipping_days',
        'shipping_type',
        'trendyol_link',
        'status_description',
        // Meta
        'import_source',
        'last_synced_at',
    ];

    protected $casts = [
        'cogs'            => 'decimal:2',
        'packaging_cost'  => 'decimal:2',
        'vat_rate'        => 'decimal:2',
        'market_price'    => 'decimal:2',
        'sale_price'      => 'decimal:2',
        'buybox_price'    => 'decimal:2',
        'commission_rate' => 'decimal:2',
        'cargo_cost'      => 'decimal:2',
        'desi'            => 'decimal:2',
        'otv_rate'        => 'decimal:2',
        'stock_quantity'  => 'integer',
        'pieces'          => 'integer',
        'shipping_days'   => 'integer',
        'image_urls'      => 'array',
        'last_synced_at'  => 'datetime',
    ];

    // ─── İlişkiler ──────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Barkod üzerinden eşleşen siparişler (MpOrder)
     */
    public function orders()
    {
        return $this->hasMany(\App\Models\MpOrder::class, 'barcode', 'barcode');
    }

    /**
     * Ürüne ait reçeteler
     */
    public function recipes()
    {
        return $this->hasMany(\App\Models\Recipe::class, 'mp_product_id');
    }

    /**
     * Aktif reçete (tek)
     */
    public function activeRecipe()
    {
        return $this->hasOne(\App\Models\Recipe::class, 'mp_product_id')
            ->where('status', 'active')
            ->latestOfMany();
    }

    // ─── Accessor'lar ───────────────────────────────────────

    /**
     * Durum etiketi (Türkçe)
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'active'       => 'Satışta',
            'out_of_stock' => 'Tükendi',
            'pending'      => 'Onay Bekliyor',
            'suspended'    => 'Beklemede',
            default        => $this->status ?? 'Bilinmiyor',
        };
    }

    /**
     * Durum rengi (Tailwind badge sınıfları)
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'active'       => 'bg-emerald-100 text-emerald-800',
            'out_of_stock' => 'bg-red-100 text-red-800',
            'pending'      => 'bg-yellow-100 text-yellow-800',
            'suspended'    => 'bg-gray-100 text-gray-800',
            default        => 'bg-gray-100 text-gray-600',
        };
    }

    /**
     * Toplam birim maliyet: COGS + Ambalaj + Kargo
     */
    public function getTotalCostAttribute(): float
    {
        return (float) $this->cogs + (float) $this->packaging_cost + (float) $this->cargo_cost;
    }

    /**
     * Kâr marjı (%) = (Satış Fiyatı - Toplam Maliyet - Komisyon) / Satış Fiyatı * 100
     */
    public function getProfitMarginAttribute(): ?float
    {
        $salePrice = (float) $this->sale_price;
        if ($salePrice <= 0) return null;

        $commissionAmount = $salePrice * ((float) $this->commission_rate / 100);
        $profit = $salePrice - $this->total_cost - $commissionAmount;

        return round(($profit / $salePrice) * 100, 1);
    }

    /**
     * Ana görsel URL (ilk görsel veya placeholder)
     */
    public function getMainImageAttribute(): ?string
    {
        if ($this->image_url) return $this->image_url;

        $urls = $this->image_urls;
        return is_array($urls) && count($urls) > 0 ? $urls[0] : null;
    }

    /**
     * Stok seviyesi etiketi
     */
    public function getStockLevelAttribute(): string
    {
        $qty = (int) $this->stock_quantity;
        if ($qty <= 0) return 'out_of_stock';
        if ($qty <= 10) return 'critical';
        return 'in_stock';
    }

    public function getStockLevelLabelAttribute(): string
    {
        return match ($this->stock_level) {
            'out_of_stock' => 'Tükendi',
            'critical'     => 'Kritik',
            'in_stock'     => 'Stokta',
            default        => 'Bilinmiyor',
        };
    }

    public function getStockLevelColorAttribute(): string
    {
        return match ($this->stock_level) {
            'out_of_stock' => 'bg-red-100 text-red-800',
            'critical'     => 'bg-amber-100 text-amber-800',
            'in_stock'     => 'bg-emerald-100 text-emerald-800',
            default        => 'bg-gray-100 text-gray-600',
        };
    }

    // ─── Query Scope'ları ───────────────────────────────────

    /**
     * Genel arama: ürün adı, barkod, stok kodu, model kodu
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (empty($term)) return $query;

        return $query->where(function ($q) use ($term) {
            $q->where('product_name', 'like', "%{$term}%")
              ->orWhere('barcode', 'like', "%{$term}%")
              ->orWhere('stock_code', 'like', "%{$term}%")
              ->orWhere('model_code', 'like', "%{$term}%");
        });
    }

    /**
     * Durum filtresi
     */
    public function scopeByStatus(Builder $query, ?string $status): Builder
    {
        if (empty($status) || $status === 'all') return $query;
        return $query->where('status', $status);
    }

    /**
     * Kategori filtresi
     */
    public function scopeByCategory(Builder $query, ?string $category): Builder
    {
        if (empty($category) || $category === 'all') return $query;
        return $query->where('category_name', $category);
    }

    /**
     * Marka filtresi
     */
    public function scopeByBrand(Builder $query, ?string $brand): Builder
    {
        if (empty($brand) || $brand === 'all') return $query;
        return $query->where('brand', $brand);
    }

    /**
     * Stok seviyesi filtresi
     */
    public function scopeByStockLevel(Builder $query, ?string $level): Builder
    {
        if (empty($level) || $level === 'all') return $query;

        return match ($level) {
            'out_of_stock' => $query->where('stock_quantity', '<=', 0),
            'critical'     => $query->whereBetween('stock_quantity', [1, 10]),
            'in_stock'     => $query->where('stock_quantity', '>', 10),
            default        => $query,
        };
    }

    /**
     * Fiyat aralığı filtresi
     */
    public function scopeByPriceRange(Builder $query, ?float $min, ?float $max): Builder
    {
        if ($min !== null) $query->where('sale_price', '>=', $min);
        if ($max !== null) $query->where('sale_price', '<=', $max);
        return $query;
    }

    /**
     * Maliyeti tanımlı ürünler (COGS > 0)
     */
    public function scopeWithCost(Builder $query): Builder
    {
        return $query->where('cogs', '>', 0);
    }

    /**
     * Maliyeti tanımlı olmayan ürünler
     */
    public function scopeWithoutCost(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->where('cogs', '<=', 0)->orWhereNull('cogs');
        });
    }
}
