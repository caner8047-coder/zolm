<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class MpOrder extends Model
{
    protected ?array $operationalSnapshotCache = null;
    protected ?array $rawSnapshotCache = null;
    protected bool $resolvedProductCacheLoaded = false;
    protected ?MpProduct $resolvedProductCache = null;

    protected $fillable = [
        'period_id', 'store_id', 'legal_entity_id', 'order_number', 'barcode', 'stock_code',
        'product_name', 'quantity',
        'order_date', 'delivery_date', 'payment_date', 'status',
        'source_marketplace',
        'list_price', 'sale_price', 'gross_amount', 'discount_amount', 'campaign_discount',
        'commission_rate', 'commission_amount', 'commission_tax',
        'cargo_company', 'cargo_desi', 'cargo_amount', 'cargo_tax',
        'service_fee', 'withholding_tax', 'net_hakedis',
        'product_vat_rate', 'cogs_at_time', 'packaging_cost_at_time', 'own_cargo_cost_at_time',
        'calculated_net_profit', 'is_flagged', 'is_reconciled', 'raw_data',
        'erp_pushed_at', 'erp_status', 'erp_response', 'projected_at',
        // Fuzzy match
        'matched_product_id', 'match_confidence', 'match_method',
    ];

    protected $casts = [
        'order_date'              => 'datetime',
        'delivery_date'           => 'datetime',
        'payment_date'            => 'date',
        'projected_at'            => 'datetime',
        'quantity'                => 'integer',
        'list_price'              => 'decimal:2',
        'sale_price'              => 'decimal:2',
        'gross_amount'            => 'decimal:2',
        'discount_amount'         => 'decimal:2',
        'campaign_discount'       => 'decimal:2',
        'commission_rate'         => 'decimal:2',
        'commission_amount'       => 'decimal:2',
        'commission_tax'          => 'decimal:2',
        'cargo_desi'              => 'decimal:2',
        'cargo_amount'            => 'decimal:2',
        'cargo_tax'               => 'decimal:2',
        'service_fee'             => 'decimal:2',
        'withholding_tax'         => 'decimal:2',
        'net_hakedis'             => 'decimal:2',
        'product_vat_rate'        => 'decimal:2',
        'cogs_at_time'            => 'decimal:2',
        'packaging_cost_at_time'  => 'decimal:2',
        'own_cargo_cost_at_time'  => 'decimal:2',
        'calculated_net_profit'   => 'decimal:2',
        'is_flagged'              => 'boolean',
        'is_reconciled'           => 'boolean',
        'raw_data'                => 'array',
        'erp_pushed_at'           => 'datetime',
        'match_confidence'        => 'decimal:3',
    ];

    // ─── Relationships ──────────────────────────────────────────

    public function period(): BelongsTo
    {
        return $this->belongsTo(MpPeriod::class, 'period_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class, 'legal_entity_id');
    }

    /**
     * Fuzzy match ile eşleştirilen ürün (ProductMatcherService tarafından set edilir)
     */
    public function matchedProduct(): BelongsTo
    {
        return $this->belongsTo(MpProduct::class, 'matched_product_id');
    }

    public function operationalOrder(): BelongsTo
    {
        return $this->belongsTo(MpOperationalOrder::class, 'order_number', 'order_number');
    }

    public function operationalItems(): HasMany
    {
        return $this->hasMany(MpOperationalOrderItem::class, 'order_number', 'order_number');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(MpAuditLog::class, 'order_id');
    }

    /**
     * Cari Hesap Ekstre'sindeki eşleşen işlemler
     */
    public function transactions()
    {
        $query = MpTransaction::where(function ($transactionQuery) {
            $transactionQuery
                ->where('order_number', $this->order_number)
                ->orWhere('description', 'like', '%' . $this->order_number . '%');
        });
        $userId = $this->resolveOwnerUserId();

        if ($userId) {
            return $query->whereHas('period', fn($periodQuery) => $periodQuery->where('user_id', $userId));
        }

        return $query->where('period_id', $this->period_id);
    }

    /**
     * Ürün maliyet kaydı (mp_products tablosundan)
     */
    public function product()
    {
        $relation = $this->belongsTo(MpProduct::class, 'barcode', 'barcode');
        $userId = $this->resolveOwnerUserId();

        return $userId ? $relation->where('user_id', $userId) : $relation;
    }

    /**
     * Ödeme Detay (Hakediş) kaydı
     */
    public function settlement()
    {
        return $this->hasOne(MpSettlement::class, 'order_id')->latestOfMany('id');
    }

    /**
     * Siparişe ait tüm ödeme detay satırları
     */
    public function settlements(): HasMany
    {
        return $this->hasMany(MpSettlement::class, 'order_id');
    }

    // ─── Scopes ─────────────────────────────────────────────────

    public function scopeDelivered($query)
    {
        return $query->where('status', 'Teslim Edildi');
    }

    public function scopeReturned($query)
    {
        return $query->where('status', 'İade Edildi');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'İptal Edildi');
    }

    public function scopeInTransit($query)
    {
        return $query->where('status', 'Kargoda');
    }

    public function scopeFlagged($query)
    {
        return $query->where('is_flagged', true);
    }

    public function scopeByOrderNumber($query, string $orderNumber)
    {
        return $query->where('order_number', $orderNumber);
    }

    public function scopeByBarcode($query, string $barcode)
    {
        return $query->where('barcode', $barcode);
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('order_number', 'like', "%{$term}%")
              ->orWhere('barcode', 'like', "%{$term}%")
              ->orWhere('product_name', 'like', "%{$term}%");
        });
    }

    // ─── Accessors (5N1K Hesaplamaları) ─────────────────────────

    /**
     * NE? Toplam kesintiler
     */
    public function getTotalDeductionsAttribute(): float
    {
        return (float) $this->commission_amount
             + (float) $this->cargo_amount
             + (float) $this->withholding_tax
             + (float) $this->service_fee;
    }

    /**
     * NE? Komisyon oranını geri hesapla
     */
    public function getCalculatedCommissionRateAttribute(): float
    {
        if ((float) $this->gross_amount <= 0) return 0;
        return round(((float) $this->commission_amount / (float) $this->gross_amount) * 100, 2);
    }

    /**
     * NE? KDV Asimetrisi
     * Satış KDV - (Komisyon KDV + Kargo KDV)
     */
    public function getVatBalanceAttribute(): float
    {
        $userId = $this->resolveOwnerUserId();
        $svc = new \App\Services\MpSettingsService($userId);

        if (in_array($this->status, ['İptal Edildi', 'İade Edildi'])) {
            // İptal/İade durumunda satış gerçekleşmediği için e-Arşiv fatura iptal edilir.
            // Devlete ödenecek Satış KDV'si doğmaz.
            $salesVat = 0;
        } else {
            $defaultVatRate = $svc->getDefaultProductVatRate();
            $vatRate = $defaultVatRate;
            if ($this->resolved_product_vat_rate !== null && (float) $this->resolved_product_vat_rate > 0) {
                $vatRate = (float) $this->resolved_product_vat_rate / 100;
            }
            $salesVat = (float) $this->gross_amount * $vatRate / (1 + $vatRate);
        }

        $expenseVatRate = $svc->getExpenseVatRate();
        $commissionVat = abs((float) $this->commission_amount) * $expenseVatRate;
        $cargoVat      = abs((float) $this->cargo_amount) * $expenseVatRate;

        // Pozitif sonuç = Devlete ödenecek net KDV (Kârdan düşmeli)
        // Negatif sonuç = KDV alacağı / avantajı (Kâra eklenmeli)
        return round($salesVat - $commissionVat - $cargoVat, 2);
    }

    /**
     * NE? Gerçek Net Kâr (Birim İktisadı)
     * Hakediş - COGS - Ambalaj + KDV Avantajı
     */
    public function getRealNetProfitAttribute(): float
    {
        if ($this->status === 'İptal Edildi') {
            return 0;
        }

        if ($this->status === 'İade Edildi') {
            return -abs($this->return_logistic_loss);
        }

        return (float) (new \App\Services\UnitEconomicsService())
            ->calculateForOrder($this)['real_net_profit'];
    }

    /**
     * NEDEN? Bu sipariş zararda mı?
     */
    public function getIsBleedingAttribute(): bool
    {
        return $this->real_net_profit < 0;
    }

    /**
     * NEDEN? İade ise toplam lojistik zararı
     * Gidiş kargo (yanık maliyet) + dönüş kargo cezası
     */
    public function getReturnLogisticLossAttribute(): float
    {
        if ($this->status !== 'İade Edildi') return 0;

        // Gidiş kargo yanık maliyet
        $sunkCost = (float) $this->cargo_amount;

        // Dönüş kargo — Cari Ekstre'den aranır
        $returnCargo = $this->transactions()
            ->where('transaction_type', 'like', '%İade Kargo%')
            ->sum('debt');

        return round($sunkCost + $returnCargo, 2);
    }

    /**
     * Status badge rengi
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'Teslim Edildi' => 'green',
            'İade Edildi'   => 'red',
            'İptal Edildi'  => 'gray',
            'Kargoda'       => 'yellow',
            default         => 'blue',
        };
    }

    public function getResolvedBarcodeAttribute(): ?string
    {
        if (filled($this->barcode)) {
            return $this->barcode;
        }

        $rawBarcode = $this->resolveRawSnapshot()['barcode'] ?? null;
        if (filled($rawBarcode)) {
            return $rawBarcode;
        }

        $snapshotBarcode = $this->resolveOperationalSnapshot()['barcode'] ?? null;
        if (filled($snapshotBarcode)) {
            return $snapshotBarcode;
        }

        return $this->resolveMatchedProduct()?->barcode;
    }

    public function getResolvedStockCodeAttribute(): ?string
    {
        if (filled($this->stock_code)) {
            return $this->stock_code;
        }

        $rawStockCode = $this->resolveRawSnapshot()['stock_code'] ?? null;
        if (filled($rawStockCode)) {
            return $rawStockCode;
        }

        $snapshotStockCode = $this->resolveOperationalSnapshot()['stock_code'] ?? null;
        if (filled($snapshotStockCode)) {
            return $snapshotStockCode;
        }

        return $this->resolveMatchedProduct()?->stock_code;
    }

    public function getResolvedProductNameAttribute(): ?string
    {
        if (filled($this->product_name)) {
            return $this->product_name;
        }

        $rawProductName = $this->resolveRawSnapshot()['product_name'] ?? null;
        if (filled($rawProductName)) {
            return $rawProductName;
        }

        $snapshotProductName = $this->resolveOperationalSnapshot()['product_name'] ?? null;
        if (filled($snapshotProductName)) {
            return $snapshotProductName;
        }

        return $this->resolveMatchedProduct()?->product_name;
    }

    public function getResolvedQuantityAttribute(): int
    {
        if ((int) $this->quantity > 0) {
            return (int) $this->quantity;
        }

        $rawQuantity = (int) ($this->resolveRawSnapshot()['quantity'] ?? 0);
        if ($rawQuantity > 0) {
            return $rawQuantity;
        }

        return max(1, (int) ($this->resolveOperationalSnapshot()['quantity'] ?? 1));
    }

    public function getResolvedDeliveryDateAttribute()
    {
        return $this->delivery_date
            ?? ($this->resolveRawSnapshot()['delivery_date'] ?? null)
            ?? ($this->resolveOperationalSnapshot()['delivery_date'] ?? null);
    }

    public function getResolvedProductVatRateAttribute(): ?float
    {
        if ($this->product_vat_rate !== null && (float) $this->product_vat_rate > 0) {
            return (float) $this->product_vat_rate;
        }

        return $this->resolveMatchedProduct()?->vat_rate;
    }

    public function getResolvedOperationalCommissionRateAttribute(): ?float
    {
        $rate = $this->resolveOperationalSnapshot()['item']?->commission_rate ?? null;

        return ($rate !== null && (float) $rate > 0) ? (float) $rate : null;
    }

    public function getResolvedSettlementCommissionRateAttribute(): ?float
    {
        $rates = $this->resolveSettlementCollection()
            ->map(fn(MpSettlement $settlement) => (float) ($settlement->commission_rate ?? 0))
            ->filter(fn(float $rate) => $rate > 0)
            ->values();

        if ($rates->isEmpty()) {
            return null;
        }

        return round((float) $rates->avg(), 2);
    }

    public function getResolvedCogsAtTimeAttribute(): float
    {
        if ((float) ($this->cogs_at_time ?? 0) > 0) {
            return (float) $this->cogs_at_time;
        }

        $product = $this->resolveMatchedProduct();
        if (!$product) {
            return 0.0;
        }

        return round((float) ($product->cogs ?? 0) * $this->resolved_quantity, 2);
    }

    public function getResolvedPackagingCostAtTimeAttribute(): float
    {
        if ((float) ($this->packaging_cost_at_time ?? 0) > 0) {
            return (float) $this->packaging_cost_at_time;
        }

        $product = $this->resolveMatchedProduct();
        if (!$product) {
            return 0.0;
        }

        return round((float) ($product->packaging_cost ?? 0) * $this->resolved_quantity, 2);
    }

    public function getResolvedOwnCargoCostAtTimeAttribute(): float
    {
        if ((float) ($this->own_cargo_cost_at_time ?? 0) > 0) {
            return (float) $this->own_cargo_cost_at_time;
        }

        $product = $this->resolveMatchedProduct();
        if (!$product) {
            return 0.0;
        }

        return round((float) ($product->cargo_cost ?? 0) * $this->resolved_quantity, 2);
    }

    public function getCogsMissingReasonAttribute(): ?string
    {
        if ((float) $this->resolved_cogs_at_time > 0) {
            return null;
        }

        if (!filled($this->resolved_barcode) && !filled($this->resolved_stock_code) && !filled($this->resolved_product_name)) {
            return 'Sipariste barkod, stok kodu veya urun adi yok. Bu nedenle urun kutuphanesi ile eslesme kurulamiyor.';
        }

        $matchedProduct = $this->resolveMatchedProduct();
        if (!$matchedProduct) {
            return 'Sipariste urun bilgisi var ancak Urunler tablosunda eslesen kayit bulunamadi.';
        }

        if ((float) ($matchedProduct->cogs ?? 0) <= 0) {
            return 'Eslesen urun bulundu fakat urun kartindaki COGS bos ya da sifir.';
        }

        return 'COGS eslestirmesi kurulamadi.';
    }
    
    /**
     * Trendyol Vade Kurallarına Göre Gerçek Ödeme (Tahsilat) Tarihini Hesaplar
     * Kural: Pzt, Sal, Çarş -> O haftanın İlk Perşembe Günü
     *        Per, Cum, Cmt, Paz -> Sonraki haftanın İlk Pazartesi Günü
     *
     * Öncelik Sırası:
     *  1. Settlement.settlement_date (Trendyol raporu ile mutabık gerçek ödeme)
     *  2. Settlement.due_date üzerinden Trendyol algoritmasına göre hesaplanan tarih
     *  3. payment_date varsa (nadir) onu kullan
     */
    public function getExpectedPaymentDateAttribute()
    {
        $settlements = $this->resolveSettlementCollection();
        $positiveSettlements = $settlements->filter(fn($settlement) => (float) $settlement->seller_hakedis > 0);

        $latestSettlementDate = ($positiveSettlements->isNotEmpty() ? $positiveSettlements : $settlements)
            ->filter(fn($settlement) => $settlement->settlement_date !== null)
            ->sortByDesc(fn($settlement) => $settlement->settlement_date?->getTimestamp() ?? 0)
            ->first();

        if ($latestSettlementDate) {
            return $latestSettlementDate->settlement_date;
        }

        // 2. Vade Tarihi'ni önce Settlement'tan al, yoksa sipariş payment_date'ine bak
        $settlementForDueDate = ($positiveSettlements->isNotEmpty() ? $positiveSettlements : $settlements)
            ->filter(fn($settlement) => $settlement->due_date !== null)
            ->sortByDesc(fn($settlement) => $settlement->due_date?->getTimestamp() ?? 0)
            ->first();

        $vadeTarihi = $settlementForDueDate?->due_date ?? ($this->payment_date ? \Carbon\Carbon::parse($this->payment_date) : null);

        if (!$vadeTarihi) {
            return null; // Vade tarihi belli değil, hesaplanamaz
        }

        $vade = \Carbon\Carbon::parse($vadeTarihi);
        
        // Trendyol Algoritması: ISO gün numaraları (Pazartesi=1 ... Pazar=7)
        $dayOfWeek = $vade->dayOfWeekIso;

        if (in_array($dayOfWeek, [1, 2, 3])) {
            // Pazartesi, Salı, Çarşamba -> Aynı haftanın Perşembesi
            $expected = $vade->copy()->startOfWeek()->addDays(3);
        } else {
            // Perşembe, Cuma, Cumartesi, Pazar -> Takip eden haftanın Pazartesisi
            $expected = $vade->copy()->next(\Carbon\Carbon::MONDAY);
        }

        return $expected->startOfDay();
    }

    /**
     * Siparişin Parası Bankaya Yattı Mı?
     */
    public function getIsPaidAttribute(): bool
    {
        $settlements = $this->resolveSettlementCollection();
        $today = \Carbon\Carbon::today();

        $positiveSettlements = $settlements->filter(fn($settlement) => (float) $settlement->seller_hakedis > 0);

        if ($positiveSettlements->contains(function ($settlement) use ($today) {
            $effectiveDate = $settlement->settlement_date ?? $settlement->due_date;

            return $effectiveDate !== null
                && $effectiveDate->copy()->startOfDay()->lte($today);
        })) {
            return true;
        }

        // 2. Durum: Excel yok ama Tarih tabanlı varsayım.
        // Eğer hesaplanan 'expected_payment_date' şu anki günden küçük/eşitse, hesaba yatmış VARSAYILIR.
        $expected = $this->expected_payment_date;
        if ($expected && $expected->startOfDay()->lte(\Carbon\Carbon::today())) {
            return true;
        }

        return false;
    }

    protected function resolveOwnerUserId(): ?int
    {
        if ($this->relationLoaded('period')) {
            return $this->period?->user_id;
        }

        return $this->period()->value('user_id');
    }

    protected function resolveSettlementCollection()
    {
        $userId = $this->resolveOwnerUserId();
        $baseQuery = MpSettlement::query()
            ->where('order_number', $this->order_number)
            ->orderByRaw('transaction_date is null, transaction_date asc')
            ->orderBy('id');

        $settlements = collect();

        if ($this->relationLoaded('settlements') && $this->settlements->isNotEmpty()) {
            $settlements = $settlements->merge($this->settlements);
        }

        if ($this->exists) {
            $settlements = $settlements->merge(
                (clone $baseQuery)->where('order_id', $this->id)->get()
            );
        }

        $settlements = $settlements->merge(
            (clone $baseQuery)->where('period_id', $this->period_id)->get()
        );

        if ($userId) {
            $crossPeriod = (clone $baseQuery)
                ->where('user_id', $userId)
                ->get();

            if ($crossPeriod->isNotEmpty()) {
                if ($this->exists) {
                    $crossPeriod
                        ->whereNull('order_id')
                        ->each(fn(MpSettlement $settlement) => $settlement->update(['order_id' => $this->id]));
                }
                $settlements = $settlements->merge($crossPeriod);
            }
        }

        return $settlements
            ->unique('id')
            ->sortBy([
                fn(MpSettlement $settlement) => $settlement->transaction_date?->getTimestamp() ?? PHP_INT_MAX,
                fn(MpSettlement $settlement) => $settlement->id,
            ])
            ->values();
    }

    protected function resolveOperationalSnapshot(): array
    {
        if ($this->operationalSnapshotCache !== null) {
            return $this->operationalSnapshotCache;
        }

        $operationalOrder = $this->relationLoaded('operationalOrder')
            ? $this->operationalOrder
            : $this->operationalOrder()->with('items')->first();

        $items = $operationalOrder?->relationLoaded('items')
            ? $operationalOrder->items
            : collect();

        if ($items->isEmpty()) {
            $items = $this->relationLoaded('operationalItems')
                ? $this->operationalItems
                : $this->operationalItems()->get();
        }

        $matchedItem = $this->findMatchingOperationalItem($items);

        return $this->operationalSnapshotCache = [
            'order'         => $operationalOrder,
            'items'         => $items,
            'item'          => $matchedItem,
            'barcode'       => $matchedItem?->barcode,
            'stock_code'    => $matchedItem?->stock_code,
            'product_name'  => $matchedItem?->product_name,
            'quantity'      => $matchedItem?->quantity,
            'delivery_date' => $operationalOrder?->delivery_date,
        ];
    }

    protected function resolveRawSnapshot(): array
    {
        if ($this->rawSnapshotCache !== null) {
            return $this->rawSnapshotCache;
        }

        $raw = is_array($this->raw_data) ? $this->raw_data : [];

        return $this->rawSnapshotCache = [
            'barcode' => $this->extractRawString($raw, [
                'barcode', 'Barcode', 'Barkod', 'Ürün Barkodu', 'Satıcı Barkodu',
            ]),
            'stock_code' => $this->extractRawString($raw, [
                'stock_code', 'stockCode', 'Stok Kodu', 'Stock Code', 'Model Kodu', 'Satıcı Ürün Kodu',
            ]),
            'product_name' => $this->extractRawString($raw, [
                'product_name', 'productName', 'Ürün Adı', 'Ürün', 'Ürün Adı / Açıklama', 'Product Name',
            ]),
            'quantity' => $this->extractRawInteger($raw, [
                'quantity', 'Adet', 'Miktar', 'Ürün Adedi',
            ]),
            'delivery_date' => $this->extractRawDate($raw, [
                'delivery_date', 'Teslim Tarihi', 'Delivery Date', 'Teslimat Tarihi',
            ]),
        ];
    }

    protected function findMatchingOperationalItem(Collection $items): ?MpOperationalOrderItem
    {
        if ($items->isEmpty()) {
            return null;
        }

        if (filled($this->barcode)) {
            $barcodeMatch = $items->firstWhere('barcode', $this->barcode);
            if ($barcodeMatch) {
                return $barcodeMatch;
            }
        }

        if (filled($this->stock_code)) {
            $stockCodeMatch = $items->firstWhere('stock_code', $this->stock_code);
            if ($stockCodeMatch) {
                return $stockCodeMatch;
            }
        }

        if (filled($this->product_name)) {
            $normalizedName = $this->normalizeOperationalText($this->product_name);
            $nameMatch = $items->first(function (MpOperationalOrderItem $item) use ($normalizedName) {
                return $this->normalizeOperationalText((string) $item->product_name) === $normalizedName;
            });

            if ($nameMatch) {
                return $nameMatch;
            }
        }

        if ($items->count() === 1) {
            return $items->first();
        }

        $quantityMatches = $items->filter(fn(MpOperationalOrderItem $item) => (int) $item->quantity === (int) $this->quantity);
        if ($quantityMatches->count() === 1) {
            return $quantityMatches->first();
        }

        return null;
    }

    protected function normalizeOperationalText(?string $value): string
    {
        return mb_strtolower(trim((string) $value));
    }

    protected function resolveMatchedProduct(): ?MpProduct
    {
        if ($this->resolvedProductCacheLoaded) {
            return $this->resolvedProductCache;
        }

        $this->resolvedProductCacheLoaded = true;

        $userId = $this->resolveOwnerUserId();
        if (!$userId) {
            return $this->resolvedProductCache = null;
        }

        $raw = $this->resolveRawSnapshot();
        $snapshot = $this->resolveOperationalSnapshot();
        $query = MpProduct::query()->where('user_id', $userId);
        $barcode = filled($this->barcode)
            ? $this->barcode
            : ($raw['barcode'] ?? ($snapshot['barcode'] ?? null));
        $stockCode = filled($this->stock_code)
            ? $this->stock_code
            : ($raw['stock_code'] ?? ($snapshot['stock_code'] ?? null));
        $productName = filled($this->product_name)
            ? $this->product_name
            : ($raw['product_name'] ?? ($snapshot['product_name'] ?? null));

        if (filled($barcode)) {
            $product = (clone $query)->where('barcode', $barcode)->first();
            if ($product) {
                return $this->resolvedProductCache = $product;
            }
        }

        if (filled($stockCode)) {
            $product = (clone $query)->where('stock_code', $stockCode)->first();
            if ($product) {
                return $this->resolvedProductCache = $product;
            }
        }

        if (filled($productName)) {
            $matches = (clone $query)
                ->where('product_name', $productName)
                ->limit(2)
                ->get();

            if ($matches->count() === 1) {
                return $this->resolvedProductCache = $matches->first();
            }
        }

        return $this->resolvedProductCache = null;
    }

    protected function extractRawString(array $raw, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = trim((string) ($raw[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    protected function extractRawInteger(array $raw, array $keys): ?int
    {
        foreach ($keys as $key) {
            $value = $raw[$key] ?? null;
            if ($value === null || $value === '') {
                continue;
            }

            if (is_numeric($value)) {
                return (int) $value;
            }

            $normalized = preg_replace('/[^0-9-]/', '', (string) $value);
            if ($normalized !== '') {
                return (int) $normalized;
            }
        }

        return null;
    }

    protected function extractRawDate(array $raw, array $keys)
    {
        foreach ($keys as $key) {
            $value = $raw[$key] ?? null;
            if ($value === null || $value === '' || $value === '-') {
                continue;
            }

            try {
                if (is_numeric($value)) {
                    return \Carbon\Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value));
                }

                return \Carbon\Carbon::parse($value);
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }
}
