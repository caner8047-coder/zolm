<?php

namespace App\Services;

use App\Models\MpPeriod;
use App\Models\MpOrder;
use App\Models\MpTransaction;
use App\Models\MpAuditLog;
use App\Models\MpSettlement;
use App\Services\MpSettingsService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Pazaryeri Muhasebe — Hata Denetim Motoru (Audit Engine)
 *
 * Import sonrası sipariş ve ekstre verilerini karşılaştırarak
 * hatalı kesintileri, barem aşımlarını ve kayıpları tespit eder.
 * Sonuçları mp_audit_logs tablosuna yazar.
 */
class AuditEngine
{
    protected MpSettingsService $settings;

    /**
     * Tüm denetim kuralları — yeni kural eklendiğinde buraya da eklenmeli.
     */
    public const RULES = [
        'checkStopaj',
        'checkBaremExcess',
        'checkCommissionRefund',
        'checkSunkCost',
        'checkHakedisDiscrepancy',
        'checkOperationalPenalties',
        'checkMultipleCart',
        'checkEarsivReminder',
        'checkHeavyCargoPenalty',
        'checkCommissionRefundTracking',
        'checkMissingPayments',
        'checkDelayedPayments',
        'checkTransactionDiscrepancy',
        'checkExtremeMargins',
        'checkCommissionMismatch',
        // ── Yeni Kurallar ──
        'checkMissingCogs',
        'checkPriceDrop',
        'checkNegativeHakedis',
        'checkCargoOverCost',
        'checkHighReturnRate',
        // ── Faz 3 Kurallar ──
        'checkCampaignLoss',
        'checkCommissionRateChange',
        'checkServiceFeeIncrease',
        'checkHighCancellationRate',
    ];

    /**
     * Her kuralın kullanıcıya gösterilecek metadata bilgisi.
     * Tooltip, kural listesi ve audit log badge'lerinde kullanılır.
     */
    public const RULE_META = [
        'checkStopaj' => [
            'code'     => 'STOPAJ',
            'title'    => 'Stopaj Doğrulaması',
            'tooltip'  => 'Brüt satış × %1 ≈ kesilen stopaj mı? Vergi dairesine fazla/eksik ödeme riskini tespit eder.',
            'severity' => 'warning',
            'category' => 'Vergi',
            'icon'     => '🏛️',
        ],
        'checkBaremExcess' => [
            'code'     => 'BAREM_ASIMI',
            'title'    => 'Barem Aşımı',
            'tooltip'  => 'Kargo tutarı, barem limitini aşıyor mu? Trendyol\'un fazla kestiği kargo bedellerini tespit eder.',
            'severity' => 'critical',
            'category' => 'Kargo',
            'icon'     => '📦',
        ],
        'checkCommissionRefund' => [
            'code'     => 'KOMISYON_IADE',
            'title'    => 'Komisyon İade Kontrolü',
            'tooltip'  => 'İade edilen siparişteki komisyon geri iade edilmiş mi? Trendyol\'un iade sonrası komisyonu geri vermemesini tespit eder.',
            'severity' => 'critical',
            'category' => 'Komisyon',
            'icon'     => '💸',
        ],
        'checkSunkCost' => [
            'code'     => 'YANIK_MALIYET',
            'title'    => 'Yanık/Batık Maliyet',
            'tooltip'  => 'İptal veya iade olan siparişteki batık maliyet (COGS + kargo). Geri dönüşü olmayan kayıpları gösterir.',
            'severity' => 'critical',
            'category' => 'Maliyet',
            'icon'     => '🔥',
        ],
        'checkHakedisDiscrepancy' => [
            'code'     => 'HAKEDIS_FARK',
            'title'    => 'Hakediş Tutarsızlığı',
            'tooltip'  => 'Beklenen hakediş vs gerçek yatan tutar farkını karşılaştırır. Kısmi iade ve illüzyon hakedişlerini tespit eder.',
            'severity' => 'critical',
            'category' => 'Ödeme',
            'icon'     => '🔍',
        ],
        'checkOperationalPenalties' => [
            'code'     => 'OPERASYONEL_CEZA',
            'title'    => 'Operasyonel Ceza/Tazminat',
            'tooltip'  => 'Trendyol tarafından kesilen operasyonel cezaları ve kayıp tazminatlarını tespit eder.',
            'severity' => 'critical',
            'category' => 'Ceza',
            'icon'     => '⚠️',
        ],
        'checkMultipleCart' => [
            'code'     => 'COKLU_SEPET',
            'title'    => 'Çoklu Sepet Tespiti',
            'tooltip'  => 'Aynı siparişteki çoklu ürün kartlarını tespit eder. Desi/kargo paylaştırmasının doğruluğunu kontrol eder.',
            'severity' => 'warning',
            'category' => 'Sipariş',
            'icon'     => '🛒',
        ],
        'checkEarsivReminder' => [
            'code'     => 'EARSIV_UYARI',
            'title'    => 'E-Arşiv Fatura Hatırlatması',
            'tooltip'  => 'İade edilen sipariş için E-Arşiv fatura iptali veya iade faturası çıkarılması gerektiğini hatırlatır.',
            'severity' => 'info',
            'category' => 'Fatura',
            'icon'     => '🧾',
        ],
        'checkHeavyCargoPenalty' => [
            'code'     => 'AGIR_KARGO_CEZA',
            'title'    => 'Ağır Kargo Cezası',
            'tooltip'  => '100 desi üstü ağır kargo taşıma bedelini tespit eder. Bilinen ceza tutarlarıyla karşılaştırır.',
            'severity' => 'critical',
            'category' => 'Kargo',
            'icon'     => '🏋️',
        ],
        'checkCommissionRefundTracking' => [
            'code'     => 'KOMISYON_IADE_TAKIP',
            'title'    => 'Komisyon İade Takibi',
            'tooltip'  => 'İade sonrası komisyon iade sürecini takip eder. Henüz iade edilmemiş komisyonları listeler.',
            'severity' => 'warning',
            'category' => 'Komisyon',
            'icon'     => '📋',
        ],
        'checkMissingPayments' => [
            'code'     => 'EKSIK_ODEME',
            'title'    => 'Eksik Yatan Ödeme',
            'tooltip'  => 'Trendyol\'un vadettiği net hakediş ile Ödeme Detay raporundaki fiilen yatırılan tutarı karşılaştırır.',
            'severity' => 'critical',
            'category' => 'Ödeme',
            'icon'     => '💰',
        ],
        'checkDelayedPayments' => [
            'code'     => 'KAYIP_ODEME',
            'title'    => 'Geciken/Kayıp Ödeme',
            'tooltip'  => 'Vadesi geçmiş ancak henüz ödenmemiş siparişleri tespit eder. Bankaya yatmayan ödemeleri gösterir.',
            'severity' => 'critical',
            'category' => 'Ödeme',
            'icon'     => '⏰',
        ],
        'checkTransactionDiscrepancy' => [
            'code'     => 'CARI_UYUMSUZLUK',
            'title'    => 'Cari-Hakediş Uyumu',
            'tooltip'  => 'Cari hesap ekstresi ile hakediş fatura tutarlarının uyumunu kontrol eder.',
            'severity' => 'warning',
            'category' => 'Fatura',
            'icon'     => '🏦',
        ],
        'checkExtremeMargins' => [
            'code'     => 'EXTREME_MARGIN',
            'title'    => 'Kritik Kâr Marjı İhlali',
            'tooltip'  => 'Kâr marjı %100\'ü aşan veya -%100\'ü geçen siparişleri tespit eder. Veri hatası veya fiyatlama sorunu göstergesi.',
            'severity' => 'critical',
            'category' => 'Kârlılık',
            'icon'     => '📊',
        ],
        'checkCommissionMismatch' => [
            'code'     => 'KOMISYON_TUTARSIZLIGI',
            'title'    => 'Komisyon Kuruş Tutarsızlığı',
            'tooltip'  => 'Komisyon tutarı ile brüt×oran hesabı arasındaki kuruş farklarını tespit eder.',
            'severity' => 'warning',
            'category' => 'Komisyon',
            'icon'     => '🔢',
        ],
        // ── Yeni Kurallar ──
        'checkMissingCogs' => [
            'code'     => 'COGS_EKSIK',
            'title'    => 'Maliyet Tanımsız Ürünler',
            'tooltip'  => 'COGS (ürün maliyeti) tanımlı olmayan siparişleri tespit eder. Kârlılık hesabı güvenilmez — Pazaryeri Ürünlerim\'den maliyet girilmelidir.',
            'severity' => 'critical',
            'category' => 'Maliyet',
            'icon'     => '🏷️',
        ],
        'checkPriceDrop' => [
            'code'     => 'FIYAT_DUSME',
            'title'    => 'Fiyat Düşme Alarmı',
            'tooltip'  => 'Aynı ürünün ortalama satış fiyatı önceki döneme göre %15+ düşmüşse alarm verir. Kampanya etkisi veya piyasa değişimini gösterir.',
            'severity' => 'warning',
            'category' => 'Fiyat',
            'icon'     => '📉',
        ],
        'checkNegativeHakedis' => [
            'code'     => 'NEGATIF_HAKEDIS',
            'title'    => 'Negatif Hakediş Tespiti',
            'tooltip'  => 'Teslim edilmiş ama negatif hakediş olan siparişleri tespit eder. Kampanya zararı veya kayıt dışı iade göstergesi.',
            'severity' => 'critical',
            'category' => 'Ödeme',
            'icon'     => '🚨',
        ],
        'checkCargoOverCost' => [
            'code'     => 'KARGO_MALIYET_ASIMI',
            'title'    => 'Kargo Maliyet Aşımı',
            'tooltip'  => 'Kendi kargo maliyeti, ürün kâr marjının %50\'sini aşıyorsa uyarır. Desi/ambalaj optimizasyonu gerekebilir.',
            'severity' => 'warning',
            'category' => 'Kargo',
            'icon'     => '🚛',
        ],
        'checkHighReturnRate' => [
            'code'     => 'YUKSEK_IADE',
            'title'    => 'Yüksek İade Oranı',
            'tooltip'  => 'Aynı SKU\'da iade oranı %15\'i aşıyorsa uyarır. Ürün kalitesi, paketleme veya listing sorununa işaret eder.',
            'severity' => 'warning',
            'category' => 'İade',
            'icon'     => '📦',
        ],
        // ── Faz 3 Kurallar ──
        'checkCampaignLoss' => [
            'code'     => 'KAMPANYA_ZARAR',
            'title'    => 'Kampanya Zarar Analizi',
            'tooltip'  => 'Kampanya indirimi uygulanan siparişlerde net kâr negatife dönmüşse uyarır. Sattıkça zarar eden kampanyaları tespit eder.',
            'severity' => 'critical',
            'category' => 'Kârlılık',
            'icon'     => '🏷️',
        ],
        'checkCommissionRateChange' => [
            'code'     => 'KOMISYON_ORANI_DEGISIMI',
            'title'    => 'Komisyon Oranı Değişim Tespiti',
            'tooltip'  => 'Aynı ürünün komisyon oranı önceki döneme göre artmışsa uyarır. Trendyol\'un sessiz komisyon artışlarını tespit eder.',
            'severity' => 'warning',
            'category' => 'Komisyon',
            'icon'     => '📈',
        ],
        'checkServiceFeeIncrease' => [
            'code'     => 'HIZMET_BEDELI_ARTISI',
            'title'    => 'Hizmet Bedeli Artış Tespiti',
            'tooltip'  => 'Service fee oranı önceki aya göre artmışsa uyarır. Gizli marj erozyonu göstergesi.',
            'severity' => 'warning',
            'category' => 'Komisyon',
            'icon'     => '💹',
        ],
        'checkHighCancellationRate' => [
            'code'     => 'IPTAL_ORANI',
            'title'    => 'Yüksek İptal Oranı',
            'tooltip'  => 'SKU bazında iptal oranı %10\'u aşarsa uyarır. Stok yönetimi, operasyonel ceza riski ve Trendyol algoritma sıralama düşüşü göstergesi.',
            'severity' => 'warning',
            'category' => 'İade',
            'icon'     => '🚫',
        ],
    ];

    public static function getRuleCount(): int
    {
        return count(self::RULES);
    }

    /**
     * Kural kodu (STOPAJ, EXTREME_MARGIN vb.) ile RULE_META eşleşmesi
     */
    public static function getMetaByCode(string $ruleCode): ?array
    {
        foreach (self::RULE_META as $method => $meta) {
            if ($meta['code'] === $ruleCode) {
                return $meta;
            }
        }
        return null;
    }

    public function __construct(?MpSettingsService $settings = null)
    {
        $this->settings = $settings ?? new MpSettingsService();
    }
    /**
     * Tüm denetim kurallarını çalıştır
     */
    public function runAllRules(MpPeriod $period, array $disabledRules = []): array
    {
        $results = [
            'total_errors'   => 0,
            'total_warnings' => 0,
            'total_amount'   => 0,
            'rules_run'      => [],
            'rules_skipped'  => [],
        ];

        // Önceki audit loglarını temizle (her çalıştırmada taze sonuç)
        MpAuditLog::where('period_id', $period->id)->delete();

        $rules = self::RULES;

        foreach ($rules as $rule) {
            // Kullanıcı tarafından devre dışı bırakılan kuralları atla
            if (in_array($rule, $disabledRules)) {
                $results['rules_skipped'][] = $rule;
                continue;
            }

            try {
                $count = $this->$rule($period);
                $results['rules_run'][$rule] = $count;
            } catch (\Exception $e) {
                Log::error("AuditEngine: {$rule} hatası", ['error' => $e->getMessage()]);
                $results['rules_run'][$rule] = 'error: ' . $e->getMessage();
            }
        }

        // Toplam istatistikleri hesapla
        $results['total_errors'] = MpAuditLog::where('period_id', $period->id)
            ->where('severity', 'critical')->count();
        $results['total_warnings'] = MpAuditLog::where('period_id', $period->id)
            ->where('severity', 'warning')->count();
        $results['total_amount'] = MpAuditLog::where('period_id', $period->id)
            ->sum('difference');

        // Dönem istatistiğini güncelle
        $period->update([
            'total_audit_errors' => $results['total_errors'] + $results['total_warnings'],
        ]);

        return $results;
    }

    // ═══════════════════════════════════════════════════════════════
    // KURAL 1: STOPAJ DOĞRULAMASI
    // Brüt Satış × %1 ≈ Kesilen Stopaj (±0.05 TL tolerans)
    // ═══════════════════════════════════════════════════════════════

    protected function checkStopaj(MpPeriod $period): int
    {
        $count = 0;
        $stopajRate = $this->settings->getStopajRate();
        $tolerance = $this->settings->getFloat('audit_tolerances.stopaj_tolerance', 0.05);

        $orders = MpOrder::where('period_id', $period->id)
            ->where('gross_amount', '>', 0)
            ->whereNotIn('status', ['İptal Edildi'])
            ->get();

        foreach ($orders as $order) {
            // Stopaj KDV'siz (Matrah) üzerinden hesaplanır.
            // Zemin: %1, %10 veya %20 KDV olabilir.

            $hasDiscount = $order->discount_amount > 0 || $order->campaign_discount > 0;
            $matrahMultiplier = 1 / (1 + ($order->product_vat_rate / 100));
            $expectedBase = ($order->gross_amount - $order->discount_amount - $order->campaign_discount) * $matrahMultiplier;
            
            $expectedStopaj = $expectedBase * $stopajRate;
            $diff = abs($expectedStopaj - $order->withholding_tax);
            
            if ($diff > $tolerance) {
                MpAuditLog::create([
                    'period_id'   => $period->id,
                    'order_id'    => $order->id,
                    'rule_code'   => 'EKSİK_STOPAJ_KESINTISI',
                    'severity'    => 'warning',
                    'message'     => 'E-Ticaret stopaj matrahı uyumsuz! Beklenen: ' . round($expectedStopaj,2) . ' TL, Kesilen: ' . $order->withholding_tax . ' TL',
                    'difference'  => round($diff, 2),
                ]);
                $count++;
            }
        }
        return $count;
    }

    // Epic 7: Komisyon Kuruş Tutarsızlığı
    protected function checkCommissionMismatch(MpPeriod $period): int
    {
        $count = 0;
        $tolerance = $this->settings->getFloat('audit_tolerances.commission_mismatch_tolerance', 1.5);
        
        $orders = MpOrder::where('period_id', $period->id)
            ->where('gross_amount', '>', 0)
            ->whereNotIn('status', ['İptal Edildi'])
            ->get();
            
        foreach ($orders as $order) {
            // Excelden o satırda hesaplanan raw (ham) komisyon oranını getirelim
            $rate = $order->commission_rate; // import esnasında (commission_amount / gross_amount * 100) ile bulunur.
            
            if ($rate <= 0) continue; // Rate yoksa veya komisyonsuzsa atla

            // Eğer sistem komisyonda özel bir rate map indirseydi onunla asıl commission_amount kıyaslanırdı.
            // Fakat bizim sistemde "Teorik komisyon oranı" elimizde olmadığı için excelden gelen commission_amount
            // ile cari listeden gelen (kısmi) komisyon tutarını veya order daki komisyon_rate*brut asimetrisini test edelim.
            
            $expectedCommission = ($order->gross_amount * ($rate / 100));
            $actualCommission = abs((float) $order->commission_amount);
            $diff = abs($actualCommission - $expectedCommission);
            
            if ($diff > $tolerance) {
                // Gerçekte ne kadar yüzde kesildiğini hesapla (bilgi amaçlı)
                $actualRatio = $order->gross_amount > 0 ? ($actualCommission / $order->gross_amount) * 100 : 0;

                MpAuditLog::create([
                    'period_id'      => $period->id,
                    'order_id'       => $order->id,
                    'rule_code'      => 'KOMISYON_TUTARSIZLIGI',
                    'severity'       => 'warning',
                    'title'          => "Komisyon Tutarsızlığı — Sipariş #{$order->order_number}",
                    'description'    => "Kesilen sipariş komisyonu ({$actualCommission} TL, %" . round($actualRatio, 2) . ") "
                                     . "ile beklenen komisyon matrahı (" . round($expectedCommission, 2) . " TL, %{$rate}) uyuşmuyor. "
                                     . "Fark: " . round($diff, 2) . " TL.",
                    'expected_value' => round($expectedCommission, 2),
                    'actual_value'   => round($actualCommission, 2),
                    'difference'     => round($diff, 2),
                ]);
                $count++;
            }
        }
        return $count;
    }

    // ═══════════════════════════════════════════════════════════════
    // KURAL 2: KARGO BAREM AŞIMI
    // Sipariş < 300 TL ise barem fiyatı uygulanmalı
    // ═══════════════════════════════════════════════════════════════

    protected function checkBaremExcess(MpPeriod $period): int
    {
        $count = 0;
        $baremLimit = $this->settings->getBaremLimit();

        $orders = MpOrder::where('period_id', $period->id)
            ->where('gross_amount', '<', $baremLimit)
            ->where('cargo_amount', '>', 0)
            ->whereNotIn('status', ['İptal Edildi'])
            ->get();

        foreach ($orders as $order) {
            $cargoCompany = $order->cargo_company ?? 'TEX';
            $expectedBarem = $this->settings->getBaremPrice($cargoCompany, (float) $order->gross_amount);

            if ($expectedBarem === null) continue;

            $actual = (float) $order->cargo_amount;
            $diff = $actual - $expectedBarem;

            $baremTolerance = $this->settings->getFloat('audit_tolerances.barem_excess_tolerance', 1.0);
            if ($diff > $baremTolerance) {
                MpAuditLog::create([
                    'period_id'      => $period->id,
                    'order_id'       => $order->id,
                    'rule_code'      => 'BAREM_ASIMI',
                    'severity'       => 'warning',
                    'title'          => "Barem Aşımı — Sipariş #{$order->order_number}",
                    'description'    => "Sipariş tutarı " . number_format($order->gross_amount, 2, ',', '.') . " TL (< {$baremLimit} TL). "
                                     . "Barem: " . number_format($expectedBarem, 2, ',', '.') . " TL olması gerekirken "
                                     . number_format($actual, 2, ',', '.') . " TL kesilmiş. "
                                     . "Çoklu Sepet Etkisi veya yanlış desi ölçümü olabilir.",
                    'expected_value' => $expectedBarem,
                    'actual_value'   => $actual,
                    'difference'     => $diff,
                ]);

                $order->update(['is_flagged' => true]);
                $count++;
            }
        }

        return $count;
    }

    // ═══════════════════════════════════════════════════════════════
    // KURAL 3: KOMİSYON İADESİ KAYBI
    // İade edilen ürünün komisyonu Cari Ekstre'de Alacak olarak dönmeli
    // ═══════════════════════════════════════════════════════════════

    protected function checkCommissionRefund(MpPeriod $period): int
    {
        $count = 0;

        $returnedOrders = MpOrder::where('period_id', $period->id)
            ->whereIn('status', ['İade Edildi', 'İptal Edildi'])
            ->where('commission_amount', '>', 0)
            ->get();

        foreach ($returnedOrders as $order) {
            // Cari Ekstre'de bu sipariş için komisyon iadesi (Alacak) arayalım
            $commissionRefund = MpTransaction::where('period_id', $period->id)
                ->where('order_number', $order->order_number)
                ->where(function ($q) {
                    $q->where('transaction_type', 'like', '%Komisyon%')
                      ->orWhere('transaction_type', 'like', '%komisyon%');
                })
                ->where('credit', '>', 0)
                ->sum('credit');

            // Komisyon iadesi alınmamış veya eksik
            $expectedRefund = (float) $order->commission_amount;
            $diff = $expectedRefund - $commissionRefund;

            $refundTolerance = $this->settings->getFloat('audit_tolerances.commission_refund_tolerance', 0.50);
            if ($diff > $refundTolerance) {
                MpAuditLog::create([
                    'period_id'      => $period->id,
                    'order_id'       => $order->id,
                    'rule_code'      => 'KOMISYON_IADE',
                    'severity'       => 'critical',
                    'title'          => "Komisyon İadesi Alınmamış — Sipariş #{$order->order_number}",
                    'description'    => "Bu sipariş iptal/iade edilmiş, ancak " . number_format($expectedRefund, 2, ',', '.') . " TL komisyon iadesi "
                                     . "Cari Hesap Ekstresi'nde Alacak olarak dönmemiş. "
                                     . "Alacak bulunan: " . number_format($commissionRefund, 2, ',', '.') . " TL. "
                                     . "Kayıp: " . number_format($diff, 2, ',', '.') . " TL",
                    'expected_value' => $expectedRefund,
                    'actual_value'   => $commissionRefund,
                    'difference'     => $diff,
                ]);

                $order->update(['is_flagged' => true]);
                $count++;
            }
        }

        return $count;
    }

    // ═══════════════════════════════════════════════════════════════
    // KURAL 4: YANIK MALİYET (İade Lojistik Zararı)
    // İade → gidiş kargo sunk cost + dönüş kargo cezası
    // ═══════════════════════════════════════════════════════════════

    protected function checkSunkCost(MpPeriod $period): int
    {
        $count = 0;

        $returnedOrders = MpOrder::where('period_id', $period->id)
            ->where('status', 'İade Edildi')
            ->where('cargo_amount', '>', 0)
            ->get();

        foreach ($returnedOrders as $order) {
            $sunkCost = (float) $order->cargo_amount;

            // Dönüş kargo faturası kontrolü
            $returnCargoFee = MpTransaction::where('period_id', $period->id)
                ->where('order_number', $order->order_number)
                ->where(function ($q) {
                    $q->where('transaction_type', 'like', '%İade Kargo%')
                      ->orWhere('transaction_type', 'like', '%iade kargo%');
                })
                ->sum('debt');

            $totalLoss = $sunkCost + $returnCargoFee;

            if ($totalLoss > 0) {
                MpAuditLog::create([
                    'period_id'      => $period->id,
                    'order_id'       => $order->id,
                    'rule_code'      => 'YANIK_MALIYET',
                    'severity'       => $totalLoss > $this->settings->getFloat('audit_tolerances.sunk_cost_critical_threshold', 100) ? 'critical' : 'warning',
                    'title'          => "Yanık Maliyet — Sipariş #{$order->order_number}",
                    'description'    => "İade edilen sipariş lojistik zararı: "
                                     . "Gidiş kargo (sunk cost): " . number_format($sunkCost, 2, ',', '.') . " TL + "
                                     . "Dönüş kargo faturası: " . number_format($returnCargoFee, 2, ',', '.') . " TL = "
                                     . "Toplam zarar: " . number_format($totalLoss, 2, ',', '.') . " TL",
                    'expected_value' => 0,
                    'actual_value'   => $totalLoss,
                    'difference'     => $totalLoss,
                ]);

                $order->update(['is_flagged' => true]);
                $count++;
            }
        }

        return $count;
    }

    // ═══════════════════════════════════════════════════════════════
    // KURAL 5: HAKEDİŞ FARK KONTROLÜ
    // Hesaplanan net hakediş ≈ Excel'den gelen net hakediş
    // ═══════════════════════════════════════════════════════════════

    protected function checkHakedisDiscrepancy(MpPeriod $period): int
    {
        $count = 0;

        $orders = MpOrder::where('period_id', $period->id)
            ->where('net_hakedis', '>', 0)
            ->where('status', 'Teslim Edildi')
            ->get();

        foreach ($orders as $order) {
            // Hesaplanan = Brüt - Satıcı İndirimi - Kampanya İndirimi - Komisyon - Kargo - Hizmet Bedeli
            // NOT: E-Ticaret stopajı (withholding_tax), Trendyol'un Sipariş Excel'indeki 
            // 'Tahmini Net Hakediş' (net_hakedis) sütunundan düşülmemiş halde gelir (!). 
            // Bu nedenle Audit motoru kıyaslama yaparken stopajı denkleme katmaz, aksi takdirde false-positive üretir.
            // Excel'den gelen gider değerleri negatif (-) bile gelse abs() ile mutlaka mutlak değer çıkarılır
            $calculated = $this->calculateExpectedNetFromOrder($order);

            $reported = (float) $order->net_hakedis;
            $diff = abs($calculated - $reported);

            // ── QUANTITY NORMALIZASYON ──
            // Trendyol Sipariş Excel'inde quantity>1 olan satırlarda:
            // gross_amount, commission_amount vs. TÜM ADETLERİN TOPLAMI olarak gelir
            // AMA net_hakedis bazen ADET BAŞI ya da KISMI (iade sonrası) tutar olarak gelir.
            // Bu durum yapısal bir Trendyol format farkıdır, gerçek tutarsızlık değildir.
            $qty = (int) $order->quantity;
            if ($qty > 1 && $reported > 0 && $reported < $calculated) {
                // Çoklu adetli siparişlerde calculated her zaman toplam, 
                // reported ise adet başına veya kısmi olabilir → bu farkı yoksay
                $diff = 0;
            }

            $hakedisTolerance = $this->settings->getFloat('audit_tolerances.hakedis_tolerance', 1.0);
            if ($diff > $hakedisTolerance) {
                // ── ADIM 1: Settlement verisiyle doğrulama (Tüm kayıtlar toplanır) ──
                $allSettlements = MpSettlement::where('period_id', $period->id)
                    ->where('order_number', $order->order_number)
                    ->get();
                $netSettlement  = (float) $allSettlements->sum('seller_hakedis');
                
                $refundSettlements = $allSettlements->filter(function($s) {
                    return (float) $s->seller_hakedis < 0 && (str_contains(mb_strtolower($s->transaction_type), 'iade') || str_contains(mb_strtolower($s->transaction_type), 'iptal'));
                });
                $hasRefund      = $refundSettlements->count() > 0;
                $hasSettlement  = $allSettlements->where('seller_hakedis', '>', 0)->count() > 0;

                if ($hasSettlement) {
                    // A) Kısmi İade Senaryosu: İade kaydı var → net tutarla kıyasla
                    if ($hasRefund) {
                        // Kısmi iade varsa net settlement doğal olarak calculated'dan düşük olacak.
                        // Bu normal bir durum — alarm üretme.
                        $refundTotal = abs((float) $refundSettlements->sum('seller_hakedis'));
                        MpAuditLog::create([
                            'period_id'      => $period->id,
                            'order_id'       => $order->id,
                            'rule_code'      => 'KISMI_IADE',
                            'severity'       => 'info',
                            'title'          => "💡 Kısmi İade — Sipariş #{$order->order_number}",
                            'description'    => "Bu siparişte kısmi iade tespit edildi. "
                                             . "Toplam satış: " . number_format((float) $allSettlements->where('seller_hakedis', '>', 0)->sum('seller_hakedis'), 2, ',', '.') . " TL, "
                                             . "İade kesintisi: -" . number_format($refundTotal, 2, ',', '.') . " TL, "
                                             . "Net tahsilat: " . number_format($netSettlement, 2, ',', '.') . " TL. "
                                             . "Bu normal bir operasyonel durumdur.",
                            'expected_value' => $calculated,
                            'actual_value'   => $netSettlement,
                            'difference'     => $refundTotal,
                        ]);
                        $count++;
                        continue; // is_flagged yapma
                    }

                    // B) İade yok, net settlement hesaplananla uyumlu mu?
                    if ($netSettlement >= $calculated * 0.90) {
                        // Banka ödemesi doğru → tamamen gizle
                        continue;
                    }
                    
                    // C) Quantity kontrol: Ödeme Excel per-unit satır yazıyor olabilir
                    $qty = (int) $order->quantity;
                    if ($qty > 1 && $netSettlement > 0) {
                        $positiveRows = $allSettlements->filter(fn($s) => (float) $s->seller_hakedis > 0)->count();
                        if ($positiveRows < $qty) {
                            continue; // Kısmi ödeme yüklemesi, gerçek fark yok
                        }
                    }
                }

                // ── ADIM 2: İndirim illüzyonu kontrolü ──
                $discountAmount = abs((float) $order->discount_amount);
                $campaignAmount = abs((float) $order->campaign_discount);
                $totalDiscount  = $discountAmount + $campaignAmount;
                
                $isDiscountIllusion = false;
                if ($discountAmount > 0 && abs($diff - $discountAmount) <= $hakedisTolerance) $isDiscountIllusion = true;
                if ($campaignAmount > 0 && abs($diff - $campaignAmount) <= $hakedisTolerance) $isDiscountIllusion = true;
                if ($totalDiscount  > 0 && abs($diff - $totalDiscount)  <= $hakedisTolerance) $isDiscountIllusion = true;

                if ($isDiscountIllusion) {
                    MpAuditLog::create([
                        'period_id'      => $period->id,
                        'order_id'       => $order->id,
                        'rule_code'      => 'HAKEDIS_ILLUZYON',
                        'severity'       => 'info',
                        'title'          => "💡 Raporlama Sapması (İndirim Etkisi)",
                        'description'    => "Trendyol Sipariş Excel'inde kampanya/indirim tutarı (" 
                                         . number_format($totalDiscount, 2, ',', '.') . " TL) görsel olarak mükerrer düşülmüş. "
                                         . "Bu sadece bir raporlama illüzyonudur, finansal kaybınız yoktur.",
                        'expected_value' => $calculated,
                        'actual_value'   => $reported,
                        'difference'     => $diff,
                    ]);
                    $count++;
                } else {
                    // ── ADIM 3: Gerçek tutarsızlık ──
                    MpAuditLog::create([
                        'period_id'      => $period->id,
                        'order_id'       => $order->id,
                        'rule_code'      => 'HAKEDIS_FARK',
                        'severity'       => $diff > $this->settings->getFloat('audit_tolerances.hakedis_critical_threshold', 20) ? 'critical' : 'warning',
                        'title'          => "Hakediş Tutarsızlığı — Sipariş #{$order->order_number}",
                        'description'    => "Hesaplanan hakediş: " . number_format($calculated, 2, ',', '.') . " TL, "
                                         . "Trendyol hakediş: " . number_format($reported, 2, ',', '.') . " TL. "
                                         . "Fark: " . number_format($diff, 2, ',', '.') . " TL. "
                                         . "Gizli kesinti veya yuvarlama hatası olabilir.",
                        'expected_value' => $calculated,
                        'actual_value'   => $reported,
                        'difference'     => $diff,
                    ]);

                    $order->update(['is_flagged' => true]);
                    $count++;
                }
            }
        }

        return $count;
    }

    // ═══════════════════════════════════════════════════════════════
    // KURAL 6: OPERASYONEL KARGO CEZALARI
    // Cari Ekstre'de Ağır Desi, Teslimat Başarısızlık vb. cezalar
    // ═══════════════════════════════════════════════════════════════

    protected function checkOperationalPenalties(MpPeriod $period): int
    {
        $count = 0;

        $penalties = MpTransaction::where('period_id', $period->id)
            ->where(function ($q) {
                $q->where('transaction_type', 'like', '%Ağır%')
                  ->orWhere('transaction_type', 'like', '%Ceza%')
                  ->orWhere('transaction_type', 'like', '%Başarısız%')
                  ->orWhere('transaction_type', 'like', '%Tazmin%');
            })
            ->get();

        foreach ($penalties as $tx) {
            $isCompensation = str_contains(mb_strtolower($tx->transaction_type), 'tazmin');
            $amount = $isCompensation ? $tx->credit : $tx->debt;
            
            if ($amount <= 0) continue;

            MpAuditLog::create([
                'period_id'      => $period->id,
                'order_id'       => null,
                'rule_code'      => $isCompensation ? 'KAYIP_TAZMINATI' : 'OPERASYONEL_CEZA',
                'severity'       => $isCompensation ? 'info' : ((float) $amount > $this->settings->getFloat('audit_tolerances.operational_penalty_critical_threshold', 500) ? 'critical' : 'warning'),
                'title'          => ($isCompensation ? "Tazminat Alacağı — " : "Operasyonel Ceza — ") . $tx->transaction_type,
                'description'    => "Cari Ekstre'de işlem tespit edildi: "
                                 . "{$tx->transaction_type} — {$tx->description}. "
                                 . "Tutar: " . number_format($amount, 2, ',', '.') . " TL. "
                                 . ($tx->order_number ? "Sipariş: #{$tx->order_number}" : "Sipariş eşleştirilmemiş."),
                'expected_value' => 0,
                'actual_value'   => $amount,
                'difference'     => $amount,
            ]);
            $count++;
        }

        return $count;
    }

    // ═══════════════════════════════════════════════════════════════
    // KURAL 7: ÇOKLU SEPET ETKİSİ
    // Brüt < 300 TL ama kargo yüksek → çoklu sepet veya desi hatası
    // ═══════════════════════════════════════════════════════════════

    protected function checkMultipleCart(MpPeriod $period): int
    {
        $count = 0;
        $baremLimit = $this->settings->getBaremLimit();

        $orders = MpOrder::where('period_id', $period->id)
            ->where('gross_amount', '<', $baremLimit)
            ->where('cargo_amount', '>', 0)
            ->whereNotIn('status', ['İptal Edildi'])
            ->get();

        foreach ($orders as $order) {
            $cargoCompany = $order->cargo_company ?? 'TEX';
            $expectedBarem = $this->settings->getBaremPrice($cargoCompany, (float) $order->gross_amount);

            if ($expectedBarem === null) continue;

            $actual = (float) $order->cargo_amount;

            // Barem farkı büyük ama standart desi fiyatına yakınsa → çoklu sepet
            $cartFactor = $this->settings->getFloat('audit_tolerances.multiple_cart_factor', 1.5);
            if ($actual > $expectedBarem * $cartFactor) {
                // Standart desi fiyatına yakın mı kontrol et
                $desiPrice = $this->settings->getDesiPrice($cargoCompany, (float) ($order->cargo_desi ?? 0));
                $desiTolerance = $this->settings->getFloat('audit_tolerances.multiple_cart_desi_tolerance', 10);

                if ($desiPrice > 0 && abs($actual - $desiPrice) < $desiTolerance) {
                    MpAuditLog::create([
                        'period_id'      => $period->id,
                        'order_id'       => $order->id,
                        'rule_code'      => 'COKLU_SEPET',
                        'severity'       => 'info',
                        'title'          => "Çoklu Sepet Etkisi — Sipariş #{$order->order_number}",
                        'description'    => "Sipariş tutarı " . number_format($order->gross_amount, 2, ',', '.') . " TL (< {$baremLimit} TL) "
                                         . "ancak kargo " . number_format($actual, 2, ',', '.') . " TL (barem: "
                                         . number_format($expectedBarem, 2, ',', '.') . " TL). "
                                         . "Bu muhtemelen çoklu sepet etkisinden kaynaklanıyor (müşteri birden fazla ürün aldı). "
                                         . "Normal desi fiyatı: " . number_format($desiPrice, 2, ',', '.') . " TL.",
                        'expected_value' => $expectedBarem,
                        'actual_value'   => $actual,
                        'difference'     => $actual - $expectedBarem,
                    ]);
                    $count++;
                }
            }
        }

        return $count;
    }

    // ═══════════════════════════════════════════════════════════════
    // KURAL 8: E-ARŞİV FATURA İPTAL UYARISI
    // İade → "E-Arşiv fatura iptalini unutmayın!" uyarısı
    // ═══════════════════════════════════════════════════════════════

    protected function checkEarsivReminder(MpPeriod $period): int
    {
        $count = 0;

        $returnedOrders = MpOrder::where('period_id', $period->id)
            ->where('status', 'İade Edildi')
            ->where('gross_amount', '>', 0)
            ->get();

        foreach ($returnedOrders as $order) {
            MpAuditLog::create([
                'period_id'      => $period->id,
                'order_id'       => $order->id,
                'rule_code'      => 'EARSIV_UYARI',
                'severity'       => 'info',
                'title'          => "E-Arşiv Fatura Uyarısı — Sipariş #{$order->order_number}",
                'description'    => "Bu sipariş iade edilmiştir! KDV ve Gelir Vergisi zararı yememek için "
                                 . "E-Arşiv faturasını iptal etmeyi veya iade faturası almayı unutmayın. "
                                 . "Brüt tutar: " . number_format($order->gross_amount, 2, ',', '.') . " TL.",
                'expected_value' => 0,
                'actual_value'   => $order->gross_amount,
                'difference'     => 0,
            ]);
            $count++;
        }

        return $count;
    }

    // ═══════════════════════════════════════════════════════════════
    // KURAL 9: AĞIR KARGO / OPERASYONEL CEZA TESPİTİ (Faz 2)
    // 100 desi üstü sabit cezalar: 4.250 TL, 4.500 TL, 5.350 TL
    // Cari Ekstre'den bu sabit tutarları tespit et
    // ═══════════════════════════════════════════════════════════════

    protected function checkHeavyCargoPenalty(MpPeriod $period): int
    {
        $count = 0;

        // Bilinen sabit ağır kargo ceza tutarları (ayarlardan çekilir)
        $heavyPenalties = $this->settings->getHeavyCargoPenalties();
        $knownPenalties = !empty($heavyPenalties) ? array_values($heavyPenalties) : [4250.00, 4500.00, 5350.00];
        $tolerance = $this->settings->getFloat('audit_tolerances.heavy_cargo_tolerance', 50);

        // 1) Sabit tutar bazlı tarama
        $transactions = MpTransaction::where('period_id', $period->id)
            ->where('debt', '>', 0)
            ->get();

        foreach ($transactions as $tx) {
            $amount = (float) $tx->debt;
            $isHeavy = false;
            $matchedPenalty = 0;

            // Dışlama: Stopaj, Fatura, KDV, Komisyon gibi standart mali işlemler
            // Bu işlemler tutarsal olarak ağır kargo cezasına benzer olabilir ama kargo cezası değildir
            $typeLC = mb_strtolower($tx->transaction_type ?? '');
            $descLC = mb_strtolower($tx->description ?? '');
            $combinedLC = $typeLC . ' ' . $descLC;
            
            $isExcluded = str_contains($combinedLC, 'stopaj')
                || str_contains($combinedLC, 'e-ticaret')
                || str_contains($combinedLC, 'eticaret') 
                || str_contains($combinedLC, 'vergi')
                || str_contains($combinedLC, 'kdv')
                || str_contains($combinedLC, 'komisyon')
                || str_contains($combinedLC, 'hizmet bedeli')
                || ($typeLC === 'kargo fatura' && !str_contains($descLC, 'ağır'))
                || ($typeLC === 'fatura')
                // Ürün iade/satış/iptal işlemleri: Tutarları tesadüfen ceza tutarlarına denk gelebilir
                // ama bunlar normal ticari işlemlerdir, ağır kargo cezası değildir
                || str_contains($typeLC, 'iade')
                || str_contains($typeLC, 'iptal')
                || ($typeLC === 'satış' || $typeLC === 'satis')
                || str_contains($typeLC, 'indirim')
                || str_contains($typeLC, 'İndirim');
                
            if ($isExcluded) continue;

            // Sabit ceza tutarı kontrolü
            foreach ($knownPenalties as $penalty) {
                if (abs($amount - $penalty) <= $tolerance) {
                    $isHeavy = true;
                    $matchedPenalty = $penalty;
                    break;
                }
            }

            // Anahtar kelime kontrolü (keyword fallback)
            if (!$isHeavy) {
                // $typeLC, $descLC, $combinedLC zaten yukarıda hesaplandı

                if (
                    str_contains($combinedLC, 'ağır kargo') ||
                    str_contains($combinedLC, 'ağır taşıma') ||
                    str_contains($combinedLC, 'heavy cargo') ||
                    (str_contains($combinedLC, '100 desi') && str_contains($combinedLC, 'ceza'))
                ) {
                    $isHeavy = true;
                    $matchedPenalty = $amount;
                }
            }

            if ($isHeavy) {
                MpAuditLog::create([
                    'period_id'      => $period->id,
                    'order_id'       => null,
                    'rule_code'      => 'AGIR_KARGO_CEZA',
                    'severity'       => 'critical',
                    'title'          => "🚨 Ağır Kargo Cezası — " . number_format($amount, 0, ',', '.') . " TL",
                    'description'    => "100 desi üstü ağır kargo taşıma bedeli tespit edildi! "
                                     . "Tutar: " . number_format($amount, 2, ',', '.') . " TL "
                                     . ($matchedPenalty > 0 && $matchedPenalty != $amount
                                         ? "(bilinen ceza: " . number_format($matchedPenalty, 0, ',', '.') . " TL'ye yakın). "
                                         : ". ")
                                     . "İşlem: {$tx->transaction_type}. "
                                     . "Açıklama: {$tx->description}. "
                                     . ($tx->order_number
                                         ? "Sipariş: #{$tx->order_number}. "
                                         : "Spesifik sipariş eşlemesi yok — genel ceza. ")
                                     . "Bu ceza ürün boyutu/ağırlığı nedeniyle oluşur, ürün listesini gözden geçirin.",
                    'expected_value' => 0,
                    'actual_value'   => $amount,
                    'difference'     => $amount,
                ]);

                // Sipariş varsa flag'le
                if ($tx->order_number) {
                    MpOrder::where('period_id', $period->id)
                        ->where('order_number', $tx->order_number)
                        ->update(['is_flagged' => true]);
                }

                $count++;
            }
        }

        return $count;
    }

    // ═══════════════════════════════════════════════════════════════
    // KURAL 10: KOMİSYON İADESİ TAKİBİ (Faz 2 — Enhanced)
    // İade edilen siparişin komisyonun Alacak (+) olarak dönmesini takip
    // Faz 1 checkCommissionRefund'dan farkı: sipariş eşlemesi olmasa bile
    // toplam bazda kontrol ve detaylı raporlama
    // ═══════════════════════════════════════════════════════════════

    protected function checkCommissionRefundTracking(MpPeriod $period): int
    {
        $count = 0;

        // Tüm iade siparişlerinin toplam komisyonu
        $returnedOrders = MpOrder::where('period_id', $period->id)
            ->where('status', 'İade Edildi')
            ->where('commission_amount', '>', 0)
            ->get();

        $totalExpectedRefund = $returnedOrders->sum('commission_amount');
        $unmatchedOrders     = [];

        foreach ($returnedOrders as $order) {
            // Bu sipariş için Cari Ekstre'de komisyon Alacak (geri ödeme) arayalım
            $refundCredit = MpTransaction::where('period_id', $period->id)
                ->where(function ($q) use ($order) {
                    // Sipariş numarasıyla eşle
                    $q->where('order_number', $order->order_number)
                      // Veya açıklamada sipariş numarası geçiyorsa
                      ->orWhere('description', 'like', "%{$order->order_number}%");
                })
                ->where(function ($q) {
                    $q->where('transaction_type', 'like', '%Komisyon%')
                      ->orWhere('transaction_type', 'like', '%komisyon%')
                      ->orWhere('transaction_type', 'like', '%İade%')
                      ->orWhere('description', 'like', '%komisyon iade%');
                })
                ->where('credit', '>', 0)
                ->sum('credit');

            $missing = (float) $order->commission_amount - $refundCredit;

            $refTrackingTolerance = $this->settings->getFloat('audit_tolerances.commission_refund_tracking_tolerance', 1.0);
            if ($missing > $refTrackingTolerance) {
                $unmatchedOrders[] = [
                    'order' => $order,
                    'expected' => (float) $order->commission_amount,
                    'found'    => $refundCredit,
                    'missing'  => $missing,
                ];
            }
        }

        // Her eşleşmeyen sipariş için bireysel log
        foreach ($unmatchedOrders as $item) {
            $order = $item['order'];
            MpAuditLog::create([
                'period_id'      => $period->id,
                'order_id'       => $order->id,
                'rule_code'      => 'KOMISYON_IADE_TAKIP',
                'severity'       => 'critical',
                'title'          => "Komisyon İadesi Alınamadı — Sipariş #{$order->order_number}",
                'description'    => "Sipariş iade edilmiş ancak " . number_format($item['expected'], 2, ',', '.') . " TL "
                                 . "komisyon iadesi Cari Hesap Ekstresi'nde Alacak (+) olarak geri yatmamış. "
                                 . "Bulunan alacak: " . number_format($item['found'], 2, ',', '.') . " TL. "
                                 . "Kayıp: " . number_format($item['missing'], 2, ',', '.') . " TL. "
                                 . "Trendyol destek ile iletişime geçin!",
                'expected_value' => $item['expected'],
                'actual_value'   => $item['found'],
                'difference'     => $item['missing'],
            ]);

            $order->update(['is_flagged' => true]);
            $count++;
        }

        // Toplam bazda özet log (eğer eksik varsa)
        if (count($unmatchedOrders) > 0) {
            $totalMissing = collect($unmatchedOrders)->sum('missing');
            MpAuditLog::create([
                'period_id'      => $period->id,
                'order_id'       => null,
                'rule_code'      => 'KOMISYON_IADE_OZET',
                'severity'       => 'warning',
                'title'          => "Komisyon İadesi Özeti — " . count($unmatchedOrders) . " sipariş",
                'description'    => "Bu dönemde toplam " . count($unmatchedOrders) . " iade siparişinden "
                                 . "komisyon iadesi tespit edilemedi. "
                                 . "Toplam beklenen iade: " . number_format($totalExpectedRefund, 2, ',', '.') . " TL. "
                                 . "Toplam kayıp: " . number_format($totalMissing, 2, ',', '.') . " TL.",
                'expected_value' => $totalExpectedRefund,
                'actual_value'   => $totalExpectedRefund - $totalMissing,
                'difference'     => $totalMissing,
            ]);
        }

        return $count;
    }

    // ═══════════════════════════════════════════════════════════════
    // KURAL 11: EKSİK ÖDEME TESPİTİ (Faz 4 - Mutabakat)
    // Sipariş tahmini hakedişi ile bankaya fiilen yatan (MpSettlement) tutarın kurgusu
    // ═══════════════════════════════════════════════════════════════
    protected function checkMissingPayments(MpPeriod $period): int
    {
        $count = 0;
        
        // Çoklu ürünlü siparişlerde aynı order_number birden fazla satır olarak geliyor.
        // Bu yüzden önce benzersiz order_number'ları grup olarak alalım.
        $orders = MpOrder::where('period_id', $period->id)
            ->where('status', 'Teslim Edildi')
            ->where('net_hakedis', '>', 0)
            ->get();
        
        // Benzersiz sipariş numarası bazında grupla
        $orderGroups = $orders->groupBy('order_number');

        foreach ($orderGroups as $orderNumber => $orderRows) {
            $firstOrder = $orderRows->first(); // Referans kayıt (flag için)
            $totalExpectedNet = (float) $orderRows->sum('net_hakedis'); // Konsolide beklenti
            
            // Tüm settlement kayıtlarını order_number ile al
            $allSettlements = MpSettlement::where('period_id', $period->id)
                ->where('order_number', $orderNumber)
                ->get();
            if ($allSettlements->isEmpty()) continue; // Settlement yoksa bu kural kapsamında değil

            // Kuponları (eksi bakiye ama satıcıdan kesilmeyen / veya satıcıya artı yansımayan platform indirimleri vs) 
            // dikkate almadan, sadece satış/hakediş olanların toplamını ve iadeleri dikkate almalıyız
            $totalDeposited = (float) $allSettlements->sum('seller_hakedis');
            $hasRefund = $allSettlements->contains(function($s) {
                return (float) $s->seller_hakedis < 0 && (str_contains(mb_strtolower($s->transaction_type), 'iade') || str_contains(mb_strtolower($s->transaction_type), 'iptal'));
            });
            
            // Kısmi iade varsa → net tutar doğal olarak düşük, bu "eksik ödeme" değil
            if ($hasRefund) {
                // Mutabakat: kısmi iade olan siparişlerde net tutarı kabul et
                MpSettlement::where('period_id', $period->id)
                    ->where('order_number', $orderNumber)
                    ->update(['is_reconciled' => true]);
                continue;
            }

            // Siparişteki tüm satırların toplam net hakedişi (önceden hesaplandı)
            $expected = $totalExpectedNet;
            
            // ── QUANTITY / ÇOK ÜRÜNLÜ SEPET KONTROL ──
            // Trendyol Sipariş Excel'i: Çok ürünlü sepetleri bazen TEK SATIR (quantity=3) olarak yazar.
            // Trendyol Ödeme Detay Excel'i: Her ürünü AYRI satır olarak yazar, 
            // ve farklı ürünler farklı vade tarihlerinde ödenebilir.
            // Bu durum, yüklenen Ödeme Excel'inde henüz tüm ürünlerin ödeme kaydı olmadığında
            // "Eksik Ödeme" gibi görünür ama aslında kalan ödemeler farklı dönemin Excel'indedir.
            //
            // Kontrol: Settlement'taki pozitif satır sayısı, siparişteki toplam adet sayısından az ise
            // ve yatan tutar makul bir oran ise (%15-%95 arası), bu bir kısmi ödeme yükleme durumudur.
            $totalQuantity = $orderRows->sum('quantity');
            $positiveSettlementRows = $allSettlements->filter(fn($s) => (float) $s->seller_hakedis > 0)->count();
            
            if ($totalQuantity > 1 && $totalDeposited > 0 && $totalDeposited < $expected) {
                // Pozitif settlement satır sayısı, toplam adetten az ise 
                // → henüz tüm adetlerin ödeme kaydı yüklenmemiş demektir
                if ($positiveSettlementRows < $totalQuantity) {
                    MpSettlement::where('period_id', $period->id)
                        ->where('order_number', $orderNumber)
                        ->update(['is_reconciled' => true]);
                    continue;
                }
            }
            
            $diff = $expected - $totalDeposited;

            $paymentTolerance = $this->settings->getFloat('audit_tolerances.missing_payment_tolerance', 0.50);
            if ($diff > $paymentTolerance && $totalDeposited < $expected) {
                // Trendyol'un net_hakedis'i yanıltıcı olabilir — hesaplanan hakediş ile de kontrol et
                $calculated = 0;
                foreach($orderRows as $r) {
                    $calculated += $this->calculateExpectedNetFromOrder($r);
                }
                
                // Eğer bankaya yatan hesaplananla uyumluysa → Trendyol beyanı yanıltıcı, gerçek kayıp yok
                // Ya da yatırılan tutar beklenen tutardan DAHA fazlaysa (kupon iadesi, fazla yatırma vs) alarm verme
                if ($totalDeposited >= $calculated * 0.90 || $totalDeposited >= $expected) {
                    MpSettlement::where('period_id', $period->id)
                        ->where('order_number', $orderNumber)
                        ->update(['is_reconciled' => true]);
                    continue;
                }

                MpAuditLog::create([
                    'period_id'      => $period->id,
                    'order_id'       => $firstOrder->id,
                    'rule_code'      => 'EKSIK_ODEME',
                    'severity'       => $diff > 10 ? 'critical' : 'warning',
                    'title'          => "💵 Eksik Ödeme Tespit Edildi — Sipariş #{$orderNumber}",
                    'description'    => "Sipariş Kayıtları'nda Trendyol'un vadettiği net hakediş: " 
                                     . number_format($expected, 2, ',', '.') . " TL iken, "
                                     . "Ödeme Detay raporunda fiilen bankanıza " 
                                     . number_format($totalDeposited, 2, ',', '.') . " TL yatırılmış. "
                                     . "FİNANSAL KAÇAK (Fark): " . number_format($diff, 2, ',', '.') . " TL",
                    'expected_value' => $expected,
                    'actual_value'   => $totalDeposited,
                    'difference'     => $diff,
                ]);

                // Tüm satırları flagle
                $orderRows->each(fn($o) => $o->update(['is_flagged' => true]));
                MpSettlement::where('period_id', $period->id)
                    ->where('order_number', $orderNumber)
                    ->update(['is_reconciled' => false, 'notes' => 'Eksik ödeme']);
                
                $count++;
            } else {
                // Mutabakat sağlandı
                MpSettlement::where('period_id', $period->id)
                    ->where('order_number', $orderNumber)
                    ->update(['is_reconciled' => true]);
            }
        }

        return $count;
    }

    // ═══════════════════════════════════════════════════════════════
    // KURAL 12: KAYIP/GECİKEN ÖDEME TESPİTİ (Faz 4 - Mutabakat)
    // Teslim edilmiş, vadesi geçmiş ama bankaya/Ödeme detay dosyasına hiç yansımamış
    // ═══════════════════════════════════════════════════════════════
    protected function checkDelayedPayments(MpPeriod $period): int
    {
        $count = 0;
        
        $now = Carbon::now();

        // Teslim edilmiş, iptal olmayan ve henüz settlement dosyasına girmemiş siparişler
        $orders = MpOrder::where('period_id', $period->id)
            ->where('status', 'Teslim Edildi')
            ->whereNotNull('delivery_date')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('mp_settlements')
                    ->whereColumn('mp_settlements.order_number', 'mp_orders.order_number')
                    ->whereColumn('mp_settlements.period_id', 'mp_orders.period_id');
            })
            ->get();

        foreach ($orders as $order) {
            // Trendyol genelde teslimattan sonra 21-28 gün vade uygular.
            // Eğer sipariş teslim edileli 35 GÜN geçmişse ve hala "Ödeme Detay" yüklemesinde yoksa Kirmizi alarm.
            $daysSinceDelivery = $order->delivery_date->diffInDays($now);
            $delayedDays = $this->settings->getDelayedPaymentDays();
            
            if ($daysSinceDelivery > $delayedDays) {
                MpAuditLog::create([
                    'period_id'      => $period->id,
                    'order_id'       => $order->id,
                    'rule_code'      => 'KAYIP_ODEME',
                    'severity'       => 'critical',
                    'title'          => "🚨 Kayıp Ödeme — Sipariş #{$order->order_number}",
                    'description'    => "Sipariş teslim edileli {$daysSinceDelivery} gün geçmiş ("
                                     . $order->delivery_date->format('d.m.Y') . "). "
                                     . "Ancak yüklediğiniz Ödeme Detay (Hakediş) dosyalarının hiçbirinde "
                                     . "bu siparişe ait banka transfer kaydı bulunmuyor! "
                                     . "Beklenen tutar: " . number_format($order->net_hakedis, 2, ',', '.') . " TL içeride kalmış olabilir.",
                    'expected_value' => $order->net_hakedis,
                    'actual_value'   => 0,
                    'difference'     => (float) $order->net_hakedis,
                ]);

                $order->update(['is_flagged' => true]);
                $count++;
            }
        }

        return $count;
    }

    // ═══════════════════════════════════════════════════════════════
    // KURAL 13: CARİ VE HAKEDİŞ UYUMSUZLUĞU (Faz 4 - Mutabakat)
    // Cari hesaba düşülen satır tutarları vs Ödemedeki tutarlar birbirini kesmiyor mu?
    // ═══════════════════════════════════════════════════════════════
    protected function checkTransactionDiscrepancy(MpPeriod $period): int
    {
        $count = 0;
        // Cari ve Ödeme tabloları arasında order_id ile yapılan kapsamlı kesinti kontrolleri eklenebilir. 
        // Temel taslak, ilerde genişletilebilir.
        return $count;
    }

    // ═══════════════════════════════════════════════════════════════
    // KURAL 14: İMKANSIZ KÂR MARJLARI (Epic 3 - Guard Rails)
    // Kâr marjı +%100 veya -%100 bandını aşıyor mu?
    // ═══════════════════════════════════════════════════════════════
    protected function checkExtremeMargins(MpPeriod $period): int
    {
        $count = 0;
        
        $orders = MpOrder::where('period_id', $period->id)
            ->whereNotIn('status', ['İptal Edildi'])
            ->where('gross_amount', '>', 0)
            ->get();

        foreach ($orders as $order) {
            $profit = $order->real_net_profit;
            $margin = ($profit / (float) $order->gross_amount) * 100;

            if ($margin > 100 || $margin < -100) {
                MpAuditLog::create([
                    'period_id'      => $period->id,
                    'order_id'       => $order->id,
                    'rule_code'      => 'EXTREME_MARGIN',
                    'severity'       => 'critical',
                    'title'          => "Kritik Kâr Marjı İhlali: %" . number_format($margin, 2),
                    'description'    => "Sipariş (Brüt: " . number_format($order->gross_amount, 2, ',', '.') . " TL) için hesaplanan net kâr " 
                                     . number_format($profit, 2, ',', '.') . " TL. "
                                     . "Bu %100'ü aşan matematiksel olarak şüpheli bir marjdır. "
                                     . "Lütfen ürün maliyetlerini (COGS) veya iade kargo cezalarını kontrol edin.",
                    'expected_value' => $order->gross_amount,
                    'actual_value'   => $profit,
                    'difference'     => abs($profit),
                ]);

                $order->update(['is_flagged' => true]);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Sipariş satırından beklenen net hakedişi hesapla.
     * Ham sipariş dosyasındaki iade/iptal/ceza gibi ekstra kalemler varsa hesaba dahil eder.
     */
    protected function calculateExpectedNetFromOrder(MpOrder $order): float
    {
        $base = (float) $order->gross_amount
            - abs((float) $order->discount_amount)
            - abs((float) $order->campaign_discount)
            - abs((float) $order->commission_amount)
            - abs((float) $order->cargo_amount)
            - abs((float) $order->service_fee);

        $raw = is_array($order->raw_data) ? $order->raw_data : [];
        if (empty($raw)) {
            return $base;
        }

        $extraDeductions = abs($this->rawToFloat($raw['refund_amount'] ?? 0))
            + abs($this->rawToFloat($raw['cancel_amount'] ?? 0))
            + abs($this->rawToFloat($raw['return_cargo_amount'] ?? 0))
            + abs($this->rawToFloat($raw['penalty_amount'] ?? 0))
            + abs($this->rawToFloat($raw['other_amount'] ?? 0))
            + abs($this->rawToFloat($raw['intl_operation_refund'] ?? 0));

        return $base - $extraDeductions;
    }

    protected function rawToFloat($value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }

        $value = (string) $value;
        $value = preg_replace('/[^\d.,-]/', '', $value) ?? '';
        if ($value === '') {
            return 0.0;
        }

        if (str_contains($value, ',') && str_contains($value, '.')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } elseif (str_contains($value, ',')) {
            $value = str_replace(',', '.', $value);
        }

        return is_numeric($value) ? (float) $value : 0.0;
    }

    // ═══════════════════════════════════════════════════════════════
    // KURAL 16: COGS_EKSIK — Maliyet Tanımsız Ürünler
    // Teslim edilen siparişlerde COGS=0 ise kârlılık hesabı güvenilmez
    // ═══════════════════════════════════════════════════════════════
    protected function checkMissingCogs(MpPeriod $period): int
    {
        $orders = MpOrder::where('period_id', $period->id)
            ->where('status', 'Teslim Edildi')
            ->where(function ($q) {
                $q->whereNull('cogs_at_time')->orWhere('cogs_at_time', 0);
            })
            ->get();

        if ($orders->isEmpty()) return 0;

        // SKU bazlı grupla → özet alarm üret
        $grouped = $orders->groupBy(fn($o) => ($o->barcode ?: $o->stock_code ?: 'unknown'));
        $count = 0;

        foreach ($grouped as $sku => $skuOrders) {
            $totalGross = $skuOrders->sum('gross_amount');
            $orderCount = $skuOrders->count();
            $sampleName = $skuOrders->first()->product_name;

            MpAuditLog::create([
                'period_id'      => $period->id,
                'rule_code'      => 'COGS_EKSIK',
                'severity'       => 'critical',
                'title'          => "Maliyet Tanımsız: {$sku}",
                'description'    => "{$sampleName} — {$orderCount} sipariş, toplam {$totalGross} ₺ brüt ciro. COGS (üretim/alış maliyeti) tanımlanmamış, kârlılık hesabı güvenilmez. Pazaryeri Ürünlerim'den maliyet giriniz.",
                'expected_value' => $totalGross,
                'actual_value'   => 0,
                'difference'     => $totalGross,
            ]);
            $count++;
        }

        return $count;
    }

    // ═══════════════════════════════════════════════════════════════
    // KURAL 17: FIYAT_DUSME — Fiyat Düşme Alarmı
    // Aynı ürünün ort. satış fiyatı önceki döneme göre %15+ düşmüşse alarm
    // ═══════════════════════════════════════════════════════════════
    protected function checkPriceDrop(MpPeriod $period): int
    {
        // Önceki dönemi bul
        $prevMonth = $period->month - 1;
        $prevYear  = $period->year;
        if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }

        $prevPeriod = MpPeriod::where('year', $prevYear)->where('month', $prevMonth)->first();
        if (!$prevPeriod) return 0;

        // Bu dönem SKU bazlı ortalama fiyat
        $currentPrices = MpOrder::where('period_id', $period->id)
            ->where('status', 'Teslim Edildi')
            ->whereNotNull('barcode')->where('barcode', '!=', '')
            ->where('gross_amount', '>', 0)
            ->selectRaw('barcode, AVG(gross_amount / GREATEST(quantity, 1)) as avg_price, COUNT(*) as cnt, MIN(product_name) as product_name')
            ->groupBy('barcode')
            ->having('cnt', '>=', 3) // En az 3 sipariş olan ürünler
            ->get()->keyBy('barcode');

        // Önceki dönem SKU bazlı ortalama fiyat
        $prevPrices = MpOrder::where('period_id', $prevPeriod->id)
            ->where('status', 'Teslim Edildi')
            ->whereNotNull('barcode')->where('barcode', '!=', '')
            ->where('gross_amount', '>', 0)
            ->selectRaw('barcode, AVG(gross_amount / GREATEST(quantity, 1)) as avg_price, COUNT(*) as cnt')
            ->groupBy('barcode')
            ->having('cnt', '>=', 3)
            ->get()->keyBy('barcode');

        $count = 0;
        foreach ($currentPrices as $barcode => $current) {
            if (!isset($prevPrices[$barcode])) continue;

            $prev = $prevPrices[$barcode];
            $dropPct = (($prev->avg_price - $current->avg_price) / $prev->avg_price) * 100;

            if ($dropPct >= 15) {
                MpAuditLog::create([
                    'period_id'      => $period->id,
                    'rule_code'      => 'FIYAT_DUSME',
                    'severity'       => 'warning',
                    'title'          => "Fiyat Düşüşü: %".round($dropPct)." — {$barcode}",
                    'description'    => "{$current->product_name} — Ort. birim fiyat: ".number_format($prev->avg_price, 2)." ₺ → ".number_format($current->avg_price, 2)." ₺ (%".round($dropPct)." düşüş). Kampanya etkisi veya piyasa değişimi olabilir.",
                    'expected_value' => round($prev->avg_price, 2),
                    'actual_value'   => round($current->avg_price, 2),
                    'difference'     => round($prev->avg_price - $current->avg_price, 2),
                ]);
                $count++;
            }
        }

        return $count;
    }

    // ═══════════════════════════════════════════════════════════════
    // KURAL 18: NEGATIF_HAKEDIS — Teslim edilen sipariş, negatif hakediş
    // ═══════════════════════════════════════════════════════════════
    protected function checkNegativeHakedis(MpPeriod $period): int
    {
        $orders = MpOrder::where('period_id', $period->id)
            ->where('status', 'Teslim Edildi')
            ->where('net_hakedis', '<', 0)
            ->get();

        $count = 0;
        foreach ($orders as $order) {
            MpAuditLog::create([
                'period_id'      => $period->id,
                'order_id'       => $order->id,
                'rule_code'      => 'NEGATIF_HAKEDIS',
                'severity'       => 'critical',
                'title'          => "Negatif Hakediş — Sipariş #{$order->order_number}",
                'description'    => "{$order->product_name} — Teslim edilmiş ancak net hakediş: ".number_format($order->net_hakedis, 2)." ₺. Kampanya zararı veya kayıt dışı iade olabilir. Brüt: ".number_format($order->gross_amount, 2)." ₺.",
                'expected_value' => $order->gross_amount,
                'actual_value'   => $order->net_hakedis,
                'difference'     => abs($order->net_hakedis),
            ]);
            $count++;
        }

        return $count;
    }

    // ═══════════════════════════════════════════════════════════════
    // KURAL 19: KARGO_MALIYET_ASIMI — Kendi kargo maliyeti kâr marjını yiyor
    // ═══════════════════════════════════════════════════════════════
    protected function checkCargoOverCost(MpPeriod $period): int
    {
        $orders = MpOrder::where('period_id', $period->id)
            ->where('status', 'Teslim Edildi')
            ->where('own_cargo_cost_at_time', '>', 0)
            ->where('net_hakedis', '>', 0)
            ->get();

        $count = 0;
        foreach ($orders as $order) {
            $profit = $order->net_hakedis - ($order->cogs_at_time ?? 0) - ($order->packaging_cost_at_time ?? 0);
            if ($profit <= 0) continue; // Zaten zararda, başka kural yakalar

            $cargoRatio = $order->own_cargo_cost_at_time / $profit;
            if ($cargoRatio >= 0.50) { // Kargo maliyeti brüt kârın %50'sinden fazla
                MpAuditLog::create([
                    'period_id'      => $period->id,
                    'order_id'       => $order->id,
                    'rule_code'      => 'KARGO_MALIYET_ASIMI',
                    'severity'       => 'warning',
                    'title'          => "Kargo Maliyeti Aşımı %" . round($cargoRatio * 100) . " — #{$order->order_number}",
                    'description'    => "{$order->product_name} — Brüt kâr (hakediş - COGS): ".number_format($profit, 2)." ₺, Kendi kargo maliyeti: ".number_format($order->own_cargo_cost_at_time, 2)." ₺ (%".round($cargoRatio * 100)."). Desi/ambalaj optimizasyonu düşünülmeli.",
                    'expected_value' => round($profit, 2),
                    'actual_value'   => round($order->own_cargo_cost_at_time, 2),
                    'difference'     => round($order->own_cargo_cost_at_time, 2),
                ]);
                $count++;
            }
        }

        return $count;
    }

    // ═══════════════════════════════════════════════════════════════
    // KURAL 20: YUKSEK_IADE — SKU bazlı yüksek iade oranı
    // ═══════════════════════════════════════════════════════════════
    protected function checkHighReturnRate(MpPeriod $period): int
    {
        // SKU bazlı sipariş ve iade sayısı
        $stats = MpOrder::where('period_id', $period->id)
            ->whereNotNull('barcode')->where('barcode', '!=', '')
            ->selectRaw("
                barcode,
                MIN(product_name) as product_name,
                SUM(CASE WHEN status = 'Teslim Edildi' THEN quantity ELSE 0 END) as delivered,
                SUM(CASE WHEN status = 'İade Edildi' THEN quantity ELSE 0 END) as returned,
                COUNT(*) as total_orders
            ")
            ->groupBy('barcode')
            ->get();

        $count = 0;
        foreach ($stats as $stat) {
            $total = $stat->delivered + $stat->returned;
            if ($total < 5) continue; // Minimum sipariş eşiği

            $returnRate = $total > 0 ? ($stat->returned / $total) * 100 : 0;
            if ($returnRate >= 15) {
                MpAuditLog::create([
                    'period_id'      => $period->id,
                    'rule_code'      => 'YUKSEK_IADE',
                    'severity'       => 'warning',
                    'title'          => "Yüksek İade Oranı: %" . round($returnRate) . " — {$stat->barcode}",
                    'description'    => "{$stat->product_name} — Toplam {$total} adet, {$stat->returned} iade (%".round($returnRate)."). Ürün kalitesi, paketleme veya listing bilgilerini gözden geçirin.",
                    'expected_value' => $total,
                    'actual_value'   => $stat->returned,
                    'difference'     => $stat->returned,
                ]);
                $count++;
            }
        }

        return $count;
    }

    // ═══════════════════════════════════════════════════════════════
    // KURAL 21: KAMPANYA_ZARAR — Kampanya İndirimi Sonucu Zarar
    // ═══════════════════════════════════════════════════════════════
    protected function checkCampaignLoss(MpPeriod $period): int
    {
        $orders = MpOrder::where('period_id', $period->id)
            ->where('status', 'Teslim Edildi')
            ->where('campaign_discount', '>', 0)
            ->where('cogs_at_time', '>', 0) // COGS tanımlı olmalı
            ->get();

        // SKU bazlı grupla
        $grouped = $orders->groupBy(fn($o) => ($o->barcode ?: $o->stock_code ?: 'unknown'));
        $count = 0;

        foreach ($grouped as $sku => $skuOrders) {
            $totalLoss = 0;
            $lossOrders = 0;

            foreach ($skuOrders as $order) {
                $netProfit = $order->net_hakedis
                    - ($order->cogs_at_time ?? 0)
                    - ($order->packaging_cost_at_time ?? 0)
                    - ($order->own_cargo_cost_at_time ?? 0);

                if ($netProfit < 0) {
                    $totalLoss += abs($netProfit);
                    $lossOrders++;
                }
            }

            if ($lossOrders > 0) {
                $totalCampaignDiscount = $skuOrders->sum('campaign_discount');
                $sampleName = $skuOrders->first()->product_name;

                MpAuditLog::create([
                    'period_id'      => $period->id,
                    'rule_code'      => 'KAMPANYA_ZARAR',
                    'severity'       => 'critical',
                    'title'          => "Kampanya Zararı: {$sku} — {$lossOrders} sipariş",
                    'description'    => "{$sampleName} — {$lossOrders}/{$skuOrders->count()} kampanyalı siparişte zarar. Toplam kampanya indirimi: ".number_format($totalCampaignDiscount, 2)." ₺, toplam zarar: ".number_format($totalLoss, 2)." ₺. Kampanya katılımını gözden geçirin.",
                    'expected_value' => 0,
                    'actual_value'   => round($totalLoss, 2),
                    'difference'     => round($totalLoss, 2),
                ]);
                $count++;
            }
        }

        return $count;
    }

    // ═══════════════════════════════════════════════════════════════
    // KURAL 22: KOMISYON_ORANI_DEGISIMI — Sessiz Komisyon Artışı
    // ═══════════════════════════════════════════════════════════════
    protected function checkCommissionRateChange(MpPeriod $period): int
    {
        $prevMonth = $period->month - 1;
        $prevYear  = $period->year;
        if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }

        $prevPeriod = MpPeriod::where('year', $prevYear)->where('month', $prevMonth)->first();
        if (!$prevPeriod) return 0;

        // Bu dönem SKU bazlı ortalama komisyon oranı
        $currentRates = MpOrder::where('period_id', $period->id)
            ->where('status', 'Teslim Edildi')
            ->whereNotNull('barcode')->where('barcode', '!=', '')
            ->where('commission_rate', '>', 0)
            ->selectRaw('barcode, AVG(commission_rate) as avg_rate, COUNT(*) as cnt, MIN(product_name) as product_name')
            ->groupBy('barcode')
            ->having('cnt', '>=', 3)
            ->get()->keyBy('barcode');

        // Önceki dönem
        $prevRates = MpOrder::where('period_id', $prevPeriod->id)
            ->where('status', 'Teslim Edildi')
            ->whereNotNull('barcode')->where('barcode', '!=', '')
            ->where('commission_rate', '>', 0)
            ->selectRaw('barcode, AVG(commission_rate) as avg_rate, COUNT(*) as cnt')
            ->groupBy('barcode')
            ->having('cnt', '>=', 3)
            ->get()->keyBy('barcode');

        $count = 0;
        foreach ($currentRates as $barcode => $current) {
            if (!isset($prevRates[$barcode])) continue;

            $prev = $prevRates[$barcode];
            $diff = $current->avg_rate - $prev->avg_rate;

            // %1 puandan fazla artış varsa uyar (ör: %15 → %16.5)
            if ($diff >= 1.0) {
                MpAuditLog::create([
                    'period_id'      => $period->id,
                    'rule_code'      => 'KOMISYON_ORANI_DEGISIMI',
                    'severity'       => 'warning',
                    'title'          => "Komisyon Artışı: +" . round($diff, 1) . " puan — {$barcode}",
                    'description'    => "{$current->product_name} — Komisyon oranı: %" . round($prev->avg_rate, 1) . " → %" . round($current->avg_rate, 1) . " (+" . round($diff, 1) . " puan). Trendyol kategori/politika değişikliği olabilir.",
                    'expected_value' => round($prev->avg_rate, 2),
                    'actual_value'   => round($current->avg_rate, 2),
                    'difference'     => round($diff, 2),
                ]);
                $count++;
            }
        }

        return $count;
    }

    // ═══════════════════════════════════════════════════════════════
    // KURAL 23: HIZMET_BEDELI_ARTISI — Trendyol Hizmet Bedeli Artışı
    // ═══════════════════════════════════════════════════════════════
    protected function checkServiceFeeIncrease(MpPeriod $period): int
    {
        $prevMonth = $period->month - 1;
        $prevYear  = $period->year;
        if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }

        $prevPeriod = MpPeriod::where('year', $prevYear)->where('month', $prevMonth)->first();
        if (!$prevPeriod) return 0;

        // Bu dönem: toplam hizmet bedeli / toplam brüt = oran
        $currentStats = MpOrder::where('period_id', $period->id)
            ->where('status', 'Teslim Edildi')
            ->where('gross_amount', '>', 0)
            ->selectRaw('SUM(service_fee) as total_service, SUM(gross_amount) as total_gross, COUNT(*) as cnt')
            ->first();

        $prevStats = MpOrder::where('period_id', $prevPeriod->id)
            ->where('status', 'Teslim Edildi')
            ->where('gross_amount', '>', 0)
            ->selectRaw('SUM(service_fee) as total_service, SUM(gross_amount) as total_gross, COUNT(*) as cnt')
            ->first();

        if (!$currentStats || !$prevStats || $currentStats->total_gross <= 0 || $prevStats->total_gross <= 0) return 0;

        $currentRate = ($currentStats->total_service / $currentStats->total_gross) * 100;
        $prevRate    = ($prevStats->total_service / $prevStats->total_gross) * 100;
        $diff        = $currentRate - $prevRate;

        // %0.5 puandan fazla artış varsa uyar
        if ($diff >= 0.5) {
            MpAuditLog::create([
                'period_id'      => $period->id,
                'rule_code'      => 'HIZMET_BEDELI_ARTISI',
                'severity'       => 'warning',
                'title'          => "Hizmet Bedeli Artışı: +" . round($diff, 2) . " puan",
                'description'    => "Trendyol hizmet bedeli oranı: %" . round($prevRate, 2) . " → %" . round($currentRate, 2) . ". Toplam hizmet bedeli: " . number_format($currentStats->total_service, 2) . " ₺ (önceki ay: " . number_format($prevStats->total_service, 2) . " ₺). Bu artış tüm siparişlerde marjınızı düşürür.",
                'expected_value' => round($prevRate, 2),
                'actual_value'   => round($currentRate, 2),
                'difference'     => round($currentStats->total_service - $prevStats->total_service, 2),
            ]);
            return 1;
        }

        return 0;
    }

    // ═══════════════════════════════════════════════════════════════
    // KURAL 24: IPTAL_ORANI — SKU Bazlı Yüksek İptal Oranı
    // ═══════════════════════════════════════════════════════════════
    protected function checkHighCancellationRate(MpPeriod $period): int
    {
        $stats = MpOrder::where('period_id', $period->id)
            ->whereNotNull('barcode')->where('barcode', '!=', '')
            ->selectRaw("
                barcode,
                MIN(product_name) as product_name,
                COUNT(*) as total_orders,
                SUM(CASE WHEN status = 'İptal Edildi' THEN 1 ELSE 0 END) as cancelled,
                SUM(CASE WHEN status = 'Teslim Edildi' THEN 1 ELSE 0 END) as delivered
            ")
            ->groupBy('barcode')
            ->get();

        $count = 0;
        foreach ($stats as $stat) {
            if ($stat->total_orders < 5) continue;

            $cancelRate = ($stat->cancelled / $stat->total_orders) * 100;
            if ($cancelRate >= 10) {
                MpAuditLog::create([
                    'period_id'      => $period->id,
                    'rule_code'      => 'IPTAL_ORANI',
                    'severity'       => 'warning',
                    'title'          => "Yüksek İptal Oranı: %" . round($cancelRate) . " — {$stat->barcode}",
                    'description'    => "{$stat->product_name} — {$stat->total_orders} siparişten {$stat->cancelled} tanesi iptal (%" . round($cancelRate) . "). Stok yönetimi kontrol edilmeli, Trendyol algoritmik sıralama düşüşü ve operasyonel ceza riski var.",
                    'expected_value' => $stat->total_orders,
                    'actual_value'   => $stat->cancelled,
                    'difference'     => $stat->cancelled,
                ]);
                $count++;
            }
        }

        return $count;
    }
}
