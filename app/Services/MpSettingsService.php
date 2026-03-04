<?php

namespace App\Services;

use App\Models\MpAccountingSetting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * Pazaryeri Muhasebe — Merkezi Ayar Servisi
 *
 * Tüm finansal kurallar, toleranslar, mutabakat eşikleri
 * bu servis üzerinden okunur/yazılır.
 * Dot notation destekli, cache-friendly, varsayılan değerli.
 */
class MpSettingsService
{
    protected ?int $userId;
    protected ?array $settings = null;

    public function __construct(?int $userId = null)
    {
        $this->userId = $userId ?? Auth::id();
    }

    // ─── Okuma ──────────────────────────────────────────────────

    /**
     * Dot notation ile ayar oku. Yoksa default döner.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $settings = $this->all();
        return Arr::get($settings, $key, $default ?? Arr::get($this->getDefaults(), $key));
    }

    /**
     * Float olarak oku
     */
    public function getFloat(string $key, float $default = 0): float
    {
        return (float) $this->get($key, $default);
    }

    /**
     * Int olarak oku
     */
    public function getInt(string $key, int $default = 0): int
    {
        return (int) $this->get($key, $default);
    }

    /**
     * Tüm ayarları döndür (merge with defaults)
     */
    public function all(): array
    {
        if ($this->settings !== null) {
            return $this->settings;
        }

        $cacheKey = "mp_settings_{$this->userId}";

        $this->settings = Cache::remember($cacheKey, 3600, function () {
            $record = MpAccountingSetting::where('user_id', $this->userId)->first();
            $saved = $record?->settings ?? [];
            return $this->mergeWithDefaults($saved);
        });

        return $this->settings;
    }

    // ─── Yazma ──────────────────────────────────────────────────

    /**
     * Tek bir anahtarı güncelle
     */
    public function set(string $key, mixed $value): void
    {
        $settings = $this->all();
        Arr::set($settings, $key, $value);
        $this->save($settings);
    }

    /**
     * Birden çok anahtarı güncelle
     */
    public function setMany(array $data): void
    {
        $settings = $this->all();
        foreach ($data as $key => $value) {
            Arr::set($settings, $key, $value);
        }
        $this->save($settings);
    }

    /**
     * Tüm ayarları kaydet
     */
    public function save(array $settings): void
    {
        MpAccountingSetting::updateOrCreate(
            ['user_id' => $this->userId],
            ['settings' => $settings]
        );

        // Cache'i temizle
        Cache::forget("mp_settings_{$this->userId}");
        $this->settings = $settings;
    }

    /**
     * Fabrika ayarlarına sıfırla
     */
    public function reset(): void
    {
        $this->save($this->getDefaults());
    }

    // ─── Kısa Yollar (Convenience Helpers) ──────────────────────

    public function getStopajRate(): float
    {
        return $this->getFloat('tax.stopaj_rate', 0.01);
    }

    public function getDefaultProductVatRate(): float
    {
        return $this->getFloat('tax.default_product_vat_rate', 0.10);
    }

    public function getExpenseVatRate(): float
    {
        return $this->getFloat('tax.expense_vat_rate', 0.20);
    }

    /**
     * Net KDV Yükü hesaplaması açık mı?
     * Kapalıysa (varsayılan) KDV kâr hesaplarında dikkate alınmaz.
     */
    public function isKdvEnabled(): bool
    {
        return (bool) $this->get('tax.kdv_hesaplama_aktif', false);
    }

    public function getBaremLimit(): float
    {
        return $this->getFloat('cargo.barem_limit', 300);
    }

    public function getDefaultCargoCompany(): string
    {
        return (string) $this->get('general.default_cargo_company', 'TEX');
    }

    public function getCargoCompanies(): array
    {
        return (array) $this->get('cargo.cargo_companies', ['TEX', 'PTT', 'Aras', 'Sürat', 'Yurtiçi']);
    }

    public function getHeavyCargoPenalties(): array
    {
        return (array) $this->get('cargo.heavy_cargo_penalties', [
            'Aras'    => 4250,
            'Sürat'   => 4500,
            'Yurtiçi' => 5350,
        ]);
    }

    public function getBaremPrice(string $cargoCompany, float $amount): float
    {
        $limit = $this->getBaremLimit();

        if ($amount < $limit) {
            // Şimdilik barem fiyatları eski yapıya göre kaydedildi (Örn: barem_0_150)
            // İleride burası dinamik range yapısına çevrilebilir, ancak DB'deki karşılığını arayalım.
            $val = \App\Models\MpFinancialRule::getRule('barem_0_150', $cargoCompany) 
                ?? \App\Models\MpFinancialRule::getRule('barem_0_200', $cargoCompany)
                ?? \App\Models\MpFinancialRule::getRule('barem_0_' . (int)$limit, $cargoCompany);

            return $val !== null ? (float) $val : 0.0;
        }

        return 0.0;
    }

    public function getDesiPrice(string $cargoCompany, float $desi): float
    {
        $desiInt = (int) ceil($desi);

        $ranges = ['desi_0_2', 'desi_3', 'desi_4', 'desi_5', 'desi_10', 'desi_15', 'desi_20', 'desi_25', 'desi_30'];
        $bestRange = 'desi_0_2';

        if ($desiInt <= 2) $bestRange = 'desi_0_2';
        elseif ($desiInt <= 3) $bestRange = 'desi_3';
        elseif ($desiInt <= 4) $bestRange = 'desi_4';
        elseif ($desiInt <= 5) $bestRange = 'desi_5';
        elseif ($desiInt <= 10) $bestRange = 'desi_10';
        elseif ($desiInt <= 15) $bestRange = 'desi_15';
        elseif ($desiInt <= 20) $bestRange = 'desi_20';
        elseif ($desiInt <= 25) $bestRange = 'desi_25';
        else $bestRange = 'desi_30';

        $val = \App\Models\MpFinancialRule::getRule($bestRange, $cargoCompany);
        return $val !== null ? (float) $val : 0.0;
    }

    public function getDelayedPaymentDays(): int
    {
        return $this->getInt('payment.delayed_payment_days', 35);
    }

    public function getMarketplace(): string
    {
        return (string) $this->get('general.marketplace', 'Trendyol');
    }

    // ─── Audit Tolerans Kısa Yolları ────────────────────────────

    public function getAuditTolerance(string $key): float
    {
        return $this->getFloat("audit_tolerances.{$key}");
    }

    // ─── Reconciliation Kısa Yolları ────────────────────────────

    public function getCommissionMatchTolerance(): float
    {
        return $this->getFloat('reconciliation.commission_match_tolerance', 15.00);
    }

    public function getCargoMatchTolerance(): float
    {
        return $this->getFloat('reconciliation.cargo_match_tolerance', 20.00);
    }

    public function getInvoiceVatDivisor(): float
    {
        return $this->getFloat('reconciliation.invoice_vat_divisor', 1.20);
    }

    // ─── Varsayılanlar ──────────────────────────────────────────

    public function getDefaults(): array
    {
        return [
            'tax' => [
                'stopaj_rate'              => 0.01,
                'default_product_vat_rate' => 0.10,
                'expense_vat_rate'         => 0.20,
                'kdv_hesaplama_aktif'      => false,
            ],
            'cargo' => [
                'barem_limit'           => 300,
                'cargo_companies'       => ['TEX', 'PTT', 'Aras', 'Sürat', 'Yurtiçi'],
                'heavy_cargo_penalties' => [
                    'Aras'    => 4250,
                    'Sürat'   => 4500,
                    'Yurtiçi' => 5350,
                ],
                'barem_prices' => [
                    'TEX'     => 35.00,
                    'PTT'     => 32.00,
                    'Aras'    => 40.00,
                    'Sürat'   => 38.00,
                    'Yurtiçi' => 45.00,
                ],
                'desi_prices' => [
                    'TEX' => [1 => 45, 2 => 50, 3 => 55, 4 => 60, 5 => 65],
                    'PTT' => [1 => 40, 2 => 45, 3 => 50, 4 => 55, 5 => 60],
                    'Aras'=> [1 => 50, 2 => 55, 3 => 60, 4 => 65, 5 => 70],
                ],
            ],
            'audit_tolerances' => [
                'stopaj_tolerance'                     => 0.05,
                'commission_mismatch_tolerance'        => 1.50,
                'barem_excess_tolerance'               => 1.00,
                'commission_refund_tolerance'           => 0.50,
                'hakedis_tolerance'                    => 1.00,
                'heavy_cargo_tolerance'                => 50.00,
                'commission_refund_tracking_tolerance'  => 1.00,
                'missing_payment_tolerance'            => 0.50,
                'sunk_cost_critical_threshold'         => 100.00,
                'hakedis_critical_threshold'           => 20.00,
                'operational_penalty_critical_threshold' => 500.00,
                'multiple_cart_factor'                  => 1.50,
                'multiple_cart_desi_tolerance'          => 10.00,
            ],
            'reconciliation' => [
                'commission_match_tolerance' => 15.00,
                'cargo_match_tolerance'      => 20.00,
                'invoice_vat_divisor'        => 1.20,
            ],
            'payment' => [
                'delayed_payment_days' => 35,
            ],
            'general' => [
                'marketplace'           => 'Trendyol',
                'currency'              => 'TRY',
                'default_cargo_company' => 'TEX',
            ],
        ];
    }

    // ─── Internal ───────────────────────────────────────────────

    /**
     * Kaydedilmiş ayarları varsayılanlarla merge et
     * (eksik anahtarlar varsayılan değerlerle doldurulur)
     */
    protected function mergeWithDefaults(array $saved): array
    {
        $defaults = $this->getDefaults();
        return $this->deepMerge($defaults, $saved);
    }

    /**
     * Derin merge — saved değerler defaults'u override eder
     */
    protected function deepMerge(array $defaults, array $saved): array
    {
        $result = $defaults;

        foreach ($saved as $key => $value) {
            if (is_array($value) && isset($result[$key]) && is_array($result[$key])) {
                // Alt diziler sadece associative array ise deep merge yap
                // Sequential array'leri (cargo_companies gibi) direkt override et
                if ($this->isAssociative($value) && $this->isAssociative($result[$key])) {
                    $result[$key] = $this->deepMerge($result[$key], $value);
                } else {
                    $result[$key] = $value;
                }
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Bir array'in associative (anahtar-değer) mı yoksa sequential (liste) mi olduğunu kontrol et
     */
    protected function isAssociative(array $arr): bool
    {
        if (empty($arr)) return false;
        return array_keys($arr) !== range(0, count($arr) - 1);
    }
}
