<?php

namespace App\Models;

use App\Services\ProductCompositionResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\User;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class MpProduct extends Model
{
    use HasFactory;
    
    protected static bool $refreshingSetParents = false;

    protected const SET_PARENT_REFRESH_FIELDS = [
        'cogs',
        'packaging_cost',
        'cargo_cost',
        'desi',
        'pieces',
        'stock_quantity',
    ];

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
        'unit_name',
        'description',
        // Maliyet & Fiyat
        'cogs',
        'packaging_cost',
        'vat_rate',
        'cost_vat_rate',
        'market_price',
        'sale_price',
        'buybox_price',
        'commission_rate',
        'profit_commission_override_enabled',
        // Stok & Lojistik
        'stock_quantity',
        'critical_stock_threshold',
        'return_rate',
        'return_rate_source',
        'return_rate_calculated_at',
        'last_stock_alert_level',
        'last_stock_alert_quantity',
        'last_stock_alerted_at',
        'cargo_cost',
        'extra_cost_fixed',
        'extra_cost_percentage',
        'pieces',
        'desi',
        'otv_rate',
        // Durum
        'status',
        'product_type',
        'cost_source',
        'logistics_source',
        'variant',
        'platforms',
        'category_id',
        // Trendyol
        'image_url',
        'image_urls',
        'video_urls',
        'shipping_days',
        'shipping_type',
        'fast_delivery_type',
        'trendyol_link',
        'status_description',
        // Meta
        'import_source',
        'last_synced_at',
        'source_user_id',
        'source_product_id',
        'clone_reason',
        'clone_correlation_id',
        'cloned_at',
    ];

    protected $casts = [
        'cogs'            => 'decimal:2',
        'packaging_cost'  => 'decimal:2',
        'vat_rate'        => 'decimal:2',
        'cost_vat_rate'   => 'decimal:2',
        'market_price'    => 'decimal:2',
        'sale_price'      => 'decimal:2',
        'buybox_price'    => 'decimal:2',
        'commission_rate' => 'decimal:2',
        'profit_commission_override_enabled' => 'boolean',
        'cargo_cost'      => 'decimal:2',
        'extra_cost_fixed' => 'decimal:2',
        'extra_cost_percentage' => 'decimal:2',
        'desi'            => 'decimal:2',
        'otv_rate'        => 'decimal:2',
        'stock_quantity'  => 'integer',
        'critical_stock_threshold' => 'integer',
        'return_rate'     => 'decimal:2',
        'return_rate_calculated_at' => 'datetime',
        'last_stock_alert_quantity' => 'integer',
        'last_stock_alerted_at' => 'datetime',
        'pieces'          => 'integer',
        'shipping_days'   => 'integer',
        'image_urls'      => 'array',
        'video_urls'      => 'array',
        'last_synced_at'  => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (MpProduct $product): void {
            if (empty($product->barcode)) {
                $product->barcode = 'BAR-' . ($product->stock_code ?: uniqid());
            }
        });

        static::saved(function (MpProduct $product): void {
            // Stok değişikliği → domain event
            if ($product->wasChanged('stock_quantity')) {
                $old = $product->getOriginal('stock_quantity');
                $new = $product->stock_quantity;
                \App\Events\ProductStockChanged::dispatch($product, (int) $old, (int) $new);
            }

            if (static::$refreshingSetParents || !$product->wasChanged(static::SET_PARENT_REFRESH_FIELDS)) {
                return;
            }

            static::$refreshingSetParents = true;

            try {
                app(ProductCompositionResolver::class)->refreshParentSetsForComponent($product);
            } finally {
                static::$refreshingSetParents = false;
            }
        });
    }

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
     * Kanallardaki listing bağlantıları
     */
    public function channelListings(): HasMany
    {
        return $this->hasMany(ChannelListing::class, 'mp_product_id');
    }

    public function changeLogs(): HasMany
    {
        return $this->hasMany(MpProductChangeLog::class, 'mp_product_id')->latest('changed_at');
    }

    /**
     * Ürünün set/takım bileşen tanımı.
     */
    public function productSet(): HasOne
    {
        return $this->hasOne(ProductSet::class, 'parent_mp_product_id');
    }

    /**
     * Bu ürünün başka setlerde bileşen olarak kullanıldığı satırlar.
     */
    public function setMemberships(): HasMany
    {
        return $this->hasMany(ProductSetItem::class, 'component_mp_product_id');
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

    public function getProductTypeLabelAttribute(): string
    {
        return match ($this->product_type) {
            'set' => 'Set / Takım',
            'bundle' => 'Kombin',
            default => 'Tekil Ürün',
        };
    }

    public function getIsSetProductAttribute(): bool
    {
        return in_array((string) $this->product_type, ['set', 'bundle'], true);
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
        $salePrice = (float) $this->sale_price;
        $extraPercentCost = $salePrice * ((float) $this->extra_cost_percentage / 100);

        return (float) $this->cogs
            + (float) $this->packaging_cost
            + (float) $this->cargo_cost
            + (float) $this->extra_cost_fixed
            + $extraPercentCost;
    }

    /**
     * Ciro bazlı kâr oranı (%) = (Net Kâr / Satış Fiyatı) * 100
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
        $threshold = $this->critical_stock_threshold;

        if ($qty <= 0) return 'out_of_stock';
        if ($threshold !== null && $qty <= (int) $threshold) return 'critical';
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
            $q->where('mp_products.product_name', 'like', "%{$term}%")
              ->orWhere('mp_products.barcode', 'like', "%{$term}%")
              ->orWhere('mp_products.stock_code', 'like', "%{$term}%")
              ->orWhere('mp_products.model_code', 'like', "%{$term}%");
        });
    }

    /**
     * Durum filtresi
     */
    public function scopeByStatus(Builder $query, ?string $status): Builder
    {
        if (empty($status) || $status === 'all') return $query;
        return $query->where('mp_products.status', $status);
    }

    /**
     * Kategori filtresi
     */
    public function scopeByCategory(Builder $query, ?string $category): Builder
    {
        if (empty($category) || $category === 'all') return $query;
        return $query->where('mp_products.category_name', $category);
    }

    /**
     * Marka filtresi
     */
    public function scopeByBrand(Builder $query, ?string $brand): Builder
    {
        if (empty($brand) || $brand === 'all') return $query;
        return $query->where('mp_products.brand', $brand);
    }

    /**
     * Stok seviyesi filtresi
     */
    public function scopeByStockLevel(Builder $query, ?string $level): Builder
    {
        if (empty($level) || $level === 'all') return $query;

        return match ($level) {
            'out_of_stock' => $query->where('mp_products.stock_quantity', '<=', 0),
            'critical'     => $query->whereNotNull('mp_products.critical_stock_threshold')
                ->whereColumn('mp_products.stock_quantity', '<=', 'mp_products.critical_stock_threshold')
                ->where('mp_products.stock_quantity', '>', 0),
            'in_stock'     => $query->where('mp_products.stock_quantity', '>', 0)
                ->where(function (Builder $query) {
                    $query->whereNull('mp_products.critical_stock_threshold')
                        ->orWhereColumn('mp_products.stock_quantity', '>', 'mp_products.critical_stock_threshold');
                }),
            default        => $query,
        };
    }

    /**
     * Fiyat aralığı filtresi
     */
    public function scopeByPriceRange(Builder $query, ?float $min, ?float $max): Builder
    {
        if ($min !== null) $query->where('mp_products.sale_price', '>=', $min);
        if ($max !== null) $query->where('mp_products.sale_price', '<=', $max);
        return $query;
    }

    /**
     * Maliyeti tanımlı ürünler (COGS > 0)
     */
    public function scopeWithCost(Builder $query): Builder
    {
        return $query->where('mp_products.cogs', '>', 0);
    }

    /**
     * Maliyeti tanımlı olmayan ürünler
     */
    public function scopeWithoutCost(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->where('mp_products.cogs', '<=', 0)->orWhereNull('mp_products.cogs');
        });
    }
}
