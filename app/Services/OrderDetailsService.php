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
            ];

            // ─── Epik 8 / Düzeltme: Stopaj Yükü (Teorik veya Pratik) ───
        $actualStopaj = abs((float) $order->withholding_tax);
        if ($actualStopaj <= 0 && !in_array($order->status, ['İptal Edildi'])) {
            $stopajRate = \App\Models\MpFinancialRule::getRuleFloat('stopaj_rate') ?: 0.01;
            $defaultVatRate = \App\Models\MpFinancialRule::getRuleFloat('default_product_vat_rate') ?: 0.20;
            // Ürün KDV oranını MpProduct'tan al, yoksa sistem ayarını kullan
            $productVatRate = $defaultVatRate;
            if ($order->barcode) {
                $matchedProduct = \App\Models\MpProduct::where('barcode', $order->barcode)->first();
                if ($matchedProduct && $matchedProduct->vat_rate !== null) {
                    $productVatRate = (float) $matchedProduct->vat_rate / 100;
                }
            }
            $grossAmount = (float) $order->gross_amount;
            $totalDiscounts = abs((float) $order->discount_amount) + abs((float) $order->campaign_discount);
            $discountedGross = max(0, $grossAmount - $totalDiscounts);
            $vatExcludedBase = $discountedGross / (1 + $productVatRate);
            $actualStopaj = round($vatExcludedBase * $stopajRate, 2);
        }

        // 2. FİNANSAL ÖZET (Sipariş Kayıtlarından)
        $financials = [
            'gross_amount'      => (float) $order->gross_amount,
            'discount_amount'   => abs((float) $order->discount_amount),
            'campaign_discount' => abs((float) $order->campaign_discount),
            'commission_amount' => abs((float) $order->commission_amount),
            'cargo_company'     => $order->cargo_company,
            'cargo_amount'      => abs((float) $order->cargo_amount),
            'service_fee'       => abs((float) $order->service_fee),
            'withholding_tax'   => $actualStopaj, // Güncellendi
            // Toplam Kesinti: Komisyon + Kargo + Hizmet + Stopaj
            'total_deductions'  => $order->total_deductions, 
            // Trendyol'un vaat ettiği net bakiye
            'expected_net'      => (float) $order->net_hakedis,
        ];

            // 3. ÖDEME DURUMU (Settlement Tablosundan)
            $settlementData = null;
            $settlement = $order->settlement;
            
            // Fallback: FK ilişkisi yoksa, order_number ile arama yap
            if (!$settlement && $order->order_number) {
                $settlement = \App\Models\MpSettlement::where('order_number', $order->order_number)
                    ->whereNotNull('due_date')
                    ->orderByDesc('due_date')
                    ->first();
                
                // Eşleşen settlement bulduysak, FK'yı da güncelle (gelecek için)
                if ($settlement && !$settlement->order_id) {
                    $settlement->update(['order_id' => $order->id]);
                }
            }
            
            if ($settlement) {
                $settlementData = [
                    'due_date'         => $settlement->due_date?->format('d.m.Y'),
                    'settlement_date'  => $settlement->settlement_date?->format('d.m.Y'),
                    'seller_hakedis'   => (float) $settlement->seller_hakedis,
                    'is_reconciled'    => $settlement->is_reconciled,
                ];
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
                    'severity'   => $log->severity,
                    'title'      => $log->title,
                    'description'=> $log->description,
                    'diff'       => (float) $log->difference,
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
                $baseRevenue = $settlementData ? $settlementData['seller_hakedis'] : $financials['expected_net'];
                $costOfGoods = (float) ($order->cogs_at_time ?? 0) + (float) ($order->packaging_cost_at_time ?? 0);
                
                // Gerçek Net Kâr = Tahsilat - Tüm Maliyetler - Ekstra Cezalar + Komisyon İadeleri
                $absoluteNetProfit = $baseRevenue - $costOfGoods - $totalExtraDebt + $totalRefundCredit;
                
                // KDV Yükümlülüğü
                $vatPayable = (float) $order->vat_balance;
                if ($vatPayable > 0) {
                    $absoluteNetProfit -= $vatPayable;
                }
                
                // Stopajı düş
                $stopajVal = $actualStopaj;
                $absoluteNetProfit -= $stopajVal;
            }

            $summary = [
                'base_revenue'        => $baseRevenue,
                'cost_of_goods'       => $costOfGoods,
                'total_extra_debt'    => $totalExtraDebt,
                'total_refund_credit' => $totalRefundCredit,
                'vat_advantage'       => $vatPayable < 0 ? abs($vatPayable) : 0,
                'vat_payable'         => $vatPayable > 0 ? $vatPayable : 0,
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
