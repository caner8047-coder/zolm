<?php

namespace App\Services;

use App\Services\Marketplace\MarketplaceProviderRegistry;
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
     * Bool olarak oku
     */
    public function getBool(string $key, bool $default = false): bool
    {
        return (bool) $this->get($key, $default);
    }

    /**
     * Array olarak oku
     */
    public function getArray(string $key, array $default = []): array
    {
        $value = $this->get($key, $default);

        return is_array($value) ? $value : $default;
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

    public function isEstimatedWithholdingEnabled(): bool
    {
        return (bool) $this->get('tax.estimated_withholding_enabled', false);
    }

    /**
     * Firma kendi kargo anlaşması ile mi çalışıyor?
     * Açıksa, MpProduct.cargo_cost sipariş kâr hesabına dahil edilir.
     */
    public function usesOwnCargo(): bool
    {
        return (bool) $this->get('cargo.uses_own_cargo', false);
    }

    public function getBaremLimit(): float
    {
        return $this->getFloat('cargo.barem_limit', 300);
    }

    public function getMaxDesiLimit(): float
    {
        return $this->getFloat('cargo.max_desi_limit', 500);
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

    public function getDesiRanges(): array
    {
        $defaults = $this->getDefaults()['cargo']['desi_ranges'];
        $saved = $this->get('cargo.desi_ranges', null);

        if (! is_array($saved) || $saved === []) {
            return $defaults;
        }

        return $this->normalizeDesiRanges($saved, $defaults);
    }

    public function getBaremRanges(): array
    {
        $defaults = $this->getDefaults()['cargo']['barem_ranges'];
        $saved = $this->get('cargo.barem_ranges', null);

        if (! is_array($saved) || $saved === []) {
            return $defaults;
        }

        return $this->normalizeBaremRanges($saved, $defaults);
    }

    public function normalizeDesiRanges(array $input, array $defaults): array
    {
        $normalized = [];
        foreach ($input as $range) {
            if (! is_array($range) || empty($range['key'])) {
                continue;
            }

            $key = (string) $range['key'];
            $min = isset($range['min']) ? (int) $range['min'] : 0;
            $max = isset($range['max']) ? (int) $range['max'] : $min;
            $label = isset($range['label']) ? (string) $range['label'] : $key;

            if ($min > $max) {
                [$min, $max] = [$max, $min];
            }

            $normalized[] = ['key' => $key, 'min' => $min, 'max' => $max, 'label' => $label];
        }

        return $normalized !== [] ? $normalized : $defaults;
    }

    public function normalizeBaremRanges(array $input, array $defaults): array
    {
        $normalized = [];
        foreach ($input as $range) {
            if (! is_array($range) || empty($range['key'])) {
                continue;
            }

            $key = (string) $range['key'];
            $min = isset($range['min']) ? (float) $range['min'] : 0;
            $max = isset($range['max']) ? (float) $range['max'] : $min;
            $label = isset($range['label']) ? (string) $range['label'] : $key;

            if ($min > $max) {
                [$min, $max] = [$max, $min];
            }

            $normalized[] = ['key' => $key, 'min' => $min, 'max' => $max, 'label' => $label];
        }

        return $normalized !== [] ? $normalized : $defaults;
    }

    public function getBaremPrice(string $cargoCompany, float $amount): float
    {
        $ranges = $this->getBaremRanges();
        $effectiveLimit = $this->getBaremLimit();

        if ($ranges !== []) {
            $maxRangeMax = max(array_column($ranges, 'max'));
            $effectiveLimit = max($effectiveLimit, $maxRangeMax);
        }

        if ($amount >= $effectiveLimit) {
            return 0.0;
        }

        foreach ($ranges as $range) {
            if ($amount >= $range['min'] && $amount < $range['max']) {
                $val = \App\Models\MpFinancialRule::getRule($range['key'], $cargoCompany);

                return $val !== null ? (float) $val : 0.0;
            }
        }

        return 0.0;
    }

    public function getDesiPrice(string $cargoCompany, float $desi): float
    {
        $desiInt = (int) ceil($desi);

        foreach ($this->getDesiRanges() as $range) {
            if ($desiInt >= $range['min'] && $desiInt <= $range['max']) {
                $val = \App\Models\MpFinancialRule::getRule($range['key'], $cargoCompany);

                return $val !== null ? (float) $val : 0.0;
            }
        }

        $desiRanges = $this->getDesiRanges();
        $lastRange = end($desiRanges);
        $val = \App\Models\MpFinancialRule::getRule($lastRange['key'], $cargoCompany);

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

    public function getDisabledAuditRules(): array
    {
        return $this->getArray('audit_rules.disabled', []);
    }

    public function shouldLogInfoRules(): bool
    {
        return $this->getBool('audit_behavior.log_info_rules', false);
    }

    public function helpTipsEnabled(): bool
    {
        return $this->getBool('ui.help_tips_enabled', true);
    }

    public function getOrdersPerPage(): int
    {
        return $this->normalizePerPage($this->getInt('ui.orders_per_page', 20), 20);
    }

    public function getTrendyolTimestampOffsetSeconds(): int
    {
        return $this->normalizeTrendyolTimestampOffset($this->getInt('orders.trendyol_timestamp_offset_seconds', 10800));
    }

    public function normalizeTrendyolTimestampOffset(int $value): int
    {
        return ($value >= -43200 && $value <= 50400) ? $value : 10800;
    }

    public function getProductsPerPage(): int
    {
        return $this->normalizePerPage($this->getInt('ui.products_per_page', 25), 25);
    }

    public function normalizePerPage(int $value, int $default): int
    {
        $allowed = [10, 20, 25, 50, 100];

        return in_array($value, $allowed, true) ? $value : $default;
    }

    public function getOrdersDefaultDateRangeDays(): int
    {
        return $this->normalizeDateRangeDays($this->getInt('ui.orders_default_date_range_days', 0), 0);
    }

    public function getFinanceDefaultDateRangeDays(): int
    {
        return $this->normalizeDateRangeDays($this->getInt('ui.finance_default_date_range_days', 30), 30);
    }

    public function normalizeDateRangeDays(int $value, int $default = 30): int
    {
        $allowed = [0, 7, 30, 60, 90, 180, 365];

        return in_array($value, $allowed, true) ? $value : $default;
    }

    public function getDefaultCurrency(): string
    {
        return $this->normalizeCurrency((string) $this->get('general.currency', 'TRY'));
    }

    public function normalizeCurrency(string $value): string
    {
        $allowed = ['TRY', 'EUR', 'USD', 'GBP'];

        $upper = strtoupper(trim($value));

        return in_array($upper, $allowed, true) ? $upper : 'TRY';
    }

    public function getAutoRecommendThreshold(): int
    {
        return $this->normalizeAutoRecommendThreshold($this->getInt('matching.auto_recommend_threshold', 100));
    }

    public function normalizeAutoRecommendThreshold(int $value): int
    {
        return ($value >= 1 && $value <= 500) ? $value : 100;
    }

    public function getMatchingCandidateSearchLimit(): int
    {
        return $this->normalizeCandidateLimit($this->getInt('matching.candidate_search_limit', 12), 1, 100, 12);
    }

    public function getAutoRunMatchingOnSync(): bool
    {
        $raw = $this->get('matching.auto_run_on_sync', true);

        return filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
    }

    public function getHealthScoreWeights(): array
    {
        $defaults = $this->getDefaults()['profit']['health_score_weights'];
        $saved = $this->get('profit.health_score_weights', null);

        if (! is_array($saved) || $saved === []) {
            return $defaults;
        }

        return $this->normalizeHealthScoreWeights($saved, $defaults);
    }

    public function normalizeHealthScoreWeights(array $input, array $defaults): array
    {
        $normalized = [];
        $allowed = ['finance_coverage', 'snapshot_coverage', 'cost_readiness', 'payment_pressure'];

        foreach ($allowed as $key) {
            $val = $input[$key] ?? $defaults[$key] ?? 0;
            $normalized[$key] = is_numeric($val) ? max(0.0, (float) $val) : ($defaults[$key] ?? 0);
        }

        $total = array_sum($normalized);

        if ($total > 0 && abs($total - 1.0) > 0.001) {
            foreach ($normalized as $key => $val) {
                $normalized[$key] = round($val / $total, 4);
            }
        }

        return $normalized;
    }

    public function getMatchingCandidateResultLimit(): int
    {
        $searchLimit = $this->getMatchingCandidateSearchLimit();
        $resultLimit = $this->normalizeCandidateLimit($this->getInt('matching.candidate_result_limit', 8), 1, 50, 8);

        return $resultLimit > $searchLimit ? $searchLimit : $resultLimit;
    }

    protected function normalizeCandidateLimit(int $value, int $min, int $max, int $default): int
    {
        return ($value >= $min && $value <= $max) ? $value : $default;
    }

    public function getMatchingWeights(): array
    {
        $defaults = $this->getDefaults()['matching']['weights'];

        $saved = (array) $this->get('matching.weights', []);

        $result = [];
        foreach ($defaults as $key => $default) {
            $raw = $saved[$key] ?? $default;
            $result[$key] = $this->normalizeMatchingWeight((int) $raw, $default);
        }

        return $result;
    }

    public function normalizeMatchingWeight(int $value, int $default): int
    {
        return ($value >= 0 && $value <= 500) ? $value : $default;
    }

    public function getMatchingStopWords(): array
    {
        $defaults = $this->getDefaults()['matching']['stop_words'];
        $raw = $this->get('matching.stop_words', null);

        if (! is_array($raw) || $raw === []) {
            return $defaults;
        }

        return $this->normalizeStopWords($raw, $defaults);
    }

    public function normalizeStopWords(array $input, array $defaults): array
    {
        $normalized = [];
        foreach ($input as $word) {
            if (! is_string($word)) {
                continue;
            }

            $clean = strtolower(trim($word));

            if ($clean === '' || mb_strlen($clean) < 2 || mb_strlen($clean) > 40) {
                continue;
            }

            if (! in_array($clean, $normalized, true)) {
                $normalized[] = $clean;
            }

            if (count($normalized) >= 100) {
                break;
            }
        }

        return $normalized === [] ? $defaults : $normalized;
    }

    public function getProductProfitDefaultMarketplace(): string
    {
        $value = strtolower(trim((string) $this->get('marketplace_products.profit.default_marketplace', 'average')));

        if (in_array($value, ['average', 'worst'], true)) {
            return $value;
        }

        $normalized = MarketplaceProviderRegistry::normalize($value);

        return array_key_exists($normalized, MarketplaceProviderRegistry::options())
            ? $normalized
            : 'average';
    }

    public function getProductProfitWooCommerceCommissionRate(): float
    {
        return round(min(100, max(0, $this->getFloat('marketplace_products.profit.woocommerce_commission_rate', 0))), 2);
    }

    public function getProductProfitKoctasCommissionRate(): float
    {
        $defaultRate = (float) config('marketplace.koctas.commission_rate', 15);

        return round(min(100, max(0, $this->getFloat('marketplace_products.profit.koctas_commission_rate', $defaultRate))), 2);
    }

    public function recipeCostSyncEnabled(): bool
    {
        return $this->getBool('marketplace_products.recipe_cost_sync_enabled', false);
    }

    public function getLowStockThreshold(): int
    {
        return max(0, $this->getInt('marketplace_products.low_stock_threshold', 0));
    }

    public function isAiAnswerEnabled(): bool
    {
        return (bool) $this->get('questions.ai_answer_enabled', true);
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
                'estimated_withholding_enabled' => false,
            ],
            'cargo' => [
                'barem_limit'           => 300,
                'max_desi_limit'        => 500,
                'cargo_companies'       => ['TEX', 'PTT', 'Aras', 'Sürat', 'Yurtiçi'],
                'uses_own_cargo'        => false,
                'heavy_cargo_penalties' => [
                    'Aras'    => 4250,
                    'Sürat'   => 4500,
                    'Yurtiçi' => 5350,
                ],
                'desi_ranges' => [
                    ['key' => 'desi_0_2', 'min' => 0, 'max' => 2, 'label' => '0-2 Desi'],
                    ['key' => 'desi_3', 'min' => 3, 'max' => 3, 'label' => '3 Desi'],
                    ['key' => 'desi_4', 'min' => 4, 'max' => 4, 'label' => '4 Desi'],
                    ['key' => 'desi_5', 'min' => 5, 'max' => 5, 'label' => '5 Desi'],
                    ['key' => 'desi_10', 'min' => 6, 'max' => 10, 'label' => '6-10 Desi'],
                    ['key' => 'desi_15', 'min' => 11, 'max' => 15, 'label' => '11-15 Desi'],
                    ['key' => 'desi_20', 'min' => 16, 'max' => 20, 'label' => '16-20 Desi'],
                    ['key' => 'desi_25', 'min' => 21, 'max' => 25, 'label' => '21-25 Desi'],
                    ['key' => 'desi_30', 'min' => 26, 'max' => 500, 'label' => '26+ Desi'],
                ],
                'barem_ranges' => [
                    ['key' => 'barem_0_150', 'min' => 0, 'max' => 150, 'label' => '0-150 TL'],
                    ['key' => 'barem_150_300', 'min' => 150, 'max' => 300, 'label' => '150-300 TL'],
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
                'missing_payment_critical_threshold'    => 10.00,
                'price_drop_percentage'                 => 15.00,
                'price_drop_min_orders'                 => 3,
                'commission_rate_change_threshold'      => 1.00,
                'commission_rate_change_min_orders'     => 3,
                'service_fee_increase_threshold'        => 0.50,
                'service_fee_increase_min_orders'       => 20,
                'high_return_rate_threshold'            => 15.00,
                'high_return_rate_min_quantity'         => 5,
                'high_cancellation_rate_threshold'      => 10.00,
                'high_cancellation_rate_min_orders'     => 5,
                'cargo_over_cost_ratio'                 => 0.50,
                'extreme_margin_positive_threshold'     => 100.00,
                'extreme_margin_negative_threshold'     => -100.00,
                'negative_hakedis_threshold'            => 0.00,
                'campaign_loss_min_total_loss'          => 0.00,
                'campaign_loss_min_order_count'         => 1,
            ],
            'audit_rules' => [
                'disabled' => [],
            ],
            'audit_behavior' => [
                'log_info_rules'                         => false,
                'transaction_check_commission_enabled'   => true,
                'transaction_check_cargo_enabled'        => true,
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
            'orders' => [
                'trendyol_timestamp_offset_seconds' => 10800,
            ],
            'ui' => [
                'visible_columns' => ['siparis', 'urun', 'durum', 'brut', 'hakedis', 'komisyon', 'kargo', 'detay'],
                'help_tips_enabled' => true,
                'orders_per_page' => 20,
                'products_per_page' => 25,
                'orders_default_date_range_days' => 0,
                'finance_default_date_range_days' => 30,
            ],
            'marketplace_products' => [
                'profit' => [
                    'default_marketplace' => 'average',
                    'woocommerce_commission_rate' => 0.00,
                    'koctas_commission_rate' => (float) config('marketplace.koctas.commission_rate', 15),
                ],
                'recipe_cost_sync_enabled' => false,
                'low_stock_threshold' => 0,
            ],
            'print' => [
                'label' => [
                    'template' => 'courier',
                    'paper' => 'thermal_100x150',
                    'barcode_type' => 'code128',
                    'barcode_height' => 56,
                    'show_sender' => true,
                    'show_customer_phone' => true,
                    'show_items' => true,
                    'show_marketplace' => true,
                    'show_tracking_number' => true,
                    'show_barcode_text' => true,
                    'show_item_summary' => true,
                    'footer_note' => '',
                ],
                'dispatch' => [
                    'template' => 'classic',
                    'paper' => 'a4',
                    'barcode_type' => 'code128',
                    'barcode_height' => 44,
                    'show_sender' => true,
                    'show_customer_phone' => true,
                    'show_billing_info' => true,
                    'show_items' => true,
                    'show_barcode' => true,
                    'show_barcode_text' => true,
                    'show_marketplace' => true,
                    'show_signature_area' => true,
                    'footer_note' => '',
                ],
            ],
            'company' => [
                'name'       => '',
                'tax_number' => '',
                'tax_office' => '',
                'phone'      => '',
                'email'      => '',
                'address'    => '',
                'iban'       => '',
                'bank'       => '',
                'branch'     => '',
                'manager'    => '',
                'mersis'     => '',
            ],
            'matching' => [
                'auto_recommend_threshold' => 100,
                'candidate_search_limit' => 12,
                'candidate_result_limit' => 8,
                'auto_run_on_sync' => true,
                'weights' => [
                    'barcode_exact' => 120,
                    'stock_code_exact' => 100,
                    'model_exact' => 90,
                    'model_family' => 70,
                    'brand_exact' => 12,
                    'category_exact' => 8,
                    'title_token' => 6,
                    'title_max' => 30,
                ],
                'stop_words' => [
                    'adet', 'one', 'size', 'olan', 'icin', 'için', 'ile', 've', 'bir', 'iki',
                    'tak', 'takim', 'takimi', 'takımı', 'urun', 'ürün', 'seti',
                ],
            ],
            'questions' => [
                'ai_answer_enabled' => true,
            ],
            'profitability' => [
                'target_margin'          => 15.00,
                'min_margin'             => 5.00,
                'default_packaging_cost' => 0.00,
            ],
            'profit' => [
                'health_score_weights' => [
                    'finance_coverage'   => 0.30,
                    'snapshot_coverage'  => 0.25,
                    'cost_readiness'     => 0.25,
                    'payment_pressure'   => 0.20,
                ],
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
