<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MpOperationalOrder extends Model
{
    protected $fillable = [
        'order_number', 'package_number', 'order_date', 'delivery_date',
        'deadline_date', 'cargo_delivery_date', 'invoice_date',
        'customer_name', 'customer_city', 'customer_district', 'customer_address', 'customer_phone',
        'billing_address', 'billing_name',
        'email', 'customer_age', 'customer_gender', 'customer_order_count',
        'country',
        'company_name', 'tax_office', 'tax_number',
        'cargo_company', 'tracking_number', 'cargo_code', 'status',
        'alt_delivery_status', 'second_delivery_status', 'second_tracking_number',
        'invoice_number', 'is_corporate_invoice', 'is_invoiced',
        'total_gross_amount', 'total_discount',
    ];

    protected $casts = [
        'order_date'          => 'datetime',
        'delivery_date'       => 'datetime',
        'deadline_date'       => 'datetime',
        'cargo_delivery_date' => 'datetime',
        'invoice_date'        => 'datetime',
        'total_gross_amount'  => 'decimal:2',
        'total_discount'      => 'decimal:2',
    ];

    // ─── Relationships ──────────────────────────────────────────

    public function items()
    {
        return $this->hasMany(MpOperationalOrderItem::class, 'operational_order_id');
    }

    /**
     * Muhasebe modülündeki finansal sipariş kayıtları
     * Bir operasyonel sipariş birden fazla MpOrder satırına sahip olabilir (her ürün ayrı satır)
     */
    public function financialOrders()
    {
        return $this->hasMany(MpOrder::class, 'order_number', 'order_number');
    }

    // ─── Accessors (Muhasebe verileri) ───────────────────────────

    /**
     * Muhasebeden: Toplam komisyon tutarı
     */
    public function getTotalCommissionAttribute(): float
    {
        return (float) $this->financialOrders->sum('commission_amount');
    }

    /**
     * Muhasebeden: Ortalama komisyon oranı
     */
    public function getAvgCommissionRateAttribute(): float
    {
        $orders = $this->financialOrders;
        if ($orders->isEmpty()) return 0;
        return round($orders->avg('commission_rate'), 2);
    }

    /**
     * Muhasebeden: Net hakediş
     */
    public function getTotalNetHakedisAttribute(): float
    {
        return (float) $this->financialOrders->sum('net_hakedis');
    }

    /**
     * Muhasebeden: Toplam kargo kesintisi
     */
    public function getTotalCargoAmountAttribute(): float
    {
        return (float) $this->financialOrders->sum('cargo_amount');
    }

    /**
     * Muhasebeden: Toplam gerçek net kâr
     */
    public function getTotalNetProfitAttribute(): float
    {
        return $this->financialOrders->sum(function ($order) {
            return $order->real_net_profit;
        });
    }

    /**
     * Muhasebeden: Sipariş kârlı mı?
     */
    public function getIsProfitableAttribute(): ?bool
    {
        if ($this->financialOrders->isEmpty()) return null;
        return $this->total_net_profit >= 0;
    }

    /**
     * Finansal uyarı: İade / İptal / Ceza tespiti
     * Muhasebe modülündeki kayıtların durumuna ve tutarlarına bakarak belirler.
     *
     * @return array{type: string|null, label: string|null, color: string|null}
     */
    public function getFinancialAlertAttribute(): array
    {
        $financials = $this->financialOrders;
        if ($financials->isEmpty()) {
            return ['type' => null, 'label' => null, 'color' => null];
        }

        // Muhasebe kayıtlarının durumlarına bak
        // NOT: Türkçe İ/ı karakterleri mb_strtolower ile sorun çıkarabilir,
        //      bu yüzden hem orijinal hem de lowercase kontrol yapıyoruz
        $statuses = $financials->pluck('status')->filter()->unique();

        // İade kontrolü: herhangi bir kayıt "İade" veya "iade" içeriyorsa
        $hasIade = $statuses->contains(function ($s) {
            return mb_stripos($s, 'İade') !== false
                || mb_stripos($s, 'iade') !== false;
        });
        if ($hasIade) {
            return [
                'type'  => 'iade',
                'label' => 'İade Edildi',
                'color' => 'bg-red-100 text-red-800 border-red-200',
            ];
        }

        // İptal kontrolü
        $hasIptal = $statuses->contains(function ($s) {
            return mb_stripos($s, 'İptal') !== false
                || mb_stripos($s, 'iptal') !== false;
        });
        if ($hasIptal) {
            return [
                'type'  => 'iptal',
                'label' => 'İptal Edildi',
                'color' => 'bg-orange-100 text-orange-800 border-orange-200',
            ];
        }

        // Net hakediş negatif ama iade/iptal yok → Ceza/Kesinti
        $netHakedis = (float) $financials->sum('net_hakedis');
        if ($netHakedis < 0) {
            return [
                'type'  => 'ceza',
                'label' => 'Ceza',
                'color' => 'bg-amber-100 text-amber-800 border-amber-200',
            ];
        }

        return ['type' => null, 'label' => null, 'color' => null];
    }

    // ─── Tahmini Karlılık Accessor'ları (MpProduct verileri) ─────

    /**
     * Tahmini toplam COGS (ürün maliyeti)
     * Items üzerinden barcode eşleşmesiyle MpProduct'tan çeker
     */
    public function getEstimatedCogsAttribute(): float
    {
        return $this->items->sum(function ($item) {
            $product = $item->relationLoaded('product') ? $item->product : null;
            if (!$product) return 0;
            return (float) $product->cogs * (int) $item->quantity;
        });
    }

    /**
     * Tahmini toplam kargo maliyeti (kendi kargo anlaşması)
     */
    public function getEstimatedCargoAttribute(): float
    {
        return $this->items->sum(function ($item) {
            $product = $item->relationLoaded('product') ? $item->product : null;
            if (!$product) return 0;
            return (float) ($product->cargo_cost ?? 0) * (int) $item->quantity;
        });
    }

    /**
     * Tahmini toplam ambalaj maliyeti
     */
    public function getEstimatedPackagingAttribute(): float
    {
        return $this->items->sum(function ($item) {
            $product = $item->relationLoaded('product') ? $item->product : null;
            if (!$product) return 0;
            return (float) ($product->packaging_cost ?? 0) * (int) $item->quantity;
        });
    }

    /**
     * Tahmini toplam komisyon (satır bazlı satış tutarı * komisyon oranı)
     */
    public function getEstimatedCommissionAttribute(): float
    {
        return $this->items->sum(function ($item) {
            $billableAmount = (float) ($item->billable_amount ?? 0);
            $salePrice = (float) ($item->sale_price ?? 0);
            $discount = (float) ($item->discount_amount ?? 0) + (float) ($item->trendyol_discount ?? 0);
            $commissionBase = $billableAmount > 0 ? $billableAmount : max(0, $salePrice - $discount);
            $commissionRate = (float) ($item->commission_rate ?? 0);

            if ($commissionBase <= 0 || $commissionRate <= 0) return 0;

            return $commissionBase * ($commissionRate / 100);
        });
    }

    /**
     * Tahmini net satış (Faturalanacak Tutar varsa onu kullanır, yoksa satış - indirimler)
     */
    public function getEstimatedNetSalesAttribute(): float
    {
        return $this->items->sum(function ($item) {
            $billableAmount = (float) ($item->billable_amount ?? 0);
            if ($billableAmount > 0) return $billableAmount;

            $salePrice = (float) ($item->sale_price ?? 0);
            $discount = (float) ($item->discount_amount ?? 0) + (float) ($item->trendyol_discount ?? 0);

            return max(0, $salePrice - $discount);
        });
    }

    /**
     * Tahmini net kâr = Net Satış - COGS - Kargo - Ambalaj - Komisyon
     */
    public function getEstimatedMarginAttribute(): ?float
    {
        $totalCost = $this->estimated_cogs
            + $this->estimated_cargo
            + $this->estimated_packaging
            + $this->estimated_commission;

        if ($totalCost <= 0) return null; // Maliyet verisi yok

        $salesTotal = $this->estimated_net_sales;
        return round($salesTotal - $totalCost, 2);
    }

    /**
     * Tahmini ROI yüzdesi = (Kâr / COGS) * 100
     */
    public function getEstimatedMarginPercentAttribute(): ?float
    {
        $cogs = $this->estimated_cogs;
        if ($cogs <= 0) return null;

        $margin = $this->estimated_margin;
        if ($margin === null) return null;

        return round(($margin / $cogs) * 100, 1);
    }

    /**
     * Maliyet verisi var mı kontrolü
     */
    public function getHasCostDataAttribute(): bool
    {
        return $this->items->contains(function ($item) {
            $product = $item->relationLoaded('product') ? $item->product : null;
            return $product && (float) $product->cogs > 0;
        });
    }
}
