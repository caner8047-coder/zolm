<?php

namespace App\Services;

use App\Models\MpOrder;
use Illuminate\Support\Facades\Log;

/**
 * Faz 5: Sipariş Detay ve Finansal Yolculuk Servisi
 * Siparişin komisyon, kargo, ödeme, cari ekstre ve iade geçmişini tek bir veri yapısında birleştirir.
 */
class OrderDetailsService
{
    /**
     * Siparişin tüm detaylarını (Röntgenini) derler ve döner.
     */
    public function getOrderDetails(int $orderId): ?array
    {
        try {
            // İlgili tüm tabloları Eager Load ile çekiyoruz (N+1 problemi olmaması için)
            $order = MpOrder::with(['period', 'auditLogs', 'settlement'])
                ->find($orderId);

            if (!$order) {
                return null;
            }

            // Siparişe bağlı Cari Ekstre hareketlerini (Transactions) çek // Relationship zaten tanımlı
            $transactions = $order->transactions()->orderBy('transaction_date', 'asc')->get();

            // 1. TEMEL BİLGİLER
            // Aynı sipariş numarasına sahip tüm ürünleri al (çoklu ürünlü sepet desteği)
            $siblingOrders = MpOrder::where('order_number', $order->order_number)
                ->where('id', '!=', $order->id)
                ->get(['id', 'barcode', 'stock_code', 'product_name', 'quantity', 'gross_amount']);

            $basicInfo = [
                'order_number'    => $order->order_number,
                'barcode'         => $order->barcode,
                'stock_code'      => $order->stock_code,
                'quantity'        => $order->quantity,
                'product_name'    => $order->product_name,
                'status'          => $order->status,
                'status_color'    => $order->status_color,
                'order_date'      => $order->order_date?->format('d.m.Y H:i'),
                'delivery_date'   => $order->delivery_date?->format('d.m.Y'),
                'is_flagged'      => $order->is_flagged,
                'sibling_items'   => $siblingOrders->map(fn($s) => [
                    'id'           => $s->id,
                    'barcode'      => $s->barcode,
                    'stock_code'   => $s->stock_code,
                    'product_name' => $s->product_name,
                    'quantity'     => $s->quantity,
                    'gross_amount' => (float) $s->gross_amount,
                ])->toArray(),
            ];

            $siblingOrders = MpOrder::where('order_number', $order->order_number)
                ->where('id', '!=', $order->id)
                ->get();
            $allOrders = collect([$order])->merge($siblingOrders);

            // ─── Tüm Siparişin Konsolide Finansalları ───
            $aggGrossAmount      = $allOrders->sum('gross_amount');
            $aggDiscountAmount   = $allOrders->sum(fn($o) => abs((float)$o->discount_amount));
            $aggCampaignDiscount = $allOrders->sum(fn($o) => abs((float)$o->campaign_discount));
            $aggCommissionAmount = $allOrders->sum(fn($o) => abs((float)$o->commission_amount));
            $aggCargoAmount      = $allOrders->sum(fn($o) => abs((float)$o->cargo_amount));
            $aggServiceFee       = $allOrders->sum(fn($o) => abs((float)$o->service_fee));
            $aggExpectedNet      = $allOrders->sum('net_hakedis');
            $aggCogs             = $allOrders->sum('cogs_at_time');
            $aggPackaging        = $allOrders->sum('packaging_cost_at_time');
            $aggVatBalance       = $allOrders->sum('vat_balance');

            // ─── Stopaj Konsolidasyonu ───
            $stopajRate = \App\Models\MpFinancialRule::getRuleFloat('stopaj_rate') ?: 0.01;
            $defaultVatRate = \App\Models\MpFinancialRule::getRuleFloat('default_product_vat_rate') ?: 0.20;
            
            $actualStopajSum = $allOrders->sum(function($o) use ($stopajRate, $defaultVatRate) {
                $actualStopaj = abs((float) $o->withholding_tax);
                if ($actualStopaj <= 0 && !in_array($o->status, ['İptal Edildi'])) {
                    $productVatRate = $defaultVatRate;
                    if ($o->barcode) {
                        $matchedProduct = \App\Models\MpProduct::where('barcode', $o->barcode)->first();
                        if ($matchedProduct && $matchedProduct->vat_rate !== null) {
                            $productVatRate = (float) $matchedProduct->vat_rate / 100;
                        }
                    }
                    $gross = (float) $o->gross_amount;
                    $discounts = abs((float) $o->discount_amount) + abs((float) $o->campaign_discount);
                    $discountedGross = max(0, $gross - $discounts);
                    $vatExcludedBase = $discountedGross / (1 + $productVatRate);
                    return round($vatExcludedBase * $stopajRate, 2);
                }
                return $actualStopaj;
            });


        // 2. FİNANSAL ÖZET (Konsolide sipariş kayıtlarından)
        $financials = [
            'gross_amount'      => $aggGrossAmount,
            'discount_amount'   => $aggDiscountAmount,
            'campaign_discount' => $aggCampaignDiscount,
            'commission_amount' => $aggCommissionAmount,
            'cargo_company'     => $order->cargo_company,
            'cargo_amount'      => $aggCargoAmount,
            'service_fee'       => $aggServiceFee,
            'withholding_tax'   => $actualStopajSum,
            // Toplam Kesinti: Komisyon + Kargo + Hizmet + Stopaj
            'total_deductions'  => $aggCommissionAmount + $aggCargoAmount + $aggServiceFee + $actualStopajSum, 
            // Trendyol'un vaat ettiği net bakiye toplamı
            'expected_net'      => $aggExpectedNet,
        ];

            // 3. ÖDEME DURUMU (Settlement Tablosundan & Hesaplamalardan)
            $settlementData = [
                'has_settlement'   => false,
                'due_date'         => null,
                'settlement_date'  => null,
                'seller_hakedis'   => 0.0,
                'is_reconciled'    => false,
                'expected_date'    => $order->expected_payment_date?->format('d.m.Y'),
                'is_paid'          => $order->is_paid,
            ];

            $settlement = $order->settlement;
            
            // Fallback: FK ilişkisi yoksa, order_number ile arama yap
            $allSettlements = \App\Models\MpSettlement::where('order_number', $order->order_number)
                ->orderBy('id', 'asc') // Kronolojik işlenmiş sıraya göre
                ->get();

            $settlement = null;
            if ($allSettlements->count() > 0) {
                // Tarihler ve referans için ilk gerçek settlement kaydını alalım (veya en sonuncuyu)
                $settlement = $allSettlements->whereNotNull('due_date')->sortByDesc('due_date')->first() ?? $allSettlements->first();
                
                // FK güncellemesi
                if (!$settlement->order_id) {
                    $allSettlements->each(function($s) use($order) { 
                        if (!$s->order_id) $s->update(['order_id' => $order->id]); 
                    });
                }
            }
            
            if ($settlement && $allSettlements->count() > 0) {
                // GERÇEK NET BANKA TAHSİLATI = Bütün işlemlerin toplamı (Örn: Satış + İade + Tazminat)
                $sellerHakedis = (float) $allSettlements->sum('seller_hakedis');
                // Tüm sibling siparişlerin net hakedişini topla (çoklu ürünlü sepet desteği)
                $expectedNet   = (float) $aggExpectedNet;

                // Akıllı Mutabakat: ödenen tutar beklentinin en az %90'ı ise "mutabık" say.
                $variance      = $expectedNet > 0 ? ($sellerHakedis - $expectedNet) : 0;
                $isReconciled  = $settlement->is_reconciled  // DB'de manuel mutabık işaretlendiyse
                    || ($sellerHakedis >= $expectedNet * 0.90); // ya da %90+ ödendiyse

                // ── QUANTITY / ÇOK ÜRÜNLÜ SEPET KONTROL ──
                // Sipariş Excel'inde quantity=N tek satır, Ödeme Excel'inde adet başına ayrı satır
                // Farklı fiyatlı ürünler farklı vadelerde ödeniyor olabilir
                if (!$isReconciled && $sellerHakedis > 0 && $sellerHakedis < $expectedNet) {
                    $totalQty = $allOrders->sum('quantity');
                    $positiveRows = $allSettlements->filter(fn($s) => (float) $s->seller_hakedis > 0)->count();
                    if ($totalQty > 1 && $positiveRows < $totalQty) {
                        // Ödeme Excel'inde henüz tüm ürünlerin kaydı yok → kısmi yükleme
                        $isReconciled = true;
                    }
                }

                $hasPartialRefund = $allSettlements->contains(function($s) {
                    $type = mb_strtolower($s->transaction_type);
                    return (float) $s->seller_hakedis < 0 && (str_contains($type, 'iade') || str_contains($type, 'iptal'));
                });

                $settlementDetails = $allSettlements->filter(function ($s) {
                    return abs((float) $s->seller_hakedis) > 0;
                })->map(function ($s) {
                    return [
                        'type'   => $s->transaction_type ?: 'Bilinmeyen İşlem',
                        'amount' => (float) $s->seller_hakedis,
                        'date'   => $s->settlement_date ? $s->settlement_date->format('d.m.Y') : ($s->due_date ? $s->due_date->format('d.m.Y') : 'Vade: Bilinmiyor'),
                    ];
                })->values()->toArray();

                // Gerçek banka ödeme tarihini hesapla (Trendyol takvimi)
                $calculatedPaymentDate = null;
                if ($settlement->due_date) {
                    $vade = \Carbon\Carbon::parse($settlement->due_date);
                    $dow = $vade->dayOfWeekIso;
                    if (in_array($dow, [1, 2, 3])) {
                        $calculatedPaymentDate = $vade->copy()->startOfWeek()->addDays(3); // Perşembe
                    } else {
                        $calculatedPaymentDate = $vade->copy()->next(\Carbon\Carbon::MONDAY); // Pazartesi
                    }
                }

                $settlementData = array_merge($settlementData, [
                    'has_settlement'     => true,
                    'due_date'           => $settlement->due_date?->format('d.m.Y'),
                    'settlement_date'    => $calculatedPaymentDate ? $calculatedPaymentDate->format('d.m.Y') : ($settlement->settlement_date?->format('d.m.Y')),
                    'seller_hakedis'     => $sellerHakedis,
                    'expected_net'       => $expectedNet,
                    'variance'           => round($variance, 2),
                    'is_reconciled'      => $isReconciled,
                    'settlement_details' => $settlementDetails,
                    'has_partial_refund' => $hasPartialRefund,
                ]);
            }

            // 4. İADE VE EKSTRA KESİNTİLER / CARİ (Transactions Tablosundan)
            // İade edilen siparişin lojistik cezası veya operasyonel cezalar vs.
            $extraDeductions = [];
            $refunds = [];
            $totalExtraDebt = 0;
            $totalRefundCredit = 0;

            foreach ($transactions as $tx) {
                $type = mb_strtolower($tx->transaction_type);
                $isDebt = (float) $tx->debt > 0;
                $isCredit = (float) $tx->credit > 0;

                // İade komisyonu hesaba dönmüş mü veya kargo tazminatı yatmış mı?
                if ($isCredit && (str_contains($type, 'komisyon') || str_contains($type, 'iade') || str_contains($type, 'tazmin'))) {
                    $refunds[] = [
                        'date'   => $tx->transaction_date?->format('d.m.Y'),
                        'type'   => $tx->transaction_type,
                        'desc'   => $tx->description,
                        'amount' => (float) $tx->credit,
                    ];
                    $totalRefundCredit += (float) $tx->credit;
                }

                // Ekstra kargo cezası, operasyonel ceza, başarısız teslimat (Siparişteki normal kargo harici)
                if ($isDebt && (str_contains($type, 'ceza') || str_contains($type, 'ağır') || str_contains($type, 'iade kargo') || str_contains($type, 'başarısız'))) {
                    $extraDeductions[] = [
                        'date'   => $tx->transaction_date?->format('d.m.Y'),
                        'type'   => $tx->transaction_type,
                        'desc'   => $tx->description,
                        'amount' => (float) $tx->debt,
                    ];
                    $totalExtraDebt += (float) $tx->debt;
                }
            }

            // İade Lojistik Zararı Özeti (Accessor'dan)
            $returnLogisticLoss = $order->return_logistic_loss;

            // 5. DENETİM LOGLARI (Audit Logs)
            // Bu siparişle ilgili AuditEngine'in bulduğu hatalar
            $auditFindings = $order->auditLogs->map(function ($log) {
                return [
                    'severity'    => $log->severity,
                    'rule_code'   => $log->rule_code,
                    'title'       => $log->title,
                    'description' => $log->description,
                    'diff'        => (float) $log->difference,
                ];
            })->toArray();

            // 6. ÖZET SONUÇ (Gerçek Net Kazanç / Kayıp)
            if ($order->status === 'İptal Edildi') {
                $absoluteNetProfit = 0.0;
                $vatPayable = 0.0;
                $costOfGoods = 0.0;
                $baseRevenue = 0.0;
                $stopajVal = 0.0;
            } elseif ($order->status === 'İade Edildi') {
                // Sadece yanık maliyet (lojistik zararı)
                $absoluteNetProfit = -abs((float) $order->return_logistic_loss);
                $vatPayable = 0.0;
                $costOfGoods = 0.0;
                $baseRevenue = 0.0;
                $stopajVal = 0.0;
            } else {
                // Eğer sipariş ödendiyse Bankaya Yatan (Settlement) - (COGS + Ambalaj + Ekstra Cezalar)
                // Ödenmediyse Beklenen (Net Hakediş) - (...)
                $baseRevenue = $settlementData['has_settlement'] ? $settlementData['seller_hakedis'] : $financials['expected_net'];
                $costOfGoods = (float) $aggCogs + (float) $aggPackaging;
                
                // Gerçek Net Kâr = Tahsilat - Tüm Maliyetler - Ekstra Cezalar + Komisyon İadeleri
                $absoluteNetProfit = $baseRevenue - $costOfGoods - $totalExtraDebt + $totalRefundCredit;
                
                // KDV Yükümlülüğü (sadece ayarlardan açıksa hesapla)
                $svc = new \App\Services\MpSettingsService();
                $kdvEnabled = $svc->isKdvEnabled();
                $vatPayable = $kdvEnabled ? (float) $aggVatBalance : 0;
                if ($vatPayable > 0) {
                    $absoluteNetProfit -= $vatPayable;
                }
                
                // Stopajı düş
                $stopajVal = $actualStopajSum;
                $absoluteNetProfit -= $stopajVal;
            }

            $summary = [
                'base_revenue'        => $baseRevenue,
                'cost_of_goods'       => $costOfGoods,
                'total_extra_debt'    => $totalExtraDebt,
                'total_refund_credit' => $totalRefundCredit,
                'vat_advantage'       => ($kdvEnabled ?? false) ? ($vatPayable < 0 ? abs($vatPayable) : 0) : 0,
                'vat_payable'         => ($kdvEnabled ?? false) ? ($vatPayable > 0 ? $vatPayable : 0) : 0,
                'stopaj_deduction'    => $stopajVal,
                'absolute_net_profit' => round($absoluteNetProfit, 2),
                'is_loss'             => $absoluteNetProfit < 0
            ];

            return [
                'id'               => $order->id,
                'basic'            => $basicInfo,
                'financials'       => $financials,
                'settlement'       => $settlementData,
                'extra_deductions' => $extraDeductions,
                'refunds'          => $refunds,
                'return_loss_sum'  => $returnLogisticLoss,
                'audits'           => $auditFindings,
                'summary'          => $summary,
                'raw_status'       => $order->status,
            ];

        } catch (\Exception $e) {
            Log::error("OrderDetailsService Error [Order ID: {$orderId}]: " . $e->getMessage());
            return null;
        }
    }
}
