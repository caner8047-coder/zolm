<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MpOrder extends Model
{
    protected $fillable = [
        'period_id', 'order_number', 'barcode', 'stock_code',
        'product_name', 'quantity',
        'order_date', 'delivery_date', 'payment_date', 'status',
        'list_price', 'sale_price', 'gross_amount', 'discount_amount', 'campaign_discount',
        'commission_rate', 'commission_amount', 'commission_tax',
        'cargo_company', 'cargo_desi', 'cargo_amount', 'cargo_tax',
        'service_fee', 'withholding_tax', 'net_hakedis',
        'product_vat_rate', 'cogs_at_time', 'packaging_cost_at_time',
        'calculated_net_profit', 'is_flagged', 'is_reconciled', 'raw_data',
        'erp_pushed_at', 'erp_status', 'erp_response',
    ];

    protected $casts = [
        'order_date'              => 'datetime',
        'delivery_date'           => 'datetime',
        'payment_date'            => 'date',
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
        'calculated_net_profit'   => 'decimal:2',
        'is_flagged'              => 'boolean',
        'is_reconciled'           => 'boolean',
        'raw_data'                => 'array',
        'erp_pushed_at'           => 'datetime',
    ];

    // ─── Relationships ──────────────────────────────────────────

    public function period(): BelongsTo
    {
        return $this->belongsTo(MpPeriod::class, 'period_id');
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
        return MpTransaction::where('order_number', $this->order_number)
            ->where('period_id', $this->period_id);
    }

    /**
     * Ürün maliyet kaydı (mp_products tablosundan)
     */
    public function product()
    {
        return $this->belongsTo(MpProduct::class, 'barcode', 'barcode')
                    ->where('user_id', $this->user_id);
    }

    /**
     * Ödeme Detay (Hakediş) kaydı
     */
    public function settlement()
    {
        return $this->hasOne(MpSettlement::class, 'order_id');
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
        $svc = new \App\Services\MpSettingsService();

        if (in_array($this->status, ['İptal Edildi', 'İade Edildi'])) {
            // İptal/İade durumunda satış gerçekleşmediği için e-Arşiv fatura iptal edilir.
            // Devlete ödenecek Satış KDV'si doğmaz.
            $salesVat = 0;
        } else {
            $defaultVatRate = $svc->getDefaultProductVatRate();
            $vatRate = $defaultVatRate;
            if ($this->barcode) {
                $matchedProduct = \App\Models\MpProduct::where('barcode', $this->barcode)->first();
                if ($matchedProduct && $matchedProduct->vat_rate !== null) {
                    $vatRate = (float) $matchedProduct->vat_rate / 100;
                }
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

        $hakedis  = (float) $this->net_hakedis;
        $cogs     = (float) ($this->cogs_at_time ?? 0);
        $packing  = (float) ($this->packaging_cost_at_time ?? 0);
        
        // KDV hesaplama açık mı kontrol et
        $svc = new \App\Services\MpSettingsService();
        $vatDeduction = 0;
        if ($svc->isKdvEnabled()) {
            $vatDeduction = $this->vat_balance; // KDV bakiyesi borç mu alacak mı?
        }
        
        // Hakediş - Ürün Maliyeti - Ambalaj Gideri - Ödenecek KDV (eğer açıksa)
        return round($hakedis - $cogs - $packing - $vatDeduction, 2);
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
        $returnCargo = MpTransaction::where('order_number', $this->order_number)
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
        // 1. Gerçek ödeme zaten yatmışsa, onu döndür
        if ($this->settlement && $this->settlement->settlement_date) {
            return $this->settlement->settlement_date;
        }

        // 2. Vade Tarihi'ni önce Settlement'tan al, yoksa sipariş payment_date'ine bak
        $vadeTarihi = $this->settlement?->due_date ?? ($this->payment_date ? \Carbon\Carbon::parse($this->payment_date) : null);

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
        // 1. Durum: Settlement tablosunda varsa, Trendyol bu siparişi kapatmış/ödemiştir.
        if ($this->settlement && $this->settlement->settlement_date) {
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
}
