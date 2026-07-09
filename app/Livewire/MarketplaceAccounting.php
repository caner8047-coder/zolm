<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Livewire\Attributes\Validate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use App\Models\MpPeriod;
use App\Models\MpOrder;
use App\Models\MpTransaction;
use App\Models\MpAuditLog;
use App\Jobs\ProcessMarketplaceImport;
use App\Services\AuditEngine;
use App\Services\ReportService;
use App\Services\UnitEconomicsService;
use App\Services\MarketplaceExportService;
use App\Services\OrderDetailsService;
use App\Services\MpSettingsService;
use App\Services\ProfitabilityMetric;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

use Illuminate\Support\Facades\DB;

class MarketplaceAccounting extends Component
{
    use WithFileUploads, WithPagination;

    // ─── State ──────────────────────────────────────────────────
    #[Url(as: 'tab', except: 'dashboard')]
    public string $activeTab = 'dashboard';
    public ?int $selectedPeriodId = null;
    #[Url(as: 'year', except: 0)]
    public int $selectedYear = 0;
    #[Url(as: 'month', except: -1)]
    public int $selectedMonth = -1;
    public int $perPage = 20;

    // Upload
    #[Validate(['bulkFiles.*' => 'file|mimes:xlsx,xls'])]
    public $bulkFiles = [];
    public $ordersFile;
    public $transactionsFile;
    public $stopajFile;
    public $invoicesFile;
    public $settlementsFile;

    // Search (5N1K)
    public string $searchQuery = '';
    public ?array $searchResult = null;

    // Audit
    public ?array $lastAuditResult = null;

    // UI State
    public bool $showOrderModal = false;
    public ?int $selectedOrderId = null;
    public array $selectedOrderDetails = []; // Servisten dönen zenginleştirilmiş veri
    public string $importStatus = '';
    public array $importErrors = [];

    // Job/Queue — arka plan işleme durumu
    public bool $importProcessing = false;
    public string $importProcessingType = '';

    // ─── Settings (Dinamik Kurallar — Tüm Bölümler) ─────────────

    // Bölüm 1: Vergi & KDV
    public float $settingsStopajRate = 0.01;
    public float $settingsDefaultProductVatRate = 0.10;
    public float $settingsExpenseVatRate = 0.20;
    public bool $settingsKdvHesaplamaAktif = false;
    public bool $settingsEstimatedWithholdingEnabled = false;

    // Bölüm 2: Kargo & Barem
    public float $settingsBaremLimit = 300;
    public string $settingsDefaultCargoCompany = 'TEX';
    public bool $settingsUsesOwnCargo = false;
    public array $settingsCargoCompanies = [];
    public array $settingsHeavyCargoPenalties = [];
    public string $newCargoCompany = '';

    public string $newDesiRangeKey = '';
    public int $newDesiRangeMin = 0;
    public int $newDesiRangeMax = 0;
    public string $newDesiRangeLabel = '';

    public string $newBaremRangeKey = '';
    public float $newBaremRangeMin = 0;
    public float $newBaremRangeMax = 0;
    public string $newBaremRangeLabel = '';
    public string $newPenaltyCompany = '';
    public float $newPenaltyAmount = 0;

    // Bölüm 3: Desi Fiyatları (firma => [desi_key => fiyat])
    public array $settingsDesiPrices = [];

    // Bölüm 4: Barem Fiyatları (firma => [range_key => fiyat])
    public array $settingsBaremPrices = [];

    // Bölüm 5: Denetim Toleransları
    public float $settingsStopajTolerance = 0.05;
    public float $settingsCommissionMismatchTolerance = 1.50;
    public float $settingsBaremExcessTolerance = 1.00;
    public float $settingsCommissionRefundTolerance = 0.50;
    public float $settingsHakedisTolerance = 1.00;
    public float $settingsHeavyCargoTolerance = 50.00;
    public float $settingsCommissionRefundTrackingTolerance = 1.00;
    public float $settingsMissingPaymentTolerance = 0.50;
    public float $settingsSunkCostCriticalThreshold = 100.00;
    public float $settingsHakedisCriticalThreshold = 20.00;
    public float $settingsOperationalPenaltyCriticalThreshold = 500.00;
    public float $settingsMultipleCartFactor = 1.50;
    public float $settingsMultipleCartDesiTolerance = 10.00;
    public float $settingsMissingPaymentCriticalThreshold = 10.00;
    public float $settingsPriceDropPercentage = 15.00;
    public int $settingsPriceDropMinOrders = 3;
    public float $settingsCommissionRateChangeThreshold = 1.00;
    public int $settingsCommissionRateChangeMinOrders = 3;
    public float $settingsServiceFeeIncreaseThreshold = 0.50;
    public int $settingsServiceFeeIncreaseMinOrders = 20;
    public float $settingsHighReturnRateThreshold = 15.00;
    public int $settingsHighReturnRateMinQuantity = 5;
    public float $settingsHighCancellationRateThreshold = 10.00;
    public int $settingsHighCancellationRateMinOrders = 5;
    public float $settingsCargoOverCostRatio = 0.50;
    public float $settingsExtremeMarginPositiveThreshold = 100.00;
    public float $settingsExtremeMarginNegativeThreshold = -100.00;
    public float $settingsNegativeHakedisThreshold = 0.00;
    public float $settingsCampaignLossMinTotalLoss = 0.00;
    public int $settingsCampaignLossMinOrderCount = 1;
    public bool $settingsLogInfoRules = false;
    public bool $settingsTransactionCheckCommissionEnabled = true;
    public bool $settingsTransactionCheckCargoEnabled = true;

    // Bölüm 6: Mutabakat & Fatura
    public float $settingsCommissionMatchTolerance = 15.00;
    public float $settingsCargoMatchTolerance = 20.00;
    public float $settingsInvoiceVatDivisor = 1.20;

    // Ödeme
    public int $settingsDelayedPaymentDays = 35;

    // Genel
    public string $settingsMarketplace = 'Trendyol';
    public string $settingsCurrency = 'TRY';

    // Firma Profili
    public string $settingsCompanyName = '';
    public string $settingsCompanyTaxNumber = '';
    public string $settingsCompanyTaxOffice = '';
    public string $settingsCompanyPhone = '';
    public string $settingsCompanyEmail = '';
    public string $settingsCompanyAddress = '';
    public string $settingsCompanyIban = '';
    public string $settingsCompanyBank = '';
    public string $settingsCompanyBranch = '';
    public string $settingsCompanyManager = '';
    public string $settingsCompanyMersis = '';

    // Kârlılık Hedefleri
    public float $settingsTargetProfitMargin = 15.00;
    public float $settingsMinProfitMargin = 5.00;

    // Varsayılan Ambalaj Maliyeti
    public float $settingsDefaultPackagingCost = 0.00;

    // Ayar UI State
    public string $settingsActiveSection = '';
    public bool $settingsHelpTipsEnabled = true;

    // ERP Settings
    public string $erpProvider = '';
    public string $erpWebhookUrl = '';
    public string $erpApiKey = '';
    public string $erpApiSecret = '';
    public bool $erpAutoPush = false;
    public bool $erpIsActive = false;

    // Eski uyum (computed da kullanılıyor)
    public array $settingRules = [];

    // Toplu Eşleştirme (Bulk Reconcile) State
    public array $selectedOrders = [];
    public bool $selectAll = false;


    // Filter
    public string $auditFilter = 'all';
    public array $disabledAuditRules = [];
    public string $orderStatusFilter = 'all';
    public string $advancedOrderFilter = 'all'; // 5N1K Hızlı Filtreleri

    // Kârlılık tab
    public string $profitSortBy = 'total_net_profit';
    public string $profitSortDir = 'asc';
    public bool $showOnlyBleeding = false;

    // Kolon Özelleştirme
    public array $visibleColumns = ['siparis', 'urun', 'durum', 'brut', 'hakedis', 'komisyon', 'kargo', 'detay'];

    // Kolon Sıralama
    public string $orderSortBy = 'order_date';
    public string $orderSortDir = 'desc';

    public static array $sortableColumns = [
        'siparis'  => 'order_number',
        'durum'    => 'status',
        'brut'     => 'gross_amount',
        'hakedis'  => 'net_hakedis',
        'komisyon' => 'commission_amount',
        'kargo'    => 'cargo_amount',
        'cogs'     => 'cogs_at_time',
        'net_kar'  => 'calculated_net_profit',
    ];

    public static array $allColumnDefs = [
        'siparis'  => 'Sipariş',
        'urun'     => 'Ürün',
        'durum'    => 'Durum',
        'brut'     => 'Brüt',
        'hakedis'  => 'Ödeme',
        'komisyon' => 'Komisyon',
        'kargo'    => 'Kargo',
        'cogs'     => 'Maliyet',
        'net_kar'  => 'Net Kâr',
        'margin'   => 'Kârlılık',
        'detay'    => 'Detay',
    ];

    // ─── Listeners ──────────────────────────────────────────────
    protected $listeners = ['refreshComponent' => '$refresh'];

    // ─── Lifecycle ──────────────────────────────────────────────

    public function mount()
    {
        $hasPeriodFromUrl = $this->selectedYear > 0 && $this->selectedMonth >= 0;

        if ($hasPeriodFromUrl) {
            $this->selectedYear = (int) $this->selectedYear;
            $this->selectedMonth = (int) $this->selectedMonth;
            $this->selectPeriod();
        } else {
            $this->selectedYear = (int) date('Y');
            $this->selectedMonth = (int) date('n');

            // Öncelikle içinde sipariş verisi bulunan en güncel dönemi bul
            $lastPeriod = MpPeriod::where('user_id', Auth::id())
                ->whereHas('orders')
                ->orderBy('year', 'desc')
                ->orderBy('month', 'desc')
                ->first();

            // Eğer veri olan dönem yoksa, herhangi bir dönemi al
            if (!$lastPeriod) {
                $lastPeriod = MpPeriod::where('user_id', Auth::id())
                    ->orderBy('year', 'desc')
                    ->orderBy('month', 'desc')
                    ->first();
            }

            if ($lastPeriod) {
                $this->selectedPeriodId = $lastPeriod->id;
                $this->selectedYear = $lastPeriod->year;
                $this->selectedMonth = $lastPeriod->month;
            }
        }

        // ERP Ayarlarını Yükle
        $erpSetting = \App\Models\MpErpSetting::where('user_id', Auth::id())->first();
        if ($erpSetting) {
            $this->erpProvider = $erpSetting->provider_name ?? '';
            $this->erpWebhookUrl = $erpSetting->webhook_url ?? '';
            $this->erpApiKey = $erpSetting->api_key ?? '';
            $this->erpApiSecret = $erpSetting->api_secret ?? '';
            $this->erpAutoPush = $erpSetting->auto_push_on_reconcile;
            $this->erpIsActive = $erpSetting->is_active;
        }

        $this->loadSettings();

        $requestedTab = request()->query('tab');
        if (is_string($requestedTab) && array_key_exists($requestedTab, $this->tabs)) {
            $this->activeTab = $requestedTab;
        }

        if (!array_key_exists($this->activeTab, $this->tabs)) {
            $this->activeTab = 'dashboard';
        }
    }

    public function getTabsProperty(): array
    {
        return [
            'dashboard' => [
                'label' => 'Dashboard',
            ],
            'upload' => [
                'label' => 'Veri Yükleme',
            ],
            'search' => [
                'label' => 'Sipariş Ara',
            ],
            'audit' => [
                'label' => 'Denetim',
            ],
            'profit' => [
                'label' => 'Kârlılık',
            ],
            'orders' => [
                'label' => 'Siparişler',
            ],
            'settings' => [
                'label' => 'Muhasebe Ayarları',
            ],
        ];
    }

    public function setTab(string $tab): void
    {
        if (!array_key_exists($tab, $this->tabs)) {
            return;
        }

        $this->activeTab = $tab;
    }

    public function loadSettings()
    {
        $svc = new MpSettingsService();
        $all = $svc->all();

        // Bölüm 1: Vergi
        $this->settingsStopajRate             = (float) ($all['tax']['stopaj_rate'] ?? 0.01);
        $this->settingsDefaultProductVatRate   = (float) ($all['tax']['default_product_vat_rate'] ?? 0.10);
        $this->settingsExpenseVatRate           = (float) ($all['tax']['expense_vat_rate'] ?? 0.20);
        $this->settingsKdvHesaplamaAktif        = (bool) ($all['tax']['kdv_hesaplama_aktif'] ?? false);
        $this->settingsEstimatedWithholdingEnabled = (bool) ($all['tax']['estimated_withholding_enabled'] ?? false);

        // Kargo
        $this->settingsUsesOwnCargo              = (bool) ($all['cargo']['uses_own_cargo'] ?? false);

        // Bölüm 2: Kargo
        $this->settingsBaremLimit              = (float) ($all['cargo']['barem_limit'] ?? 300);
        $this->settingsCurrency                = $svc->getDefaultCurrency();
        $this->settingsDefaultCargoCompany     = (string) ($all['general']['default_cargo_company'] ?? 'TEX');
        $this->settingsCargoCompanies          = (array) ($all['cargo']['cargo_companies'] ?? ['TEX', 'PTT', 'Aras', 'Sürat', 'Yurtiçi']);
        $this->settingsHeavyCargoPenalties     = (array) ($all['cargo']['heavy_cargo_penalties'] ?? []);

        // Bölüm 5: Denetim Toleransları
        $t = $all['audit_tolerances'] ?? [];
        $this->settingsStopajTolerance                       = (float) ($t['stopaj_tolerance'] ?? 0.05);
        $this->settingsCommissionMismatchTolerance            = (float) ($t['commission_mismatch_tolerance'] ?? 1.50);
        $this->settingsBaremExcessTolerance                   = (float) ($t['barem_excess_tolerance'] ?? 1.00);
        $this->settingsCommissionRefundTolerance              = (float) ($t['commission_refund_tolerance'] ?? 0.50);
        $this->settingsHakedisTolerance                       = (float) ($t['hakedis_tolerance'] ?? 1.00);
        $this->settingsHeavyCargoTolerance                    = (float) ($t['heavy_cargo_tolerance'] ?? 50.00);
        $this->settingsCommissionRefundTrackingTolerance      = (float) ($t['commission_refund_tracking_tolerance'] ?? 1.00);
        $this->settingsMissingPaymentTolerance                = (float) ($t['missing_payment_tolerance'] ?? 0.50);
        $this->settingsSunkCostCriticalThreshold              = (float) ($t['sunk_cost_critical_threshold'] ?? 100.00);
        $this->settingsHakedisCriticalThreshold               = (float) ($t['hakedis_critical_threshold'] ?? 20.00);
        $this->settingsOperationalPenaltyCriticalThreshold    = (float) ($t['operational_penalty_critical_threshold'] ?? 500.00);
        $this->settingsMultipleCartFactor                     = (float) ($t['multiple_cart_factor'] ?? 1.50);
        $this->settingsMultipleCartDesiTolerance              = (float) ($t['multiple_cart_desi_tolerance'] ?? 10.00);
        $this->settingsMissingPaymentCriticalThreshold        = (float) ($t['missing_payment_critical_threshold'] ?? 10.00);
        $this->settingsPriceDropPercentage                    = (float) ($t['price_drop_percentage'] ?? 15.00);
        $this->settingsPriceDropMinOrders                     = (int) ($t['price_drop_min_orders'] ?? 3);
        $this->settingsCommissionRateChangeThreshold          = (float) ($t['commission_rate_change_threshold'] ?? 1.00);
        $this->settingsCommissionRateChangeMinOrders          = (int) ($t['commission_rate_change_min_orders'] ?? 3);
        $this->settingsServiceFeeIncreaseThreshold            = (float) ($t['service_fee_increase_threshold'] ?? 0.50);
        $this->settingsServiceFeeIncreaseMinOrders            = (int) ($t['service_fee_increase_min_orders'] ?? 20);
        $this->settingsHighReturnRateThreshold                = (float) ($t['high_return_rate_threshold'] ?? 15.00);
        $this->settingsHighReturnRateMinQuantity              = (int) ($t['high_return_rate_min_quantity'] ?? 5);
        $this->settingsHighCancellationRateThreshold          = (float) ($t['high_cancellation_rate_threshold'] ?? 10.00);
        $this->settingsHighCancellationRateMinOrders          = (int) ($t['high_cancellation_rate_min_orders'] ?? 5);
        $this->settingsCargoOverCostRatio                     = (float) ($t['cargo_over_cost_ratio'] ?? 0.50);
        $this->settingsExtremeMarginPositiveThreshold         = (float) ($t['extreme_margin_positive_threshold'] ?? 100.00);
        $this->settingsExtremeMarginNegativeThreshold         = (float) ($t['extreme_margin_negative_threshold'] ?? -100.00);
        $this->settingsNegativeHakedisThreshold               = (float) ($t['negative_hakedis_threshold'] ?? 0.00);
        $this->settingsCampaignLossMinTotalLoss               = (float) ($t['campaign_loss_min_total_loss'] ?? 0.00);
        $this->settingsCampaignLossMinOrderCount              = (int) ($t['campaign_loss_min_order_count'] ?? 1);

        $auditBehavior = $all['audit_behavior'] ?? [];
        $this->settingsLogInfoRules                = (bool) ($auditBehavior['log_info_rules'] ?? false);
        $this->settingsTransactionCheckCommissionEnabled = (bool) ($auditBehavior['transaction_check_commission_enabled'] ?? true);
        $this->settingsTransactionCheckCargoEnabled      = (bool) ($auditBehavior['transaction_check_cargo_enabled'] ?? true);

        $auditRules = $all['audit_rules'] ?? [];
        $this->disabledAuditRules = array_values(array_unique(array_filter(
            (array) ($auditRules['disabled'] ?? []),
            fn ($rule) => in_array($rule, AuditEngine::RULES, true)
        )));

        // Bölüm 6: Mutabakat
        $r = $all['reconciliation'] ?? [];
        $this->settingsCommissionMatchTolerance  = (float) ($r['commission_match_tolerance'] ?? 15.00);
        $this->settingsCargoMatchTolerance       = (float) ($r['cargo_match_tolerance'] ?? 20.00);
        $this->settingsInvoiceVatDivisor         = (float) ($r['invoice_vat_divisor'] ?? 1.20);

        // Ödeme
        $this->settingsDelayedPaymentDays = (int) ($all['payment']['delayed_payment_days'] ?? 35);

        // Genel
        $this->settingsMarketplace = (string) ($all['general']['marketplace'] ?? 'Trendyol');

        // Firma Profili
        $co = $all['company'] ?? [];
        $this->settingsCompanyName       = (string) ($co['name'] ?? '');
        $this->settingsCompanyTaxNumber  = (string) ($co['tax_number'] ?? '');
        $this->settingsCompanyTaxOffice  = (string) ($co['tax_office'] ?? '');
        $this->settingsCompanyPhone      = (string) ($co['phone'] ?? '');
        $this->settingsCompanyEmail      = (string) ($co['email'] ?? '');
        $this->settingsCompanyAddress    = (string) ($co['address'] ?? '');
        $this->settingsCompanyIban       = (string) ($co['iban'] ?? '');
        $this->settingsCompanyBank       = (string) ($co['bank'] ?? '');
        $this->settingsCompanyBranch     = (string) ($co['branch'] ?? '');
        $this->settingsCompanyManager    = (string) ($co['manager'] ?? '');
        $this->settingsCompanyMersis     = (string) ($co['mersis'] ?? '');

        // Kârlılık Hedefleri
        $prof = $all['profitability'] ?? [];
        $this->settingsTargetProfitMargin  = (float) ($prof['target_margin'] ?? 15.00);
        $this->settingsMinProfitMargin     = (float) ($prof['min_margin'] ?? 5.00);
        $this->settingsDefaultPackagingCost = (float) ($prof['default_packaging_cost'] ?? 0.00);

        // UI — Kolon Görünürlüğü
        $this->visibleColumns = (array) ($all['ui']['visible_columns'] ?? ['siparis', 'urun', 'durum', 'brut', 'hakedis', 'komisyon', 'kargo', 'detay']);
        $this->settingsHelpTipsEnabled = (bool) ($all['ui']['help_tips_enabled'] ?? true);

        // Desi fiyatlarını MpFinancialRule tablosundan yükle
        $this->loadDesiPrices();
        $this->loadBaremPrices();

        // Eski uyum (settingRules kullanılıyorsa)
        $this->settingRules = [
            'barem_limit'              => $this->settingsBaremLimit,
            'stopaj_rate'              => $this->settingsStopajRate,
            'expense_vat_rate'         => $this->settingsExpenseVatRate,
            'default_product_vat_rate' => $this->settingsDefaultProductVatRate,
        ];
    }

    protected function loadDesiPrices()
    {
        $desiRanges = app(\App\Services\MpSettingsService::class)->getDesiRanges();
        $this->settingsDesiPrices = [];

        foreach ($this->settingsCargoCompanies as $company) {
            $this->settingsDesiPrices[$company] = [];
            foreach ($desiRanges as $range) {
                $val = \App\Models\MpFinancialRule::getRule($range['key'], $company);
                $this->settingsDesiPrices[$company][$range['key']] = $val !== null ? (float) $val : null;
            }
        }
    }

    protected function loadBaremPrices()
    {
        $baremRanges = app(\App\Services\MpSettingsService::class)->getBaremRanges();
        $this->settingsBaremPrices = [];

        foreach ($this->settingsCargoCompanies as $company) {
            $this->settingsBaremPrices[$company] = [];
            foreach ($baremRanges as $range) {
                $val = \App\Models\MpFinancialRule::getRule($range['key'], $company);
                $this->settingsBaremPrices[$company][$range['key']] = $val !== null ? (float) $val : null;
            }
        }
    }

    // ─── Settings Save Methods ──────────────────────────────────

    public function saveSettings()
    {
        $this->validate([
            'settingsCurrency' => ['required', 'string', 'in:TRY,EUR,USD,GBP'],
        ]);

        $svc = new MpSettingsService();
        $svc->save([
            'tax' => [
                'stopaj_rate'                   => (float) $this->settingsStopajRate,
                'default_product_vat_rate'      => (float) $this->settingsDefaultProductVatRate,
                'expense_vat_rate'              => (float) $this->settingsExpenseVatRate,
                'kdv_hesaplama_aktif'           => (bool) $this->settingsKdvHesaplamaAktif,
                'estimated_withholding_enabled' => (bool) $this->settingsEstimatedWithholdingEnabled,
            ],
            'cargo' => [
                'barem_limit'           => (float) $this->settingsBaremLimit,
                'cargo_companies'       => $this->settingsCargoCompanies,
                'heavy_cargo_penalties' => $this->settingsHeavyCargoPenalties,
                'uses_own_cargo'        => (bool) $this->settingsUsesOwnCargo,
            ],
            'audit_tolerances' => [
                'stopaj_tolerance'                       => (float) $this->settingsStopajTolerance,
                'commission_mismatch_tolerance'           => (float) $this->settingsCommissionMismatchTolerance,
                'barem_excess_tolerance'                  => (float) $this->settingsBaremExcessTolerance,
                'commission_refund_tolerance'             => (float) $this->settingsCommissionRefundTolerance,
                'hakedis_tolerance'                       => (float) $this->settingsHakedisTolerance,
                'heavy_cargo_tolerance'                   => (float) $this->settingsHeavyCargoTolerance,
                'commission_refund_tracking_tolerance'    => (float) $this->settingsCommissionRefundTrackingTolerance,
                'missing_payment_tolerance'               => (float) $this->settingsMissingPaymentTolerance,
                'sunk_cost_critical_threshold'            => (float) $this->settingsSunkCostCriticalThreshold,
                'hakedis_critical_threshold'              => (float) $this->settingsHakedisCriticalThreshold,
                'operational_penalty_critical_threshold'  => (float) $this->settingsOperationalPenaltyCriticalThreshold,
                'multiple_cart_factor'                    => (float) $this->settingsMultipleCartFactor,
                'multiple_cart_desi_tolerance'            => (float) $this->settingsMultipleCartDesiTolerance,
                'missing_payment_critical_threshold'      => (float) $this->settingsMissingPaymentCriticalThreshold,
                'price_drop_percentage'                   => (float) $this->settingsPriceDropPercentage,
                'price_drop_min_orders'                   => (int) $this->settingsPriceDropMinOrders,
                'commission_rate_change_threshold'        => (float) $this->settingsCommissionRateChangeThreshold,
                'commission_rate_change_min_orders'       => (int) $this->settingsCommissionRateChangeMinOrders,
                'service_fee_increase_threshold'          => (float) $this->settingsServiceFeeIncreaseThreshold,
                'service_fee_increase_min_orders'         => (int) $this->settingsServiceFeeIncreaseMinOrders,
                'high_return_rate_threshold'              => (float) $this->settingsHighReturnRateThreshold,
                'high_return_rate_min_quantity'           => (int) $this->settingsHighReturnRateMinQuantity,
                'high_cancellation_rate_threshold'        => (float) $this->settingsHighCancellationRateThreshold,
                'high_cancellation_rate_min_orders'       => (int) $this->settingsHighCancellationRateMinOrders,
                'cargo_over_cost_ratio'                   => (float) $this->settingsCargoOverCostRatio,
                'extreme_margin_positive_threshold'       => (float) $this->settingsExtremeMarginPositiveThreshold,
                'extreme_margin_negative_threshold'       => (float) $this->settingsExtremeMarginNegativeThreshold,
                'negative_hakedis_threshold'              => (float) $this->settingsNegativeHakedisThreshold,
                'campaign_loss_min_total_loss'            => (float) $this->settingsCampaignLossMinTotalLoss,
                'campaign_loss_min_order_count'           => (int) $this->settingsCampaignLossMinOrderCount,
            ],
            'audit_rules' => [
                'disabled' => array_values(array_unique($this->disabledAuditRules)),
            ],
            'audit_behavior' => [
                'log_info_rules'                       => (bool) $this->settingsLogInfoRules,
                'transaction_check_commission_enabled' => (bool) $this->settingsTransactionCheckCommissionEnabled,
                'transaction_check_cargo_enabled'      => (bool) $this->settingsTransactionCheckCargoEnabled,
            ],
            'reconciliation' => [
                'commission_match_tolerance' => (float) $this->settingsCommissionMatchTolerance,
                'cargo_match_tolerance'      => (float) $this->settingsCargoMatchTolerance,
                'invoice_vat_divisor'        => (float) $this->settingsInvoiceVatDivisor,
            ],
            'payment' => [
                'delayed_payment_days' => (int) $this->settingsDelayedPaymentDays,
            ],
            'general' => [
                'marketplace'           => $this->settingsMarketplace,
                'currency'              => $this->settingsCurrency,
                'default_cargo_company' => $this->settingsDefaultCargoCompany,
            ],
            'company' => [
                'name'       => $this->settingsCompanyName,
                'tax_number' => $this->settingsCompanyTaxNumber,
                'tax_office' => $this->settingsCompanyTaxOffice,
                'phone'      => $this->settingsCompanyPhone,
                'email'      => $this->settingsCompanyEmail,
                'address'    => $this->settingsCompanyAddress,
                'iban'       => $this->settingsCompanyIban,
                'bank'       => $this->settingsCompanyBank,
                'branch'     => $this->settingsCompanyBranch,
                'manager'    => $this->settingsCompanyManager,
                'mersis'     => $this->settingsCompanyMersis,
            ],
            'profitability' => [
                'target_margin'          => (float) $this->settingsTargetProfitMargin,
                'min_margin'             => (float) $this->settingsMinProfitMargin,
                'default_packaging_cost' => (float) $this->settingsDefaultPackagingCost,
            ],
            'ui' => [
                'visible_columns' => $this->visibleColumns,
                'help_tips_enabled' => (bool) $this->settingsHelpTipsEnabled,
            ],
        ]);

        // Eski MpFinancialRule tablosuna da senkron yaz (geriye uyumluluk)
        $ruleSync = [
            'barem_limit'              => $this->settingsBaremLimit,
            'stopaj_rate'              => $this->settingsStopajRate,
            'expense_vat_rate'         => $this->settingsExpenseVatRate,
            'default_product_vat_rate' => $this->settingsDefaultProductVatRate,
        ];
        foreach ($ruleSync as $key => $value) {
            \App\Models\MpFinancialRule::updateOrCreate(
                ['rule_key' => $key, 'valid_from' => '2024-01-01'],
                ['rule_value' => (string) $value, 'marketplace' => 'Trendyol']
            );
        }

        session()->flash('settings_success', 'Tüm ayarlar başarıyla kaydedildi.');
    }

    public function saveDesiPrices()
    {
        foreach ($this->settingsDesiPrices as $company => $ranges) {
            foreach ($ranges as $rangeKey => $price) {
                if ($price === null || $price === '') continue;
                \App\Models\MpFinancialRule::updateOrCreate(
                    ['rule_key' => $rangeKey, 'category' => $company, 'valid_from' => '2025-01-01'],
                    ['rule_value' => (string) (float) $price, 'marketplace' => 'Trendyol']
                );
            }
        }
        session()->flash('settings_success', 'Desi fiyatları başarıyla güncellendi.');
    }

    public function saveBaremPrices()
    {
        foreach ($this->settingsBaremPrices as $company => $ranges) {
            foreach ($ranges as $rangeKey => $price) {
                if ($price === null || $price === '') continue;
                \App\Models\MpFinancialRule::updateOrCreate(
                    ['rule_key' => $rangeKey, 'category' => $company, 'valid_from' => '2025-01-01'],
                    ['rule_value' => (string) (float) $price, 'marketplace' => 'Trendyol']
                );
            }
        }
        session()->flash('settings_success', 'Barem fiyatları başarıyla güncellendi.');
    }

    public function addCargoCompany()
    {
        $name = trim($this->newCargoCompany);
        if (empty($name)) return;
        if (in_array($name, $this->settingsCargoCompanies)) {
            session()->flash('settings_error', 'Bu kargo firması zaten listede.');
            return;
        }
        $this->settingsCargoCompanies[] = $name;
        $this->newCargoCompany = '';

        $desiKeys = array_column(app(\App\Services\MpSettingsService::class)->getDesiRanges(), 'key');
        $baremKeys = array_column(app(\App\Services\MpSettingsService::class)->getBaremRanges(), 'key');

        $this->settingsDesiPrices[$name] = array_fill_keys($desiKeys, null);
        $this->settingsBaremPrices[$name] = array_fill_keys($baremKeys, null);
    }

    public function removeCargoCompany(string $company)
    {
        $this->settingsCargoCompanies = array_values(array_filter($this->settingsCargoCompanies, fn($c) => $c !== $company));
        unset($this->settingsDesiPrices[$company]);
        unset($this->settingsBaremPrices[$company]);
    }

    public function addDesiRange()
    {
        $key = trim($this->newDesiRangeKey);
        if ($key === '' || $this->newDesiRangeMin > $this->newDesiRangeMax) {
            session()->flash('settings_error', 'Geçersiz aralık bilgisi.');
            return;
        }

        $svc = app(MpSettingsService::class);
        $ranges = $svc->getDesiRanges();

        foreach ($ranges as $range) {
            if ($range['key'] === $key) {
                session()->flash('settings_error', 'Bu anahtar zaten mevcut.');
                return;
            }
        }

        $ranges[] = [
            'key' => $key,
            'min' => $this->newDesiRangeMin,
            'max' => $this->newDesiRangeMax,
            'label' => $this->newDesiRangeLabel !== '' ? $this->newDesiRangeLabel : $key,
        ];

        $svc->set('cargo.desi_ranges', $ranges);

        foreach ($this->settingsCargoCompanies as $company) {
            $this->settingsDesiPrices[$company][$key] = null;
        }

        $this->newDesiRangeKey = '';
        $this->newDesiRangeMin = 0;
        $this->newDesiRangeMax = 0;
        $this->newDesiRangeLabel = '';

        session()->flash('settings_success', "Desi aralığı '{$key}' eklendi.");
    }

    public function removeDesiRange(string $key)
    {
        $svc = app(MpSettingsService::class);
        $ranges = $svc->getDesiRanges();
        $ranges = array_values(array_filter($ranges, fn($r) => $r['key'] !== $key));
        $svc->set('cargo.desi_ranges', $ranges);

        foreach ($this->settingsCargoCompanies as $company) {
            unset($this->settingsDesiPrices[$company][$key]);
        }

        session()->flash('settings_success', "Desi aralığı '{$key}' kaldırıldı.");
    }

    public function addBaremRange()
    {
        $key = trim($this->newBaremRangeKey);
        if ($key === '' || $this->newBaremRangeMin >= $this->newBaremRangeMax) {
            session()->flash('settings_error', 'Geçersiz aralık bilgisi. Min, max\'ten küçük olmalı.');
            return;
        }

        $svc = app(MpSettingsService::class);
        $ranges = $svc->getBaremRanges();

        foreach ($ranges as $range) {
            if ($range['key'] === $key) {
                session()->flash('settings_error', 'Bu anahtar zaten mevcut.');
                return;
            }
        }

        $ranges[] = [
            'key' => $key,
            'min' => $this->newBaremRangeMin,
            'max' => $this->newBaremRangeMax,
            'label' => $this->newBaremRangeLabel !== '' ? $this->newBaremRangeLabel : $key,
        ];

        $svc->set('cargo.barem_ranges', $ranges);

        foreach ($this->settingsCargoCompanies as $company) {
            $this->settingsBaremPrices[$company][$key] = null;
        }

        $this->newBaremRangeKey = '';
        $this->newBaremRangeMin = 0;
        $this->newBaremRangeMax = 0;
        $this->newBaremRangeLabel = '';

        session()->flash('settings_success', "Barem aralığı '{$key}' eklendi.");
    }

    public function removeBaremRange(string $key)
    {
        $svc = app(MpSettingsService::class);
        $ranges = $svc->getBaremRanges();
        $ranges = array_values(array_filter($ranges, fn($r) => $r['key'] !== $key));
        $svc->set('cargo.barem_ranges', $ranges);

        foreach ($this->settingsCargoCompanies as $company) {
            unset($this->settingsBaremPrices[$company][$key]);
        }

        session()->flash('settings_success', "Barem aralığı '{$key}' kaldırıldı.");
    }

    public function addHeavyCargoPenalty()
    {
        $company = trim($this->newPenaltyCompany);
        $amount = (float) $this->newPenaltyAmount;
        if (empty($company) || $amount <= 0) return;
        $this->settingsHeavyCargoPenalties[$company] = $amount;
        $this->newPenaltyCompany = '';
        $this->newPenaltyAmount = 0;
    }

    public function removeHeavyCargoPenalty(string $company)
    {
        unset($this->settingsHeavyCargoPenalties[$company]);
    }

    public function saveErpSettings()
    {
        \App\Models\MpErpSetting::updateOrCreate(
            ['user_id' => Auth::id()],
            [
                'provider_name'          => $this->erpProvider,
                'webhook_url'            => $this->erpWebhookUrl,
                'api_key'                => $this->erpApiKey,
                'api_secret'             => $this->erpApiSecret,
                'auto_push_on_reconcile' => $this->erpAutoPush,
                'is_active'              => $this->erpIsActive,
            ]
        );
        session()->flash('success_erp', 'ERP / Webhook ayarları başarıyla kaydedildi.');
    }

    public function resetToDefaults()
    {
        $svc = new MpSettingsService();
        $svc->reset();
        $this->loadSettings();
        session()->flash('settings_success', 'Tüm ayarlar fabrika değerlerine sıfırlandı.');
    }

    public function toggleSettingsSection(string $section)
    {
        $this->settingsActiveSection = ($this->settingsActiveSection === $section) ? '' : $section;
    }

    #[Computed]
    public function effectiveDisabledAuditRules(): array
    {
        $disabledRules = $this->disabledAuditRules;

        if (!$this->settingsLogInfoRules) {
            $disabledRules = array_merge(
                $disabledRules,
                collect(AuditEngine::RULE_META)
                    ->filter(fn (array $meta) => ($meta['severity'] ?? null) === 'info')
                    ->keys()
                    ->all()
            );
        }

        return array_values(array_unique($disabledRules));
    }

    #[Computed]
    public function activeAuditRuleCount(): int
    {
        return count(AuditEngine::RULES) - count($this->effectiveDisabledAuditRules());
    }

    public function toggleColumn(string $column)
    {
        if (in_array($column, $this->visibleColumns)) {
            $this->visibleColumns = array_values(array_diff($this->visibleColumns, [$column]));
        } else {
            $this->visibleColumns[] = $column;
        }

        $svc = new MpSettingsService();
        $svc->set('ui.visible_columns', $this->visibleColumns);
    }

    public function sortOrders(string $column)
    {
        $dbColumn = self::$sortableColumns[$column] ?? null;
        if (!$dbColumn) return;

        if ($this->orderSortBy === $dbColumn) {
            $this->orderSortDir = $this->orderSortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->orderSortBy = $dbColumn;
            $this->orderSortDir = 'asc';
        }

        $this->resetPage();
    }

    public function updatedBulkFiles()
    {
        // Drop zone'dan yüklenen çoklu dosyaları sadece listede tutuyoruz.
    }

    public function updatedSelectAll($value)
    {
        if ($value) {
            // Sadece o sayfadakileri veya filtrelileri işaretle (Bunun yerine tüm sonuçları almak performansı etkiler, paginate edildi)
            // Canlı Livewire $orders içinde dönüleceği için Blade'de checkbox üzerinden bağlanması daha sağlıklıdır.
            // Fakat basitlik adına blade'deki her checkbox `wire:model="selectedOrders"` seçecek.
        } else {
            $this->selectedOrders = [];
        }
    }



    // ─── Period Management ──────────────────────────────────────

    public function updatedSelectedYear()
    {
        $this->selectPeriod();
    }

    public function updatedSelectedMonth()
    {
        $this->selectPeriod();
    }

    public function changeSelectedYear($value): void
    {
        $this->selectedYear = (int) $value;
        $this->selectPeriod();
    }

    public function changeSelectedMonth($value): void
    {
        $this->selectedMonth = (int) $value;
        $this->selectPeriod();
    }

    public function updatedPerPage()
    {
        $this->resetPage();
    }

    public function selectPeriod($periodId = null)
    {
        $this->selectedYear = (int) $this->selectedYear;
        $this->selectedMonth = (int) $this->selectedMonth;

        if ($periodId) {
            $this->selectedPeriodId = $periodId;
            $period = MpPeriod::where('user_id', Auth::id())->find($periodId);
            if ($period) {
                $this->selectedYear = $period->year;
                $this->selectedMonth = $period->month;
            }
        } else {
            // Yıl ve ay dropdown'undan seçildiyse (Eğer 0 'Tüm Yıl' değilse otomatik oluştur):
            if ($this->selectedMonth > 0) {
                $period = MpPeriod::firstOrCreate(
                    [
                        'user_id' => Auth::id(),
                        'year'    => $this->selectedYear,
                        'month'   => $this->selectedMonth,
                    ],
                    [
                        'marketplace' => 'Trendyol',
                        'status'      => 'draft',
                    ]
                );
                $this->selectedPeriodId = $period->id;
            } else {
                // "Tüm Yıl" modu
                $this->selectedPeriodId = null;
            }
        }

        $this->importErrors = [];
        $this->importStatus = '';
        $this->selectedOrders = [];
        $this->selectAll = false;
        $this->lastAuditResult = null;
        unset(
            $this->selectedPeriod,
            $this->selectedPeriodLabel,
            $this->hasSelectedPeriodScope,
            $this->dashboardStats,
            $this->profitData,
            $this->invoiceReconciliation
        );
        $this->resetPage(); // Dönem değişince sayfalamayı sıfırla
    }

    public function createPeriod()
    {
        $period = tap(new MpPeriod([
            'user_id'     => Auth::id(),
            'seller_id'   => null,
            'year'        => $this->selectedYear,
            'month'       => $this->selectedMonth,
            'marketplace' => 'Trendyol',
            'status'      => 'draft',
        ]))->save();

        $this->selectedPeriodId = $period->id;
        $this->importErrors = [];
        $this->importStatus = '';
        
        session()->flash('success', 'Yeni dönem oluşturuldu.');
    }

    // ─── File Import (Senkron İşleme) ─────────────────────────────

    public function importOrders()
    {
        $this->validate(['ordersFile' => 'required|file|mimes:xlsx,xls']);
        $this->processImport('orders', $this->ordersFile);
        $this->dispatch('import-finished');
    }

    public function importTransactions()
    {
        $this->validate(['transactionsFile' => 'required|file|mimes:xlsx,xls']);
        $this->processImport('transactions', $this->transactionsFile);
        $this->dispatch('import-finished');
    }

    public function importStopaj()
    {
        $this->validate(['stopajFile' => 'required|file|mimes:xlsx,xls']);
        $this->processImport('stopaj', $this->stopajFile);
        $this->dispatch('import-finished');
    }

    public function importInvoices()
    {
        $this->validate(['invoicesFile' => 'required|file|mimes:xlsx,xls']);
        $this->processImport('invoices', $this->invoicesFile);
        $this->dispatch('import-finished');
    }

    public function importSettlements()
    {
        $this->validate(['settlementsFile' => 'required|file|mimes:xlsx,xls']);
        $this->processImport('settlements', $this->settlementsFile);
        $this->dispatch('import-finished');
    }

    public function importAll()
    {
        $processed = false;
        $totalProcessed = 0;
        $totalRead = 0;

        // EĞER SÜRÜKLE BIRAK'LA ÇOKLU DOSYA (BULK) SEÇİLDİYSE
        if (!empty($this->bulkFiles) && is_array($this->bulkFiles)) {
            foreach ($this->bulkFiles as $file) {
                $name = mb_strtolower($file->getClientOriginalName());
                $type = null;

                if (str_contains($name, 'siparis') || str_contains($name, 'sipariş')) {
                    $type = 'orders';
                } elseif (str_contains($name, 'cari') || str_contains($name, 'ekstre')) {
                    $type = 'transactions';
                } elseif (str_contains($name, 'fatura') || str_contains($name, 'invoice') || str_contains($name, 'toplu')) {
                    $type = 'invoices';
                } elseif (str_contains($name, 'stopaj') || str_contains($name, 'tevkifat')) {
                    $type = 'stopaj';
                } elseif (str_contains($name, 'ödeme') || str_contains($name, 'odeme') || str_contains($name, 'hakediş') || str_contains($name, 'hakedis')) {
                    $type = 'settlements';
                }

                if ($type) {
                    $stats = $this->processImportInternal($type, $file);
                    $totalProcessed += $stats['processed'];
                    $totalRead += $stats['read'];
                    $processed = true;
                }
            }
            $this->reset('bulkFiles');
            $this->importStatus = "✅ Toplu aktarım tamamlandı! Tüm dosyalar veritabanına aktarıldı.";
            session()->flash('import_success', "Seçilen tüm dosyalar başarıyla veritabanına aktarıldı. Sonuçlar sisteme yansımıştır.");
        } 
        else {
            // ALTTAKİ TEKİL KUTULAR KULLANILDIYSA
            if ($this->ordersFile) {
                $st = $this->processImport('orders', $this->ordersFile);
                $totalProcessed += $st['processed']; $totalRead += $st['read'];
                $processed = true;
            }
            if ($this->transactionsFile) {
                $st = $this->processImport('transactions', $this->transactionsFile);
                $totalProcessed += $st['processed']; $totalRead += $st['read'];
                $processed = true;
            }
            if ($this->stopajFile) {
                $st = $this->processImport('stopaj', $this->stopajFile);
                $totalProcessed += $st['processed']; $totalRead += $st['read'];
                $processed = true;
            }
            if ($this->invoicesFile) {
                $st = $this->processImport('invoices', $this->invoicesFile);
                $totalProcessed += $st['processed']; $totalRead += $st['read'];
                $processed = true;
            }
            if ($this->settlementsFile) {
                $st = $this->processImport('settlements', $this->settlementsFile);
                $totalProcessed += $st['processed']; $totalRead += $st['read'];
                $processed = true;
            }

            if ($processed) {
                $this->importStatus = "✅ Excel dosyaları başarıyla işlendi ve veritabanına kaydedildi.";
                session()->flash('import_success', "Dosyalar başarıyla işlendi. Sonuçlar anında sisteme yansıtıldı.");
            }
        }

        if (!$processed) {
            session()->flash('import_error', 'Lütfen en az bir dosya seçin veya sürükleyin.');
        }
        $this->dispatch('import-finished');
    }

    /**
     * Tüm verileri sıfırla (Kullanıcının sistemi temizleyip baştan upload etmesi için)
     */
    public function resetAllData()
    {
        $userId = Auth::id();
        if (!$userId) {
            session()->flash('import_error', 'Kullanıcı doğrulaması bulunamadı.');
            return;
        }

        try {
            DB::transaction(function () use ($userId) {
                $periodIds = \App\Models\MpPeriod::where('user_id', $userId)->pluck('id');

                if ($periodIds->isEmpty()) {
                    return;
                }

                \App\Models\MpAuditLog::whereIn('period_id', $periodIds)->delete();
                \App\Models\MpSettlement::where(function ($q) use ($userId, $periodIds) {
                    $q->where('user_id', $userId)
                        ->orWhereIn('period_id', $periodIds);
                })->delete();
                \App\Models\MpInvoice::whereIn('period_id', $periodIds)->delete();
                \App\Models\MpTransaction::whereIn('period_id', $periodIds)->delete();
                \App\Models\MpOrder::whereIn('period_id', $periodIds)->delete();
                \App\Models\MpPeriod::whereIn('id', $periodIds)->delete();
            });

            $this->selectedPeriodId = null;
            $this->selectedYear = date('Y');
            $this->selectedMonth = date('m');
            $this->importStatus = "✅ Sistemdeki tüm pazaryeri verileri sıfırlandı.";
            
            session()->flash('import_success', 'Tüm veri tabanı kalıcı olarak temizlendi. Artık dosyalarınızı sıfırdan yükleyebilirsiniz.');
            $this->dispatch('import-finished');

        } catch (\Exception $e) {
            session()->flash('import_error', 'Veriler sıfırlanırken bir hata oluştu: ' . $e->getMessage());
        }
    }

    /**
     * Mükerrer sipariş kayıtlarını temizle (en düşük ID'liyi tut, fazlasını sil)
     */
    public function cleanDuplicateOrders()
    {
        try {
            $dupes = DB::table('mp_orders')
                ->select('order_number', 'barcode', 'period_id', DB::raw('MIN(id) as keep_id'), DB::raw('COUNT(*) as cnt'))
                ->groupBy('order_number', 'barcode', 'period_id')
                ->havingRaw('COUNT(*) > 1')
                ->get();

            if ($dupes->isEmpty()) {
                session()->flash('success', '✅ Mükerrer sipariş kaydı bulunamadı. Verileriniz temiz!');
                return;
            }

            $totalDeleted = 0;
            foreach ($dupes as $dupe) {
                $deleted = DB::table('mp_orders')
                    ->where('order_number', $dupe->order_number)
                    ->where('period_id', $dupe->period_id)
                    ->where(function ($q) use ($dupe) {
                        if ($dupe->barcode) {
                            $q->where('barcode', $dupe->barcode);
                        } else {
                            $q->where(function ($sq) {
                                $sq->whereNull('barcode')->orWhere('barcode', '');
                            });
                        }
                    })
                    ->where('id', '!=', $dupe->keep_id)
                    ->delete();
                $totalDeleted += $deleted;
            }

            session()->flash('success', "🧹 {$totalDeleted} adet mükerrer sipariş kaydı temizlendi! (Etkilenen grup: {$dupes->count()})");
        } catch (\Exception $e) {
            session()->flash('import_error', 'Mükerrer temizleme hatası: ' . $e->getMessage());
        }
    }

    /**
     * Tekli dosya kaydında UI güncelleyen wrapper
     */
    protected function processImport(string $type, $file): array
    {
        $stats = $this->processImportInternal($type, $file);
        
        if (empty($this->importErrors)) {
            $name = $file->getClientOriginalName();
            $this->importStatus = "✅ {$name} dosyası başarıyla işlendi ve veritabanına kaydedildi.";
        }
        
        // File input'u sıfırla
        $this->reset(match ($type) {
            'orders'       => 'ordersFile',
            'transactions' => 'transactionsFile',
            'stopaj'       => 'stopajFile',
            'invoices'     => 'invoicesFile',
            'settlements'  => 'settlementsFile',
        });

        return $stats;
    }

    public function lockPeriod()
    {
        if (!$this->selectedPeriodId) return;
        $period = MpPeriod::find($this->selectedPeriodId);
        if ($period) {
            $period->update(['is_locked' => true]);
            session()->flash('success', 'Dönem kilitlendi. Veri aktarımı durduruldu.');
            $this->loadData();
        }
    }

    public function unlockPeriod()
    {
        if (!$this->selectedPeriodId) return;
        $period = MpPeriod::find($this->selectedPeriodId);
        if ($period) {
            $period->update(['is_locked' => false]);
            session()->flash('success', 'Dönem kilidi açıldı. Veri aktarımına izin verildi.');
            $this->loadData();
        }
    }

    /**
     * Dosyayı doğrudan (senkron) işleyen çekirdek fonksiyon
     */
    protected function processImportInternal(string $type, $file): array
    {
        if (!$this->selectedPeriodId) {
            $this->createPeriod();
        }

        try {
            // Büyük dosyalar için PHP limitlerini artır
            set_time_limit(600);
            ini_set('memory_limit', '1024M');

            $period = MpPeriod::findOrFail($this->selectedPeriodId);

            if ($period->is_locked) {
                session()->flash('import_error', 'Bu dönem kilitli! Kapatılan aylara yeni veri aktarılamaz. Önce kilidi açınız.');
                return ['processed' => 0, 'read' => 0];
            }

            // Dosyayı sunucu tarafında temp dizinine kaydet
            $originalName = $file->getClientOriginalName();
            $tempPath = $file->storeAs('imports', uniqid() . '_' . $originalName);

            // Senkron çalıştır — Queue worker olmadan da çalışır
            \App\Jobs\ProcessMarketplaceImport::dispatchSync(
                $this->selectedPeriodId,
                (int) ($period->user_id ?? auth()->id() ?? 0),
                $type,
                $tempPath,
                $originalName
            );

            $this->importStatus = "✅ {$originalName} başarıyla veritabanına aktarıldı!";
            
            return ['processed' => 1, 'read' => 1];

        } catch (\Exception $e) {
            $this->importStatus = "❌ Hata (" . $file->getClientOriginalName() . "): " . $e->getMessage();
            $this->importErrors[] = $e->getMessage();
            Log::error('MP Import Error', ['type' => $type, 'file' => $file->getClientOriginalName(), 'error' => $e->getMessage()]);
            return ['processed' => 0, 'read' => 0];
        }
    }

    // ─── Audit Engine ───────────────────────────────────────────

    public function runAudit()
    {
        if (!$this->selectedPeriodId) return;

        $period = MpPeriod::findOrFail($this->selectedPeriodId);
        $engine = new AuditEngine();

        try {
            $this->lastAuditResult = $engine->runAllRules($period, $this->disabledAuditRules);
            $activeCount = $this->activeAuditRuleCount();
            $this->importStatus = "🔍 Denetim tamamlandı! "
                . $activeCount . " kural çalıştırıldı. "
                . ($this->lastAuditResult['total_errors'] ?? 0) . " kritik, "
                . ($this->lastAuditResult['total_warnings'] ?? 0) . " uyarı bulundu.";
        } catch (\Exception $e) {
            $this->importStatus = "❌ Denetim hatası: " . $e->getMessage();
        }
    }

    public function toggleAuditRule(string $rule)
    {
        if (in_array($rule, $this->disabledAuditRules)) {
            $this->disabledAuditRules = array_values(array_diff($this->disabledAuditRules, [$rule]));
        } else {
            $this->disabledAuditRules[] = $rule;
        }

        $this->disabledAuditRules = array_values(array_unique($this->disabledAuditRules));

        (new MpSettingsService())->set('audit_rules.disabled', $this->disabledAuditRules);
    }

    public function updatedSettingsLogInfoRules(bool $value): void
    {
        (new MpSettingsService())->set('audit_behavior.log_info_rules', (bool) $value);
    }

    public function updatedSettingsTransactionCheckCommissionEnabled(bool $value): void
    {
        (new MpSettingsService())->set('audit_behavior.transaction_check_commission_enabled', (bool) $value);
    }

    public function updatedSettingsTransactionCheckCargoEnabled(bool $value): void
    {
        (new MpSettingsService())->set('audit_behavior.transaction_check_cargo_enabled', (bool) $value);
    }

    public function updatedSettingsUsesOwnCargo(bool $value): void
    {
        (new MpSettingsService())->set('cargo.uses_own_cargo', (bool) $value);
    }

    // ─── Search (5N1K) ──────────────────────────────────────────

    public function searchOrder()
    {
        if (empty(trim($this->searchQuery))) {
            $this->searchResult = null;
            return;
        }

        // Search in selected period OR selected year if period is "All Year" (0)
        $orders = MpOrder::with(['period', 'operationalOrder.items'])
            ->when($this->selectedPeriodId, function ($q) {
                $q->where('period_id', $this->selectedPeriodId);
            })
            ->when(!$this->selectedPeriodId && $this->selectedYear, function ($q) {
                // Eğer period seçilmemişse ama yıl seçilmişse (Örn: Tüm Yıl)
                $q->whereHas('period', function ($sq) {
                    $sq->where('year', $this->selectedYear);
                });
            })
            ->search($this->searchQuery)
            ->limit(50) // Performans için limiti 20'den 50'ye çıkardım ama genel olarak sayfalamalı
            ->get();

        $this->searchResult = $orders->map(function ($order) {
            return [
                'id'               => $order->id,
                'order_number'     => $order->order_number,
                'product_name'     => $order->resolved_product_name,
                'barcode'          => $order->resolved_barcode,
                'status'           => $order->status,
                'status_color'     => $order->status_color,
                'gross_amount'     => $order->gross_amount,
                'net_hakedis'      => $order->net_hakedis,
                'commission_amount' => $order->commission_amount,
                'cargo_amount'     => $order->cargo_amount,
                'is_flagged'       => $order->is_flagged,
                'order_date'       => $order->order_date?->format('d.m.Y'),
                'period_name'      => $order->period ? $order->period->period_name : '',
            ];
        })->toArray();
    }

    public function showOrderDetail(int $orderId)
    {
        $this->selectedOrderId = $orderId;
        $service = new OrderDetailsService();
        $this->selectedOrderDetails = $service->getOrderDetails($orderId) ?? [];
        $this->showOrderModal = true;
    }

    public function closeOrderModal()
    {
        $this->showOrderModal = false;
        $this->selectedOrderId = null;
        $this->selectedOrderDetails = [];
    }

    // ─── Bulk Reconcile & ERP İşlemleri ─────────────────────────

    public function bulkReconcile($action)
    {
        if (empty($this->selectedOrders)) return;

        $isReconciled = $action === 'lock';

        MpOrder::whereIn('id', $this->selectedOrders)
            ->update(['is_reconciled' => $isReconciled]);

        if ($isReconciled) {
            session()->flash('success_orders', count($this->selectedOrders) . ' adet sipariş başarıyla mutabık (Kilitli) olarak işaretlendi.');
            
            // Eğer Auto-Push ayarı açıksa, kilitlenenleri direkt ERP'ye fırlat
            if ($this->erpAutoPush) {
                $this->bulkPushToErp($this->selectedOrders);
            }
        } else {
            session()->flash('success_orders', count($this->selectedOrders) . ' adet siparişin kilidi açıldı.');
        }

        $this->selectedOrders = [];
        $this->selectAll = false;
    }

    public function bulkPushToErp($orderIds = null)
    {
        $idsToPush = $orderIds ?? $this->selectedOrders;

        if (empty($idsToPush)) return;

        $ordersToQueue = MpOrder::whereIn('id', $idsToPush)
             ->where('is_reconciled', true)
             ->pluck('id')->toArray();

        if (empty($ordersToQueue)) {
            session()->flash('error_orders', 'Sadece "Mutabık (Kilitli)" olarak işaretlenmiş siparişler ERP\'ye gönderilebilir.');
            return;
        }

        $setting = \App\Models\MpErpSetting::where('user_id', Auth::id())->first();

        if (!$setting || !$setting->webhook_url || !$setting->is_active) {
            session()->flash('error_orders', 'ERP ayarlarına (Tab: Muhasebe Ayarları) girip Webhook URL tanımlamalısınız ve entegrasyonu aktif etmelisiniz.');
            return;
        }

        $service = new \App\Services\ErpIntegrationService();
        $service->queueForErp($ordersToQueue, $setting);

        session()->flash('success_orders', count($ordersToQueue) . " adet mutabık sipariş ERP'ye gönderilmek üzere asenkron (Queue) sıraya alındı.");
        
        if (!$orderIds) {
            $this->selectedOrders = [];
            $this->selectAll = false;
        }
    }

    public function retryFailedErpPushes()
    {
        if (!$this->selectedPeriodId) return;

        $failedOrderIds = MpOrder::where('period_id', $this->selectedPeriodId)
            ->where('erp_status', 'failed')
            ->pluck('id')->toArray();

        // Retry atanlar ("retry" statüsünde kalan, yani henüz handle exception'dan fully "failed" dönmeyen ama başarısız olanları da alabiliriz. Biz tam red yiyenleri alıyoruz.)
        
        if (empty($failedOrderIds)) {
            session()->flash('error_orders', 'Bu dönemde başarısız ERP gönderimi bulunmuyor.');
            return;
        }

        $this->bulkPushToErp($failedOrderIds);
    }

    // ─── Export Actions ─────────────────────────────────────────

    public function exportAllOrders()
    {
        if (!$this->selectedPeriodId) return;
        $period = MpPeriod::findOrFail($this->selectedPeriodId);
        $path = (new MarketplaceExportService())->exportAllOrders($period);
        return response()->download($path)->deleteFileAfterSend(true);
    }

    public function exportAuditReport()
    {
        if (!$this->selectedPeriodId) return;
        $period = MpPeriod::findOrFail($this->selectedPeriodId);
        $path = (new MarketplaceExportService())->exportAuditReport($period);
        return response()->download($path)->deleteFileAfterSend(true);
    }

    public function exportStopajReport()
    {
        if (!$this->selectedPeriodId) return;
        $period = MpPeriod::findOrFail($this->selectedPeriodId);
        $path = (new MarketplaceExportService())->exportStopajReport($period);
        return response()->download($path)->deleteFileAfterSend(true);
    }

    public function exportMonthlyPivot()
    {
        if (!$this->selectedPeriodId) return;
        $period = MpPeriod::findOrFail($this->selectedPeriodId);
        $path = (new MarketplaceExportService())->exportMonthlyPivot($period);
        return response()->download($path)->deleteFileAfterSend(true);
    }

    public function exportUnitEconomics()
    {
        if (!$this->selectedPeriodId) return;
        $period = MpPeriod::findOrFail($this->selectedPeriodId);
        $path = (new MarketplaceExportService())->exportUnitEconomics($period);
        return response()->download($path)->deleteFileAfterSend(true);
    }

    // ─── Kârlılık Tab Sort ──────────────────────────────────────

    /**
     * Ürün maliyetlerini (COGS) mp_products tablosundan okuyup
     * seçili dönemdeki siparişlerin cogs_at_time değerlerini günceller.
     * Barkod ve stok kodu ile eşleştirme yapar.
     */
    public function syncCogs()
    {
        if (!$this->selectedPeriodId) {
            session()->flash('import_error', 'Önce bir dönem seçin.');
            return;
        }

        $period = MpPeriod::findOrFail($this->selectedPeriodId);
        $userId = Auth::id() ?? $period->user_id;

        if (!$userId) {
            session()->flash('import_error', 'Bu dönem için kullanıcı bulunamadı.');
            return;
        }

        // Tüm ürünleri bir kerede çek (N+1 önleme)
        $products = \App\Models\MpProduct::with(['productSet.items.componentProduct'])
            ->where('user_id', $userId)
            ->get();
        $compositionResolver = app(\App\Services\ProductCompositionResolver::class);

        // Barkod → ürün ve stok kodu → ürün haritaları oluştur
        $byBarcode   = $products->keyBy('barcode');
        $byStockCode = $products->filter(fn($p) => $p->stock_code)->keyBy('stock_code');
        $byProductName = $products
            ->filter(fn($p) => filled($p->product_name))
            ->groupBy(fn($p) => mb_strtolower(trim((string) $p->product_name)))
            ->map(fn($items) => $items->count() === 1 ? $items->first() : null)
            ->filter();

        // Dönemdeki tüm siparişleri al
        $orders = MpOrder::where('period_id', $this->selectedPeriodId)
            ->with(['period', 'operationalOrder.items'])
            ->get();

        $updated = 0;
        $skipped = 0;
        $notFound = 0;

        foreach ($orders as $order) {
            $resolvedBarcode = $order->resolved_barcode;
            $resolvedStockCode = $order->resolved_stock_code;
            $resolvedProductName = $order->resolved_product_name;

            // Önce barcode ile eşleştir, sonra stock_code ile dene
            $product = $resolvedBarcode ? $byBarcode->get($resolvedBarcode) : null;
            if (!$product && $resolvedStockCode) {
                $product = $byStockCode->get($resolvedStockCode);
            }
            if (!$product && $resolvedProductName) {
                $product = $byProductName->get(mb_strtolower(trim($resolvedProductName)));
            }

            if (!$product) {
                $notFound++;
                continue;
            }

            $qty = max(1, (int) $order->resolved_quantity);
            $composition = $compositionResolver->resolve($product, $qty);

            // COGS 0 / null olan ürünü atla
            if ((float) ($composition['cogs_cost'] ?? 0) <= 0) {
                $skipped++;
                continue;
            }

            // Güncelle — tekil ürün veya set bileşen toplamı
            $updateData = [
                'cogs_at_time'              => round((float) ($composition['cogs_cost'] ?? 0), 2),
                'packaging_cost_at_time'    => round((float) ($composition['packaging_cost'] ?? 0), 2),
                'own_cargo_cost_at_time'    => round((float) ($composition['own_cargo_cost'] ?? 0), 2),
                'product_vat_rate'          => $product->vat_rate ?? 10,
            ];

            if (empty($order->barcode) && !empty($resolvedBarcode)) {
                $updateData['barcode'] = $resolvedBarcode;
            } elseif (empty($order->barcode) && !empty($product->barcode)) {
                $updateData['barcode'] = $product->barcode;
            }

            // Ürün adı veya stok kodu boşsa sync işleminde onu da doldur
            if (empty($order->product_name) && !empty($resolvedProductName)) {
                $updateData['product_name'] = $resolvedProductName;
            } elseif (empty($order->product_name) && !empty($product->product_name)) {
                $updateData['product_name'] = $product->product_name;
            }

            if (empty($order->stock_code) && !empty($resolvedStockCode)) {
                $updateData['stock_code'] = $resolvedStockCode;
            } elseif (empty($order->stock_code) && !empty($product->stock_code)) {
                $updateData['stock_code'] = $product->stock_code;
            }

            if (empty($order->delivery_date) && $order->resolved_delivery_date) {
                $updateData['delivery_date'] = $order->resolved_delivery_date;
            }

            $order->update($updateData);
            $updated++;
        }

        // Dönem istatistiklerini yeniden hesapla
        $period = MpPeriod::find($this->selectedPeriodId);
        if ($period) {
            $period->recalculateStats();
        }

        // Önbelleği temizle
        unset($this->profitData, $this->dashboardStats);

        session()->flash('import_success', sprintf(
            '💰 Maliyet Senkronizasyonu: %d sipariş güncellendi, %d eşleşme bulunamadı, %d ürünün maliyeti tanımsız.',
            $updated,
            $notFound,
            $skipped
        ));
    }

    public function sortProfit(string $column)
    {
        if ($this->profitSortBy === $column) {
            $this->profitSortDir = $this->profitSortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->profitSortBy = $column;
            $this->profitSortDir = 'asc';
        }
    }

    // ─── Computed Properties ────────────────────────────────────

    public function getSelectedPeriodProperty(): ?MpPeriod
    {
        return $this->selectedPeriodId
            ? MpPeriod::find($this->selectedPeriodId)
            : null;
    }

    public function getHasSelectedPeriodScopeProperty(): bool
    {
        return (int) $this->selectedMonth === 0 || $this->selectedPeriodId !== null;
    }

    public function getSelectedPeriodLabelProperty(): string
    {
        if ((int) $this->selectedMonth === 0) {
            return 'Tüm Yıl ' . (int) $this->selectedYear;
        }

        return $this->selectedPeriod?->period_name
            ?? $this->monthLabel((int) $this->selectedMonth) . ' ' . (int) $this->selectedYear;
    }

    public function getSelectedOrderProperty(): ?MpOrder
    {
        return $this->selectedOrderId
            ? MpOrder::with('auditLogs')->find($this->selectedOrderId)
            : null;
    }

    /**
     * Enhanced Dashboard — 5 KPI + audit alert data
     */
    public function getDashboardStatsProperty(): array
    {
        $periodIds = $this->selectedPeriodIds();

        if (empty($periodIds)) {
            return $this->emptyStats();
        }

        try {
            $reportService = new ReportService();
            return $reportService->getDashboardKpis($periodIds);
        } catch (\Exception $e) {
            Log::error('Dashboard KPI Error', ['error' => $e->getMessage()]);
            return $this->emptyStats();
        }
    }

    /**
     * Kârlılık Tab — SKU bazlı kâr/zarar
     */
    public function getProfitDataProperty(): array
    {
        $periodIds = $this->selectedPeriodIds();
        if (empty($periodIds)) return [];

        try {
            $unitService = new UnitEconomicsService();
            $data = collect();

            MpPeriod::whereIn('id', $periodIds)->get()->each(function (MpPeriod $period) use ($unitService, &$data) {
                $data = $data->merge($unitService->profitBySku($period));
            });

            if (count($periodIds) > 1) {
                $data = $data
                    ->groupBy(fn (array $item) => implode('|', [
                        $item['barcode'] ?? '-',
                        $item['stock_code'] ?? '-',
                        $item['product_name'] ?? '-',
                    ]))
                    ->map(function ($items) {
                        $first = $items->first();
                        $totalGross = (float) $items->sum('total_gross');
                        $totalNetProfit = (float) $items->sum('total_net_profit');

                        return [
                            'barcode'          => $first['barcode'] ?? '-',
                            'stock_code'       => $first['stock_code'] ?? '-',
                            'product_name'     => $first['product_name'] ?? '-',
                            'order_count'      => (int) $items->sum('order_count'),
                            'total_quantity'   => (int) $items->sum('total_quantity'),
                            'total_gross'      => round($totalGross, 2),
                            'total_hakedis'    => round((float) $items->sum('total_hakedis'), 2),
                            'total_cogs'       => round((float) $items->sum('total_cogs'), 2),
                            'total_packaging'  => round((float) $items->sum('total_packaging'), 2),
                            'total_net_profit' => round($totalNetProfit, 2),
                            'avg_margin'       => ProfitabilityMetric::multiplierOrZero(
                                $totalNetProfit,
                                ProfitabilityMetric::productCost(
                                    (float) $items->sum('total_cogs'),
                                    (float) $items->sum('total_packaging'),
                                ),
                            ),
                            'bleeding_count'   => (int) $items->sum('bleeding_count'),
                            'is_bleeding'      => $totalNetProfit < 0,
                            'has_cogs'         => $items->contains(fn (array $item) => (bool) ($item['has_cogs'] ?? false)),
                            'cogs_missing_reason' => $items->pluck('cogs_missing_reason')->filter()->first(),
                        ];
                    })
                    ->values();
            }

            if ($this->showOnlyBleeding) {
                $data = $data->where('is_bleeding', true)->values();
            }

            $sorted = $this->profitSortDir === 'asc'
                ? $data->sortBy($this->profitSortBy)
                : $data->sortByDesc($this->profitSortBy);

            return $sorted->values()->toArray();
        } catch (\Exception $e) {
            Log::error('Profit Data Error', ['error' => $e->getMessage()]);
            return [];
        }
    }

    // Epic 7: Toplu Fatura Eşleştirme Sistemi (Invoice Reconciliation)
    #[Computed]
    public function getInvoiceReconciliationProperty()
    {
        $periodIds = $this->selectedPeriodIds();
        if (empty($periodIds)) return null;

        // Dönemdeki komisyon faturalarının KDV Hariç toplamı
        $invoiceCommission = \App\Models\MpTransaction::whereIn('period_id', $periodIds)
            ->where(function($q) {
                $q->where('transaction_type', 'like', '%Komisyon%')
                  ->orWhere('description', 'like', '%Komisyon%');
            })
            ->sum('debt'); // Borç her zaman kesintidir

        $invoiceCargo = \App\Models\MpTransaction::whereIn('period_id', $periodIds)
            ->where(function($q) {
                $q->where('transaction_type', 'like', '%Kargo%')
                  ->orWhere('description', 'like', '%Kargo%');
            })
            ->sum('debt');

        // Dönemdeki Siparişlerin KDV harici matrahlaştırılabilen komisyon ve kargo kesintilerinin toplamı
        // Ayarlardan KDV böleni ve toleransları çek
        $svc = new MpSettingsService();
        $vatDivisor = $svc->getInvoiceVatDivisor();
        $commTolerance = $svc->getCommissionMatchTolerance();
        $cargoTolerance = $svc->getCargoMatchTolerance();

        // Bizdeki sipariş 'commission_amount' genelde KDV dahil Trendyol kesintisidir. Bazı iadelerde pozitif/negatif olabilir, mutlak toplam alınmalı.
        $orderCommissionNet = \App\Models\MpOrder::whereIn('period_id', $periodIds)
            ->selectRaw('SUM(ABS(commission_amount)) as total')->value('total') / $vatDivisor;
            
        $orderCargoNet = \App\Models\MpOrder::whereIn('period_id', $periodIds)
            ->selectRaw('SUM(ABS(cargo_amount)) as total')->value('total') / $vatDivisor;

        // Fark (Mutlak Değer)
        $commissionDiff = abs(abs($invoiceCommission) - abs($orderCommissionNet));
        $cargoDiff = abs(abs($invoiceCargo) - abs($orderCargoNet));

        // Boş ise kapat
        if ($invoiceCommission == 0 && $invoiceCargo == 0 && $orderCommissionNet == 0 && $orderCargoNet == 0) {
            return null;
        }

        return [
            'invoice_commission' => abs($invoiceCommission),
            'order_commission'   => abs($orderCommissionNet),
            'commission_diff'    => $commissionDiff,
            'commission_match'   => $commissionDiff <= $commTolerance,
            
            'invoice_cargo'      => abs($invoiceCargo),
            'order_cargo'        => abs($orderCargoNet),
            'cargo_diff'         => $cargoDiff,
            'cargo_match'        => $cargoDiff <= $cargoTolerance,
        ];
    }

    public function getAvailablePeriodsProperty(): array
    {
        return MpPeriod::orderByDesc('year')
            ->orderByDesc('month')
            ->get()
            ->map(fn($p) => [
                'id'     => $p->id,
                'label'  => $p->period_name,
                'status' => $p->status,
                'orders' => $p->total_orders,
            ])
            ->toArray();
    }

    protected function selectedPeriodIds(): array
    {
        if ((int) $this->selectedMonth === 0) {
            return MpPeriod::where('user_id', Auth::id())
                ->where('year', (int) $this->selectedYear)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->toArray();
        }

        return $this->selectedPeriodId ? [(int) $this->selectedPeriodId] : [];
    }

    protected function monthLabel(int $month): string
    {
        $months = [
            1 => 'Ocak', 2 => 'Şubat', 3 => 'Mart', 4 => 'Nisan',
            5 => 'Mayıs', 6 => 'Haziran', 7 => 'Temmuz', 8 => 'Ağustos',
            9 => 'Eylül', 10 => 'Ekim', 11 => 'Kasım', 12 => 'Aralık',
        ];

        return $months[$month] ?? 'Dönem';
    }

    protected function emptyStats(): array
    {
        return [
            'total_brut'       => 0,
            'total_stopaj'     => 0,
            'logistic_loss'    => ['sunk_cargo' => 0, 'return_cargo' => 0, 'total' => 0],
            'net_vat'          => ['sales_vat' => 0, 'expense_vat' => 0, 'net_vat' => 0, 'is_payable' => false],
            'real_profit'      => ['total_profit' => 0, 'profitable_count' => 0, 'bleeding_count' => 0, 'has_cogs' => false, 'no_cogs_count' => 0],
            'total_hakedis'    => 0,
            'total_orders'     => 0,
            'total_returns'    => 0,
            'total_cancels'    => 0,
            'return_rate'      => 0,
            'audit_count'      => 0,
            'audit_amount'     => 0,
        ];
    }

    // ─── Render ─────────────────────────────────────────────────

    public function render()
    {
        $orders = collect();
        $auditLogs = collect();
        $transactions = collect();

        // Eğer belirli bir dönem seçilmişse onu, Tüm Yıl modunda ise yıl içindeki tüm dönemleri kullan.
        $periodIds = $this->selectedPeriodIds();

        if (!empty($periodIds)) {
            $allUserPeriodIds = MpPeriod::where('user_id', Auth::id())->pluck('id')->toArray();

            $ordersQuery = MpOrder::whereIn('period_id', $periodIds)
                ->when($this->orderStatusFilter !== 'all', fn($q) =>
                    $q->where('status', $this->orderStatusFilter)
                )
                ->when($this->advancedOrderFilter === 'lost_payments', fn($q) =>
                    $q->delivered()->whereNotExists(function ($query) use ($allUserPeriodIds) {
                        $query->select(DB::raw(1))
                            ->from('mp_settlements')
                            ->whereColumn('mp_settlements.order_number', 'mp_orders.order_number')
                            ->where(function ($scopeQuery) use ($allUserPeriodIds) {
                                $scopeQuery->where('mp_settlements.user_id', Auth::id());
                                if (!empty($allUserPeriodIds)) {
                                    $scopeQuery->orWhereIn('mp_settlements.period_id', $allUserPeriodIds);
                                }
                            })
                            ->where('mp_settlements.seller_hakedis', '>', 0);
                    })
                )
                ->when($this->advancedOrderFilter === 'underpaid', fn($q) =>
                    $q->whereHas('auditLogs', fn($sq) => $sq->where('rule_code', 'EKSIK_ODEME'))
                )
                ->when($this->advancedOrderFilter === 'penalized', fn($q) =>
                    $q->whereExists(function ($query) {
                        $query->select(DB::raw(1))
                              ->from('mp_transactions')
                              ->whereColumn('mp_transactions.order_number', 'mp_orders.order_number')
                              ->whereColumn('mp_transactions.period_id', 'mp_orders.period_id')
                              ->where('mp_transactions.transaction_type', 'like', '%ceza%');
                    })
                )
                ->when($this->advancedOrderFilter === 'returned', fn($q) =>
                    $q->returned()
                )
                ->when($this->advancedOrderFilter === 'cancelled', fn($q) =>
                    $q->cancelled()
                )
            ->with(['period', 'settlements', 'operationalOrder.items'])
            ->orderBy($this->orderSortBy, $this->orderSortDir);

        $orders = $ordersQuery->paginate($this->perPage);

            $auditQuery = MpAuditLog::whereIn('period_id', $periodIds)
                ->when($this->auditFilter !== 'all', fn($q) =>
                    $q->where('severity', $this->auditFilter)
                )
                ->orderByDesc('created_at');

            // Çok fazla audit log varsa UI çökmesin diye paginate yapalım
            $auditLogs = $auditQuery->paginate(100, ['*'], 'auditPage');

            // Transaction tabu için sadece son 100 kaydı alıyoruz, çok varsa performansı bozar
            $transactions = MpTransaction::whereIn('period_id', $periodIds)
                ->orderByDesc('transaction_date')
                ->limit(200)
                ->get();
        } else {
            // Hiçbir şey seçili değilse boş dön
            $orders = MpOrder::whereNull('id')->paginate();
        }

        return view('livewire.marketplace-accounting', [
            'orders'       => $orders,
            'auditLogs'    => $auditLogs,
            'transactions' => $transactions,
        ])->layout('layouts.app');
    }


}
