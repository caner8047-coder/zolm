<?php

namespace App\Services;

use App\Models\MpPeriod;
use App\Models\MpOrder;
use App\Models\MpTransaction;
use App\Models\MpAuditLog;
use App\Models\MpSettlement;
use App\Services\MpSettingsService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;
use Carbon\Carbon;

/**
 * Pazaryeri Muhasebe — Hata Denetim Motoru (Audit Engine)
 *
 * @deprecated V2 MarketplaceSettlementAuditQueryService kullanın.
 *
 * Import sonrası sipariş ve ekstre verilerini karşılaştırarak
 * hatalı kesintileri, barem aşımlarını ve kayıpları tespit eder.
 * Sonuçları mp_audit_logs tablosuna yazar.
 */
class AuditEngine
{
    protected MpSettingsService $settings;
    protected array $userPeriodIdsCache = [];

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
            'severity' => 'info',
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

    /**
     * Yardımcı / türetilmiş audit log kodları.
     * Bunlar bağımsız toggle edilen kurallar değil, ana kuralların ürettiği alt bulgulardır.
     */
    public const AUXILIARY_LOG_META = [
        'KISMI_IADE' => [
            'code'     => 'KISMI_IADE',
            'title'    => 'Kısmi İade Bilgilendirmesi',
            'tooltip'  => 'Ödeme detayında kısmi iade etkisi görüldü. Bu durum gerçek finansal kaçak anlamına gelmeyebilir.',
            'severity' => 'info',
            'category' => 'Ödeme',
            'icon'     => '↩️',
        ],
        'HAKEDIS_ILLUZYON' => [
            'code'     => 'HAKEDIS_ILLUZYON',
            'title'    => 'Hakediş Raporlama Sapması',
            'tooltip'  => 'Sipariş Excel’inde indirim/kampanya kaynaklı görsel sapma tespit edildi. Finansal kayıp olmayabilir.',
            'severity' => 'info',
            'category' => 'Ödeme',
            'icon'     => '🪄',
        ],
        'KAYIP_TAZMINATI' => [
            'code'     => 'KAYIP_TAZMINATI',
            'title'    => 'Kayıp Tazminatı Alacağı',
            'tooltip'  => 'Cari hesapta satıcı lehine tazminat/alacak kaydı bulundu.',
            'severity' => 'info',
            'category' => 'Ceza',
            'icon'     => '🧾',
        ],
        'KOMISYON_IADE_OZET' => [
            'code'     => 'KOMISYON_IADE_OZET',
            'title'    => 'Komisyon İadesi Özet Uyarısı',
            'tooltip'  => 'Dönem genelinde komisyon iadesi eksik görünen siparişlerin toplu özeti.',
            'severity' => 'warning',
            'category' => 'Komisyon',
            'icon'     => '📌',
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

        return self::AUXILIARY_LOG_META[$ruleCode] ?? null;
    }

    public function __construct(?MpSettingsService $settings = null)
    {
        $this->settings = $settings ?? new MpSettingsService();
    }

    protected function createAuditLog(array $payload): ?MpAuditLog
    {
        if (($payload['severity'] ?? 'warning') === 'info' && !$this->settings->shouldLogInfoRules()) {
            return null;
        }

        return MpAuditLog::create($payload);
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

        $disabledRules = array_values(array_unique(array_merge(
            $this->settings->getDisabledAuditRules(),
            $disabledRules
        )));

        if (!$this->settings->shouldLogInfoRules()) {
            $disabledRules = array_values(array_unique(array_merge(
                $disabledRules,
                collect(self::RULE_META)
                    ->filter(fn (array $meta) => ($meta['severity'] ?? null) === 'info')
                    ->keys()
                    ->all()
            )));
        }

        foreach (self::RULES as $rule) {
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
    // Brüt Satış × %1 ≈ Kesilen Stopaj (±0.50 TL tolerans)
    // ═══════════════════════════════════════════════════════════════

    protected function checkStopaj(MpPeriod $period): int
    {
        $count = 0;
        $stopajRate = $this->settings->getStopajRate();
        $tolerance = $this->settings->getFloat('audit_tolerances.stopaj_tolerance', 0.50);
        $defaultVatRate = $this->settings->getDefaultProductVatRate() * 100;

        $orders = MpOrder::where('period_id', $period->id)
            ->where('gross_amount', '>', 0)
            ->whereNotIn('status', ['İptal Edildi'])
            ->get();

        foreach ($orders as $order) {
            // Stopaj KDV'siz (Matrah) üzerinden hesaplanır.
            // Zemin: %1, %10 veya %20 KDV olabilir.

            $vatRate = (float) ($order->resolved_product_vat_rate ?? $defaultVatRate);
            $matrahMultiplier = 1 / (1 + ($vatRate / 100));
            $expectedBase = max(0, (float) $order->gross_amount - (float) $order->discount_amount - (float) $order->campaign_discount) * $matrahMultiplier;
            $expectedStopaj = $expectedBase * $stopajRate;
            $actualStopaj = abs((float) $order->withholding_tax);
            $diff = abs($expectedStopaj - $actualStopaj);

            if ($diff > $tolerance) {
                $this->createAuditLog([
                    'period_id'      => $period->id,
                    'order_id'       => $order->id,
                    'rule_code'      => 'STOPAJ',
                    'severity'       => 'warning',
                    'title'          => "Stopaj Doğrulaması — Sipariş #{$order->order_number}",
                    'description'    => 'E-Ticaret stopaj kesintisi beklenen matrah ile uyuşmuyor. '
                        . 'Beklenen: ' . number_format($expectedStopaj, 2, ',', '.') . ' TL, '
                        . 'Kesilen: ' . number_format($actualStopaj, 2, ',', '.') . ' TL. '
                        . 'Fark: ' . number_format($diff, 2, ',', '.') . ' TL.',
                    'expected_value' => round($expectedStopaj, 2),
                    'actual_value'   => round($actualStopaj, 2),
                    'difference'     => round($diff, 2),
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
            ->with(['period', 'operationalOrder.items'])
            ->get();

        foreach ($orders as $order) {
            $reference = $this->resolveCommissionReference($order);
            if ($reference === null) {
                continue;
            }

            $expectedCommission = (float) $order->gross_amount * ($reference['rate'] / 100);
            $actualCommission = abs((float) $order->commission_amount);
            $diff = abs($actualCommission - $expectedCommission);

            if ($diff > $tolerance) {
                $actualRatio = $order->gross_amount > 0 ? ($actualCommission / $order->gross_amount) * 100 : 0;

                $this->createAuditLog([
                    'period_id'      => $period->id,
                    'order_id'       => $order->id,
                    'rule_code'      => 'KOMISYON_TUTARSIZLIGI',
                    'severity'       => 'warning',
                    'title'          => "Komisyon Tutarsızlığı — Sipariş #{$order->order_number}",
                    'description'    => "Kesilen sipariş komisyonu ({$actualCommission} TL, %" . round($actualRatio, 2) . ") "
                                     . "ile {$reference['source']} referans oranına göre beklenen komisyon (" . round($expectedCommission, 2) . " TL, %{$reference['rate']}) uyuşmuyor. "
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
                $this->createAuditLog([
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
            $commissionRefund = $this->resolveOrderTransactions($period, $order->order_number)
                ->filter(function (MpTransaction $transaction) {
                    return ((float) $transaction->credit) > 0
                        && (
                            str_contains(mb_strtolower($transaction->transaction_type ?? ''), 'komisyon')
                            || str_contains(mb_strtolower($transaction->description ?? ''), 'komisyon')
                        );
                })
                ->sum('credit');

            // Komisyon iadesi alınmamış veya eksik
            $expectedRefund = (float) $order->commission_amount;
            $diff = $expectedRefund - $commissionRefund;

            $refundTolerance = $this->settings->getFloat('audit_tolerances.commission_refund_tolerance', 0.50);
            if ($diff > $refundTolerance) {
                $this->createAuditLog([
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
            $returnCargoFee = $this->resolveOrderTransactions($period, $order->order_number)
                ->filter(function (MpTransaction $transaction) {
                    $haystack = mb_strtolower(($transaction->transaction_type ?? '') . ' ' . ($transaction->description ?? ''));

                    return (float) $transaction->debt > 0
                        && str_contains($haystack, 'iade kargo');
                })
                ->sum('debt');

            $totalLoss = $sunkCost + $returnCargoFee;

            if ($totalLoss > 0) {
                $this->createAuditLog([
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
            $qty = (int) $order->resolved_quantity;
            if ($qty > 1 && $reported > 0 && $reported < $calculated) {
                // Çoklu adetli siparişlerde calculated her zaman toplam, 
                // reported ise adet başına veya kısmi olabilir → bu farkı yoksay
                $diff = 0;
            }

            $hakedisTolerance = $this->settings->getFloat('audit_tolerances.hakedis_tolerance', 1.0);
            if ($diff > $hakedisTolerance) {
                // ── ADIM 1: Settlement verisiyle doğrulama (Tüm kayıtlar toplanır) ──
                $allSettlements = $this->resolveOrderSettlements($period, $order->order_number, $order->id);
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
                        $log = $this->createAuditLog([
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
                        if ($log) {
                            $count++;
                        }
                        continue; // is_flagged yapma
                    }

                    // B) İade yok, net settlement hesaplananla uyumlu mu?
                    if ($netSettlement >= $calculated * 0.90) {
                        // Banka ödemesi doğru → tamamen gizle
                        continue;
                    }
                    
                    // C) Quantity kontrol: Ödeme Excel per-unit satır yazıyor olabilir
                    $qty = (int) $order->resolved_quantity;
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
                    $log = $this->createAuditLog([
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
                    if ($log) {
                        $count++;
                    }
                } else {
                    // ── ADIM 3: Gerçek tutarsızlık ──
                    $this->createAuditLog([
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

            $log = $this->createAuditLog([
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
            if ($log) {
                $count++;
            }
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
                    $log = $this->createAuditLog([
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
                    if ($log) {
                        $count++;
                    }
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
            $log = $this->createAuditLog([
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
            if ($log) {
                $count++;
            }
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
                $this->createAuditLog([
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
            $refundCredit = $this->resolveOrderTransactions($period, $order->order_number)
                ->filter(function (MpTransaction $transaction) use ($order) {
                    $type = mb_strtolower($transaction->transaction_type ?? '');
                    $description = mb_strtolower($transaction->description ?? '');

                    if ((float) $transaction->credit <= 0) {
                        return false;
                    }

                    return str_contains($type, 'komisyon')
                        || str_contains($type, 'iade')
                        || str_contains($description, 'komisyon iade')
                        || str_contains($description, mb_strtolower($order->order_number));
                })
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
            $this->createAuditLog([
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
            $this->createAuditLog([
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
            $allSettlements = $this->resolveOrderSettlements($period, $orderNumber, $firstOrder?->id);
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
                $this->updateSettlementReconciliation($allSettlements, true);
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
            $totalQuantity = $orderRows->sum(fn(MpOrder $row) => $row->resolved_quantity);
            $positiveSettlementRows = $allSettlements->filter(fn($s) => (float) $s->seller_hakedis > 0)->count();
            
            if ($totalQuantity > 1 && $totalDeposited > 0 && $totalDeposited < $expected) {
                // Pozitif settlement satır sayısı, toplam adetten az ise 
                // → henüz tüm adetlerin ödeme kaydı yüklenmemiş demektir
                if ($positiveSettlementRows < $totalQuantity) {
                    $this->updateSettlementReconciliation($allSettlements, true);
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
                    $this->updateSettlementReconciliation($allSettlements, true);
                    continue;
                }

                $this->createAuditLog([
                    'period_id'      => $period->id,
                    'order_id'       => $firstOrder->id,
                    'rule_code'      => 'EKSIK_ODEME',
                'severity'       => $diff > $this->settings->getFloat('audit_tolerances.missing_payment_critical_threshold', 10) ? 'critical' : 'warning',
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
                $this->updateSettlementReconciliation($allSettlements, false, 'Eksik ödeme');
                
                $count++;
            } else {
                // Mutabakat sağlandı
                $this->updateSettlementReconciliation($allSettlements, true);
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
            ->get();

        foreach ($orders->groupBy('order_number') as $orderNumber => $orderRows) {
            /** @var MpOrder $order */
            $order = $orderRows->sortBy(fn(MpOrder $row) => $row->delivery_date?->getTimestamp() ?? PHP_INT_MAX)->first();
            $settlements = $this->resolveOrderSettlements($period, $orderNumber, $order?->id);
            $hasPositiveSettlement = $settlements->contains(fn(MpSettlement $settlement) => (float) $settlement->seller_hakedis > 0);

            if ($hasPositiveSettlement || !$order) {
                continue;
            }

            // Trendyol genelde teslimattan sonra 21-28 gün vade uygular.
            // Eğer sipariş teslim edileli 35 GÜN geçmişse ve hala "Ödeme Detay" yüklemesinde yoksa Kirmizi alarm.
            $daysSinceDelivery = (int) $order->delivery_date
                ->copy()
                ->startOfDay()
                ->diffInDays($now->copy()->startOfDay());
            $delayedDays = $this->settings->getDelayedPaymentDays();
            
            if ($daysSinceDelivery > $delayedDays) {
                $expectedNet = (float) $orderRows->sum('net_hakedis');
                $this->createAuditLog([
                    'period_id'      => $period->id,
                    'order_id'       => $order->id,
                    'rule_code'      => 'KAYIP_ODEME',
                    'severity'       => 'critical',
                    'title'          => "🚨 Kayıp Ödeme — Sipariş #{$orderNumber}",
                    'description'    => "Sipariş teslim edileli {$daysSinceDelivery} gün geçmiş ("
                                     . $order->delivery_date->format('d.m.Y') . "). "
                                     . "Ancak yüklediğiniz Ödeme Detay (Hakediş) dosyalarının hiçbirinde "
                                     . "bu siparişe ait banka transfer kaydı bulunmuyor! "
                                     . "Beklenen tutar: " . number_format($expectedNet, 2, ',', '.') . " TL içeride kalmış olabilir.",
                    'expected_value' => $expectedNet,
                    'actual_value'   => 0,
                    'difference'     => $expectedNet,
                ]);

                $orderRows->each(fn(MpOrder $row) => $row->update(['is_flagged' => true]));
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
        $commissionTolerance = $this->settings->getCommissionMatchTolerance();
        $cargoTolerance = $this->settings->getCargoMatchTolerance();

        // Ham değerleri karşılaştır — KDV bölücü uygulamıyoruz çünkü
        // hem MpTransaction (ekstre) hem MpOrder tutarları KDV dahil formattadır.
        $transactionCommission = MpTransaction::where('period_id', $period->id)
            ->where(function ($query) {
                $query->where('transaction_type', 'like', '%Komisyon%')
                    ->orWhere('description', 'like', '%Komisyon%');
            })
            ->sum('debt');

        $transactionCargo = MpTransaction::where('period_id', $period->id)
            ->where(function ($query) {
                $query->where('transaction_type', 'like', '%Kargo%')
                    ->orWhere('description', 'like', '%Kargo%');
            })
            ->sum('debt');

        $orderCommission = (float) (MpOrder::where('period_id', $period->id)
            ->selectRaw('SUM(ABS(commission_amount)) as total')
            ->value('total') ?? 0);

        $orderCargo = (float) (MpOrder::where('period_id', $period->id)
            ->selectRaw('SUM(ABS(cargo_amount)) as total')
            ->value('total') ?? 0);

        $checks = [];

        if ($this->settings->getBool('audit_behavior.transaction_check_commission_enabled', true)) {
            $checks[] = [
                'title' => 'Komisyon',
                'expected' => abs($orderCommission),
                'actual' => abs((float) $transactionCommission),
                'tolerance' => $commissionTolerance,
            ];
        }

        if ($this->settings->getBool('audit_behavior.transaction_check_cargo_enabled', true)) {
            $checks[] = [
                'title' => 'Kargo',
                'expected' => abs($orderCargo),
                'actual' => abs((float) $transactionCargo),
                'tolerance' => $cargoTolerance,
            ];
        }

        foreach ($checks as $check) {
            if ($check['expected'] <= 0 && $check['actual'] <= 0) {
                continue;
            }

            $diff = abs($check['expected'] - $check['actual']);
            if ($diff <= $check['tolerance']) {
                continue;
            }

            $this->createAuditLog([
                'period_id'      => $period->id,
                'order_id'       => null,
                'rule_code'      => 'CARI_UYUMSUZLUK',
                'severity'       => 'warning',
                'title'          => "{$check['title']} Cari-Hakediş Uyumsuzluğu",
                'description'    => "{$check['title']} kesintilerinin cari hesap ekstresi toplamı ile siparişlerden türetilen netleştirilmiş tutarı uyuşmuyor. "
                    . "Beklenen: " . number_format($check['expected'], 2, ',', '.') . " TL, "
                    . "Cari'de görülen: " . number_format($check['actual'], 2, ',', '.') . " TL. "
                    . "Fark: " . number_format($diff, 2, ',', '.') . " TL.",
                'expected_value' => round($check['expected'], 2),
                'actual_value'   => round($check['actual'], 2),
                'difference'     => round($diff, 2),
            ]);
            $count++;
        }

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

            $positiveThreshold = $this->settings->getFloat('audit_tolerances.extreme_margin_positive_threshold', 100);
            $negativeThreshold = $this->settings->getFloat('audit_tolerances.extreme_margin_negative_threshold', -100);

            if ($margin > $positiveThreshold || $margin < $negativeThreshold) {
                $this->createAuditLog([
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
            ->with(['period', 'operationalOrder.items'])
            ->get();
        $orders = $orders->filter(fn(MpOrder $order) => (float) $order->resolved_cogs_at_time <= 0);

        if ($orders->isEmpty()) return 0;

        // SKU bazlı grupla → özet alarm üret
        $grouped = $orders->groupBy(fn(MpOrder $order) => $this->resolveOrderSkuKey($order));
        $count = 0;

        foreach ($grouped as $sku => $skuOrders) {
            $totalGross = $skuOrders->sum('gross_amount');
            $orderCount = $skuOrders->count();
            /** @var MpOrder $sampleOrder */
            $sampleOrder = $skuOrders->first();
            $sampleName = $sampleOrder->resolved_product_name ?: $sampleOrder->product_name ?: 'Urun bilgisi eksik';
            $reason = $sampleOrder->cogs_missing_reason ?: 'COGS eslestirmesi kurulamadi.';

            $this->createAuditLog([
                'period_id'      => $period->id,
                'rule_code'      => 'COGS_EKSIK',
                'severity'       => 'critical',
                'title'          => "Maliyet Tanımsız: {$sku}",
                'description'    => "{$sampleName} — {$orderCount} sipariş, toplam {$totalGross} ₺ brüt ciro. COGS (üretim/alış maliyeti) tanımlanmamış, kârlılık hesabı güvenilmez. {$reason}",
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
        $prevPeriod = $this->resolvePreviousComparablePeriod($period);
        if (!$prevPeriod) return 0;

        $minOrders = max(1, $this->settings->getInt('audit_tolerances.price_drop_min_orders', 3));
        $dropThreshold = $this->settings->getFloat('audit_tolerances.price_drop_percentage', 15);
        $count = 0;

        $currentPrices = $this->buildResolvedUnitPriceStats(
            $this->loadComparableOrders($period->id, ['Teslim Edildi']),
            $minOrders
        );

        $prevPrices = $this->buildResolvedUnitPriceStats(
            $this->loadComparableOrders($prevPeriod->id, ['Teslim Edildi']),
            $minOrders
        );

        foreach ($currentPrices as $skuKey => $current) {
            if (!isset($prevPrices[$skuKey])) continue;

            $prev = $prevPrices[$skuKey];
            $dropPct = (($prev->avg_price - $current->avg_price) / $prev->avg_price) * 100;

            if ($dropPct >= $dropThreshold) {
                $this->createAuditLog([
                    'period_id'      => $period->id,
                    'rule_code'      => 'FIYAT_DUSME',
                    'severity'       => 'warning',
                    'title'          => "Fiyat Düşüşü: %".round($dropPct)." — {$current->display_key}",
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
        $negativeThreshold = $this->settings->getFloat('audit_tolerances.negative_hakedis_threshold', 0);

        $orders = MpOrder::where('period_id', $period->id)
            ->where('status', 'Teslim Edildi')
            ->where('net_hakedis', '<', $negativeThreshold)
            ->get();

        $count = 0;
        foreach ($orders as $order) {
            $this->createAuditLog([
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
        if (!$this->settings->usesOwnCargo()) {
            return 0;
        }

        $orders = MpOrder::where('period_id', $period->id)
            ->where('status', 'Teslim Edildi')
            ->where('net_hakedis', '>', 0)
            ->with(['period', 'operationalOrder.items'])
            ->get();

        $count = 0;
        foreach ($orders as $order) {
            $resolvedOwnCargo = (float) $order->resolved_own_cargo_cost_at_time;
            if ($resolvedOwnCargo <= 0) {
                continue;
            }

            $profit = (float) $order->net_hakedis
                - (float) $order->resolved_cogs_at_time
                - (float) $order->resolved_packaging_cost_at_time;
            if ($profit <= 0) continue; // Zaten zararda, başka kural yakalar

            $cargoRatio = $resolvedOwnCargo / $profit;
            $ratioThreshold = $this->settings->getFloat('audit_tolerances.cargo_over_cost_ratio', 0.50);

            if ($cargoRatio >= $ratioThreshold) {
                $this->createAuditLog([
                    'period_id'      => $period->id,
                    'order_id'       => $order->id,
                    'rule_code'      => 'KARGO_MALIYET_ASIMI',
                    'severity'       => 'warning',
                    'title'          => "Kargo Maliyeti Aşımı %" . round($cargoRatio * 100) . " — #{$order->order_number}",
                    'description'    => "{$order->resolved_product_name} — Brüt kâr (hakediş - COGS - ambalaj): ".number_format($profit, 2)." ₺, Kendi kargo maliyeti: ".number_format($resolvedOwnCargo, 2)." ₺ (%".round($cargoRatio * 100)."). Desi/ambalaj optimizasyonu düşünülmeli.",
                    'expected_value' => round($profit, 2),
                    'actual_value'   => round($resolvedOwnCargo, 2),
                    'difference'     => round($resolvedOwnCargo, 2),
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
        $minQuantity = max(1, $this->settings->getInt('audit_tolerances.high_return_rate_min_quantity', 5));
        $returnThreshold = $this->settings->getFloat('audit_tolerances.high_return_rate_threshold', 15);
        $stats = $this->buildResolvedReturnRateStats(
            $this->loadComparableOrders($period->id, ['Teslim Edildi', 'İade Edildi'])
        );

        $count = 0;
        foreach ($stats as $stat) {
            $total = $stat->delivered + $stat->returned;
            if ($total < $minQuantity) continue;

            $returnRate = $total > 0 ? ($stat->returned / $total) * 100 : 0;
            if ($returnRate >= $returnThreshold) {
                $this->createAuditLog([
                    'period_id'      => $period->id,
                    'rule_code'      => 'YUKSEK_IADE',
                    'severity'       => 'warning',
                    'title'          => "Yüksek İade Oranı: %" . round($returnRate) . " — {$stat->display_key}",
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
            ->with(['period', 'operationalOrder.items'])
            ->get();
        $orders = $orders->filter(fn(MpOrder $order) => (float) $order->resolved_cogs_at_time > 0);

        // SKU bazlı grupla
        $grouped = $orders
            ->groupBy(fn(MpOrder $order) => $this->resolveComparableSkuKey($order) ?? ('order:' . $order->order_number));
        $count = 0;

        foreach ($grouped as $sku => $skuOrders) {
            $totalLoss = 0;
            $lossOrders = 0;
            $includeOwnCargo = $this->settings->usesOwnCargo();

            foreach ($skuOrders as $order) {
                $netProfit = $order->net_hakedis
                    - ($order->resolved_cogs_at_time ?? 0)
                    - ($order->resolved_packaging_cost_at_time ?? 0)
                    - ($includeOwnCargo ? ($order->resolved_own_cargo_cost_at_time ?? 0) : 0);

                if ($netProfit < 0) {
                    $totalLoss += abs($netProfit);
                    $lossOrders++;
                }
            }

            $minLoss = $this->settings->getFloat('audit_tolerances.campaign_loss_min_total_loss', 0);
            $minOrderCount = max(1, $this->settings->getInt('audit_tolerances.campaign_loss_min_order_count', 1));

            if ($lossOrders >= $minOrderCount && $totalLoss >= $minLoss) {
                $totalCampaignDiscount = $skuOrders->sum('campaign_discount');
                $sampleName = $skuOrders->first()->resolved_product_name ?: $skuOrders->first()->product_name;
                $displayKey = $this->resolveDisplaySkuKey($skuOrders->first());

                $this->createAuditLog([
                    'period_id'      => $period->id,
                    'rule_code'      => 'KAMPANYA_ZARAR',
                    'severity'       => 'critical',
                    'title'          => "Kampanya Zararı: {$displayKey} — {$lossOrders} sipariş",
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
        $prevPeriod = $this->resolvePreviousComparablePeriod($period);
        if (!$prevPeriod) return 0;

        $minOrders = max(1, $this->settings->getInt('audit_tolerances.commission_rate_change_min_orders', 3));
        $rateThreshold = $this->settings->getFloat('audit_tolerances.commission_rate_change_threshold', 1.0);

        $currentRates = $this->buildResolvedCommissionRateStats(
            $this->loadComparableOrders($period->id, ['Teslim Edildi']),
            $minOrders
        );

        $prevRates = $this->buildResolvedCommissionRateStats(
            $this->loadComparableOrders($prevPeriod->id, ['Teslim Edildi']),
            $minOrders
        );

        $count = 0;
        foreach ($currentRates as $skuKey => $current) {
            if (!isset($prevRates[$skuKey])) continue;

            $prev = $prevRates[$skuKey];
            $diff = $current->avg_rate - $prev->avg_rate;

            // %1 puandan fazla artış varsa uyar (ör: %15 → %16.5)
            if ($diff >= $rateThreshold) {
                $this->createAuditLog([
                    'period_id'      => $period->id,
                    'rule_code'      => 'KOMISYON_ORANI_DEGISIMI',
                    'severity'       => 'warning',
                    'title'          => "Komisyon Artışı: +" . round($diff, 1) . " puan — {$current->display_key}",
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
        $prevPeriod = $this->resolvePreviousComparablePeriod($period);
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

        $minOrders = max(1, $this->settings->getInt('audit_tolerances.service_fee_increase_min_orders', 20));
        $increaseThreshold = $this->settings->getFloat('audit_tolerances.service_fee_increase_threshold', 0.5);

        if (
            !$currentStats || !$prevStats
            || $currentStats->total_gross <= 0 || $prevStats->total_gross <= 0
            || (int) ($currentStats->cnt ?? 0) < $minOrders
            || (int) ($prevStats->cnt ?? 0) < $minOrders
        ) {
            return 0;
        }

        $currentRate = ($currentStats->total_service / $currentStats->total_gross) * 100;
        $prevRate    = ($prevStats->total_service / $prevStats->total_gross) * 100;
        $diff        = $currentRate - $prevRate;

        // %0.5 puandan fazla artış varsa uyar
        if ($diff >= $increaseThreshold) {
            $this->createAuditLog([
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
        $minOrders = max(1, $this->settings->getInt('audit_tolerances.high_cancellation_rate_min_orders', 5));
        $cancelThreshold = $this->settings->getFloat('audit_tolerances.high_cancellation_rate_threshold', 10);

        $stats = $this->buildResolvedCancellationRateStats(
            $this->loadComparableOrders($period->id)
        );

        $count = 0;
        foreach ($stats as $stat) {
            if ($stat->total_orders < $minOrders) continue;

            $cancelRate = ($stat->cancelled / $stat->total_orders) * 100;
            if ($cancelRate >= $cancelThreshold) {
                $this->createAuditLog([
                    'period_id'      => $period->id,
                    'rule_code'      => 'IPTAL_ORANI',
                    'severity'       => 'warning',
                    'title'          => "Yüksek İptal Oranı: %" . round($cancelRate) . " — {$stat->display_key}",
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

    protected function resolveCommissionReference(MpOrder $order): ?array
    {
        $settlementRate = (float) ($order->resolved_settlement_commission_rate ?? 0);
        if ($settlementRate > 0) {
            return [
                'rate' => round($settlementRate, 2),
                'source' => 'Ödeme Detay',
            ];
        }

        $operationalRate = (float) ($order->resolved_operational_commission_rate ?? 0);
        if ($operationalRate > 0) {
            return [
                'rate' => round($operationalRate, 2),
                'source' => 'Operasyon export',
            ];
        }

        return null;
    }

    protected function loadComparableOrders(int $periodId, ?array $statuses = null): Collection
    {
        return MpOrder::query()
            ->where('period_id', $periodId)
            ->when($statuses !== null, fn($query) => $query->whereIn('status', $statuses))
            ->with(['period', 'operationalOrder.items'])
            ->get();
    }

    protected function buildResolvedUnitPriceStats(Collection $orders, int $minOrders): Collection
    {
        $stats = [];

        foreach ($orders as $order) {
            if ((float) $order->gross_amount <= 0) {
                continue;
            }

            $key = $this->resolveComparableSkuKey($order);
            if ($key === null) {
                continue;
            }

            $qty = max(1, (int) $order->resolved_quantity);
            $stats[$key] ??= [
                'sum_price' => 0.0,
                'cnt' => 0,
                'product_name' => $order->resolved_product_name ?: $order->product_name ?: 'Ürün',
                'display_key' => $this->resolveDisplaySkuKey($order),
            ];
            $stats[$key]['sum_price'] += ((float) $order->gross_amount / $qty);
            $stats[$key]['cnt']++;
        }

        return collect($stats)
            ->filter(fn(array $stat) => $stat['cnt'] >= $minOrders)
            ->map(fn(array $stat) => (object) [
                'avg_price' => $stat['sum_price'] / $stat['cnt'],
                'cnt' => $stat['cnt'],
                'product_name' => $stat['product_name'],
                'display_key' => $stat['display_key'],
            ]);
    }

    protected function buildResolvedCommissionRateStats(Collection $orders, int $minOrders): Collection
    {
        $stats = [];

        foreach ($orders as $order) {
            $rate = (float) $order->commission_rate;
            if ($rate <= 0) {
                continue;
            }

            $key = $this->resolveComparableSkuKey($order);
            if ($key === null) {
                continue;
            }

            $stats[$key] ??= [
                'sum_rate' => 0.0,
                'cnt' => 0,
                'product_name' => $order->resolved_product_name ?: $order->product_name ?: 'Ürün',
                'display_key' => $this->resolveDisplaySkuKey($order),
            ];
            $stats[$key]['sum_rate'] += $rate;
            $stats[$key]['cnt']++;
        }

        return collect($stats)
            ->filter(fn(array $stat) => $stat['cnt'] >= $minOrders)
            ->map(fn(array $stat) => (object) [
                'avg_rate' => $stat['sum_rate'] / $stat['cnt'],
                'cnt' => $stat['cnt'],
                'product_name' => $stat['product_name'],
                'display_key' => $stat['display_key'],
            ]);
    }

    protected function buildResolvedReturnRateStats(Collection $orders): Collection
    {
        $stats = [];

        foreach ($orders as $order) {
            $key = $this->resolveComparableSkuKey($order);
            if ($key === null) {
                continue;
            }

            $qty = max(1, (int) $order->resolved_quantity);
            $stats[$key] ??= (object) [
                'product_name' => $order->resolved_product_name ?: $order->product_name ?: 'Ürün',
                'display_key' => $this->resolveDisplaySkuKey($order),
                'delivered' => 0,
                'returned' => 0,
                'total_orders' => 0,
            ];

            $stats[$key]->total_orders++;

            if ($order->status === 'Teslim Edildi') {
                $stats[$key]->delivered += $qty;
            } elseif ($order->status === 'İade Edildi') {
                $stats[$key]->returned += $qty;
            }
        }

        return collect($stats);
    }

    protected function buildResolvedCancellationRateStats(Collection $orders): Collection
    {
        $stats = [];

        foreach ($orders as $order) {
            $key = $this->resolveComparableSkuKey($order);
            if ($key === null) {
                continue;
            }

            $stats[$key] ??= (object) [
                'product_name' => $order->resolved_product_name ?: $order->product_name ?: 'Ürün',
                'display_key' => $this->resolveDisplaySkuKey($order),
                'total_orders' => 0,
                'cancelled' => 0,
            ];

            $stats[$key]->total_orders++;

            if ($order->status === 'İptal Edildi') {
                $stats[$key]->cancelled++;
            }
        }

        return collect($stats);
    }

    protected function resolveComparableSkuKey(MpOrder $order): ?string
    {
        if (filled($order->resolved_barcode)) {
            return 'barcode:' . trim((string) $order->resolved_barcode);
        }

        if (filled($order->resolved_stock_code)) {
            return 'stock:' . trim((string) $order->resolved_stock_code);
        }

        if (filled($order->resolved_product_name)) {
            return 'name:' . mb_strtolower(trim((string) $order->resolved_product_name));
        }

        return null;
    }

    protected function resolveDisplaySkuKey(MpOrder $order): string
    {
        if (filled($order->resolved_barcode)) {
            return (string) $order->resolved_barcode;
        }

        if (filled($order->resolved_stock_code)) {
            return (string) $order->resolved_stock_code;
        }

        if (filled($order->resolved_product_name)) {
            return (string) $order->resolved_product_name;
        }

        return 'Sipariş #' . $order->order_number;
    }

    protected function resolveOrderSettlements(MpPeriod $period, string $orderNumber, ?int $orderId = null): Collection
    {
        $baseQuery = MpSettlement::query()
            ->where('order_number', $orderNumber)
            ->orderByRaw('transaction_date is null, transaction_date asc')
            ->orderBy('id');

        $settlements = collect();

        if ($orderId) {
            $settlements = $settlements->merge(
                (clone $baseQuery)->where('order_id', $orderId)->get()
            );
        }

        $settlements = $settlements->merge(
            (clone $baseQuery)->where('period_id', $period->id)->get()
        );

        $userId = (int) ($period->user_id ?? 0);
        if ($userId > 0) {
            $settlements = $settlements->merge(
                (clone $baseQuery)->where('user_id', $userId)->get()
            );
        }

        return $settlements
            ->unique('id')
            ->sortBy([
                fn(MpSettlement $settlement) => $settlement->transaction_date?->getTimestamp() ?? PHP_INT_MAX,
                fn(MpSettlement $settlement) => $settlement->id,
            ])
            ->values();
    }

    protected function updateSettlementReconciliation(Collection $settlements, bool $isReconciled, ?string $note = null): void
    {
        $settlements->each(function (MpSettlement $settlement) use ($isReconciled, $note) {
            $payload = ['is_reconciled' => $isReconciled];
            if ($note !== null && array_key_exists('notes', $settlement->getAttributes())) {
                $payload['notes'] = $note;
            }

            $settlement->update($payload);
        });
    }

    protected function resolveOrderTransactions(MpPeriod $period, string $orderNumber): Collection
    {
        $query = MpTransaction::query()
            ->where(function ($transactionQuery) use ($orderNumber) {
                $transactionQuery
                    ->where('order_number', $orderNumber)
                    ->orWhere('description', 'like', '%' . $orderNumber . '%');
            })
            ->orderByRaw('transaction_date is null, transaction_date asc')
            ->orderBy('id');

        $userPeriodIds = $this->resolveUserPeriodIds($period);
        if (!empty($userPeriodIds)) {
            return $query->whereIn('period_id', $userPeriodIds)->get();
        }

        return $query->where('period_id', $period->id)->get();
    }

    protected function resolveUserPeriodIds(MpPeriod $period): array
    {
        $cacheKey = (int) $period->id;
        if (array_key_exists($cacheKey, $this->userPeriodIdsCache)) {
            return $this->userPeriodIdsCache[$cacheKey];
        }

        $userId = (int) ($period->user_id ?? 0);
        if ($userId <= 0) {
            return $this->userPeriodIdsCache[$cacheKey] = [$period->id];
        }

        $periodIds = MpPeriod::where('user_id', $userId)->pluck('id')->all();

        return $this->userPeriodIdsCache[$cacheKey] = (!empty($periodIds) ? $periodIds : [$period->id]);
    }

    protected function resolvePreviousComparablePeriod(MpPeriod $period): ?MpPeriod
    {
        $prevMonth = $period->month - 1;
        $prevYear = $period->year;

        if ($prevMonth < 1) {
            $prevMonth = 12;
            $prevYear--;
        }

        return MpPeriod::query()
            ->where('year', $prevYear)
            ->where('month', $prevMonth)
            ->where('marketplace', $period->marketplace)
            ->when($period->user_id, fn($query) => $query->where('user_id', $period->user_id))
            ->when(
                filled($period->seller_id),
                fn($query) => $query->where('seller_id', $period->seller_id),
                fn($query) => $query->where(function ($sellerQuery) {
                    $sellerQuery->whereNull('seller_id')->orWhere('seller_id', '');
                })
            )
            ->first();
    }

    protected function resolveOrderSkuKey(MpOrder $order): string
    {
        if (filled($order->resolved_barcode)) {
            return $order->resolved_barcode;
        }

        if (filled($order->resolved_stock_code)) {
            return $order->resolved_stock_code;
        }

        if (filled($order->resolved_product_name)) {
            return mb_strtolower(trim($order->resolved_product_name));
        }

        return 'order:' . $order->order_number;
    }
}
