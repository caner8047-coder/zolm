<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Livewire\Attributes\Validate;
use Livewire\Attributes\Computed;
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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

use Illuminate\Support\Facades\DB;

class MarketplaceAccounting extends Component
{
    use WithFileUploads, WithPagination;

    // ─── State ──────────────────────────────────────────────────
    public string $activeTab = 'dashboard';
    public ?int $selectedPeriodId = null;
    public int $selectedYear;
    public int $selectedMonth;
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

    // Bölüm 2: Kargo & Barem
    public float $settingsBaremLimit = 300;
    public string $settingsDefaultCargoCompany = 'TEX';
    public array $settingsCargoCompanies = [];
    public array $settingsHeavyCargoPenalties = [];
    public string $newCargoCompany = '';
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

    // Bölüm 6: Mutabakat & Fatura
    public float $settingsCommissionMatchTolerance = 15.00;
    public float $settingsCargoMatchTolerance = 20.00;
    public float $settingsInvoiceVatDivisor = 1.20;

    // Ödeme
    public int $settingsDelayedPaymentDays = 35;

    // Genel
    public string $settingsMarketplace = 'Trendyol';

    // Ayar UI State
    public string $settingsActiveSection = '';

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
    public string $orderStatusFilter = 'all';
    public string $advancedOrderFilter = 'all'; // 5N1K Hızlı Filtreleri

    // Kârlılık tab
    public string $profitSortBy = 'total_net_profit';
    public string $profitSortDir = 'asc';
    public bool $showOnlyBleeding = false;

    // ─── Listeners ──────────────────────────────────────────────
    protected $listeners = ['refreshComponent' => '$refresh'];

    // ─── Lifecycle ──────────────────────────────────────────────

    public function mount()
    {
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

        // Bölüm 2: Kargo
        $this->settingsBaremLimit              = (float) ($all['cargo']['barem_limit'] ?? 300);
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

        // Bölüm 6: Mutabakat
        $r = $all['reconciliation'] ?? [];
        $this->settingsCommissionMatchTolerance  = (float) ($r['commission_match_tolerance'] ?? 15.00);
        $this->settingsCargoMatchTolerance       = (float) ($r['cargo_match_tolerance'] ?? 20.00);
        $this->settingsInvoiceVatDivisor         = (float) ($r['invoice_vat_divisor'] ?? 1.20);

        // Ödeme
        $this->settingsDelayedPaymentDays = (int) ($all['payment']['delayed_payment_days'] ?? 35);

        // Genel
        $this->settingsMarketplace = (string) ($all['general']['marketplace'] ?? 'Trendyol');

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
        $desiRanges = ['desi_0_2', 'desi_3', 'desi_4', 'desi_5', 'desi_10', 'desi_15', 'desi_20', 'desi_25', 'desi_30'];
        $this->settingsDesiPrices = [];

        foreach ($this->settingsCargoCompanies as $company) {
            $this->settingsDesiPrices[$company] = [];
            foreach ($desiRanges as $range) {
                $val = \App\Models\MpFinancialRule::getRule($range, $company);
                $this->settingsDesiPrices[$company][$range] = $val !== null ? (float) $val : null;
            }
        }
    }

    protected function loadBaremPrices()
    {
        $baremRanges = ['barem_0_150', 'barem_150_300'];
        $this->settingsBaremPrices = [];

        foreach ($this->settingsCargoCompanies as $company) {
            $this->settingsBaremPrices[$company] = [];
            foreach ($baremRanges as $range) {
                $val = \App\Models\MpFinancialRule::getRule($range, $company);
                $this->settingsBaremPrices[$company][$range] = $val !== null ? (float) $val : null;
            }
        }
    }

    // ─── Settings Save Methods ──────────────────────────────────

    public function saveSettings()
    {
        $svc = new MpSettingsService();
        $svc->save([
            'tax' => [
                'stopaj_rate'              => (float) $this->settingsStopajRate,
                'default_product_vat_rate' => (float) $this->settingsDefaultProductVatRate,
                'expense_vat_rate'         => (float) $this->settingsExpenseVatRate,
                'kdv_hesaplama_aktif'      => (bool) $this->settingsKdvHesaplamaAktif,
            ],
            'cargo' => [
                'barem_limit'           => (float) $this->settingsBaremLimit,
                'cargo_companies'       => $this->settingsCargoCompanies,
                'heavy_cargo_penalties' => $this->settingsHeavyCargoPenalties,
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
                'currency'              => 'TRY',
                'default_cargo_company' => $this->settingsDefaultCargoCompany,
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
        // Desi & barem tablosuna da boş satır ekle
        $this->settingsDesiPrices[$name] = array_fill_keys(['desi_0_2','desi_3','desi_4','desi_5','desi_10','desi_15','desi_20','desi_25','desi_30'], null);
        $this->settingsBaremPrices[$name] = ['barem_0_150' => null, 'barem_150_300' => null];
    }

    public function removeCargoCompany(string $company)
    {
        $this->settingsCargoCompanies = array_values(array_filter($this->settingsCargoCompanies, fn($c) => $c !== $company));
        unset($this->settingsDesiPrices[$company]);
        unset($this->settingsBaremPrices[$company]);
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

    public function updatedPerPage()
    {
        $this->resetPage();
    }

    public function selectPeriod($periodId = null)
    {
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
                $this->importStatus = "✅ İşlem sıraya alındı! Excel verileri arkaplanda işleniyor...";
                session()->flash('import_success', "Dosyalar başarıyla sıraya alındı (Queue). Büyük Excel dosyaları arka planda parçalanarak işlenecek.");
            }
        }

        if (!$processed) {
            session()->flash('import_error', 'Lütfen en az bir dosya seçin veya sürükleyin.');
        } else {
            // Faz 5: Dosyalar asenkron (arka planda) islendigi icin denetimi (Audit) simdi calistiramayiz.
            // Kullanici islem bitince manuel calistirmalidir.
        }
        $this->dispatch('import-finished');
    }

    /**
     * Tüm verileri sıfırla (Kullanıcının sistemi temizleyip baştan upload etmesi için)
     */
    public function resetAllData()
    {
        try {
            \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            
            \App\Models\MpAuditLog::truncate();
            \App\Models\MpSettlement::truncate();
            \App\Models\MpInvoice::truncate();
            \App\Models\MpTransaction::truncate();
            \App\Models\MpOrder::truncate();
            \App\Models\MpPeriod::truncate();
            
            \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=1;');

            $this->selectedPeriodId = null;
            $this->selectedYear = date('Y');
            $this->selectedMonth = date('m');
            $this->periods = \App\Models\MpPeriod::where('user_id', Auth::id())->orderBy('year', 'desc')->orderBy('month', 'desc')->get();
            $this->importStatus = "✅ Sistemdeki tüm pazaryeri verileri sıfırlandı.";
            
            session()->flash('import_success', 'Tüm veri tabanı kalıcı olarak temizlendi. Artık dosyalarınızı sıfırdan yükleyebilirsiniz.');
            $this->dispatch('import-finished');

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=1;');
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
            $this->importStatus = "⏳ {$name} dosyası başarıyla kuyruğa atıldı. Arkaplanda işleniyor.";
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
                session()->flash('import_error', 'Bu dönem kilitli! Mutabakatı sağlanan aylara yeni veri aktarılamaz. Önce kilidi açınız.');
                return ['processed' => 0, 'read' => 0];
            }

            // Dosyayı sunucu tarafında temp dizinine kaydet
            $originalName = $file->getClientOriginalName();
            $tempPath = $file->storeAs('imports', uniqid() . '_' . $originalName);

            // Senkron çalıştır — Queue worker olmadan da çalışır
            \App\Jobs\ProcessMarketplaceImport::dispatchSync(
                $this->selectedPeriodId,
                auth()->id() ?? 1,
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
            $this->lastAuditResult = $engine->runAllRules($period);
            $this->importStatus = "🔍 Denetim tamamlandı! "
                . ($this->lastAuditResult['total_errors'] ?? 0) . " kritik, "
                . ($this->lastAuditResult['total_warnings'] ?? 0) . " uyarı bulundu.";
        } catch (\Exception $e) {
            $this->importStatus = "❌ Denetim hatası: " . $e->getMessage();
        }
    }

    // ─── Search (5N1K) ──────────────────────────────────────────

    public function searchOrder()
    {
        if (empty(trim($this->searchQuery))) {
            $this->searchResult = null;
            return;
        }

        // Search in selected period OR selected year if period is "All Year" (0)
        $orders = MpOrder::with('period')
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
                'product_name'     => $order->product_name,
                'barcode'          => $order->barcode,
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
            session()->flash('error_orders', 'ERP ayarlarına (Tab: Ayarlar) girip Webhook URL tanımlamalısınız ve entegrasyonu aktif etmelisiniz.');
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

        $userId = Auth::id() ?? 1;

        // Tüm ürünleri bir kerede çek (N+1 önleme)
        $products = \App\Models\MpProduct::where('user_id', $userId)->get();

        // Barkod → ürün ve stok kodu → ürün haritaları oluştur
        $byBarcode   = $products->keyBy('barcode');
        $byStockCode = $products->filter(fn($p) => $p->stock_code)->keyBy('stock_code');

        // Dönemdeki tüm siparişleri al
        $orders = MpOrder::where('period_id', $this->selectedPeriodId)->get();

        $updated = 0;
        $skipped = 0;
        $notFound = 0;

        foreach ($orders as $order) {
            // Önce barcode ile eşleştir, sonra stock_code ile dene
            $product = $byBarcode->get($order->barcode);
            if (!$product && $order->stock_code) {
                $product = $byStockCode->get($order->stock_code);
            }

            if (!$product) {
                $notFound++;
                continue;
            }

            // COGS 0 / null olan ürünü atla
            if (!$product->cogs || (float)$product->cogs <= 0) {
                $skipped++;
                continue;
            }

            // Güncelle — birim maliyet × sipariş adedi
            $qty = max(1, (int) $order->quantity);
            
            $updateData = [
                'cogs_at_time'          => round((float)$product->cogs * $qty, 2),
                'packaging_cost_at_time' => round((float)($product->packaging_cost ?? 0) * $qty, 2),
                'product_vat_rate'      => $product->vat_rate ?? 10,
            ];

            // Ürün adı veya stok kodu boşsa sync işleminde onu da doldur
            if (empty($order->product_name) && !empty($product->product_name)) {
                $updateData['product_name'] = $product->product_name;
            }
            if (empty($order->stock_code) && !empty($product->stock_code)) {
                $updateData['stock_code'] = $product->stock_code;
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
            '💰 COGS Senkronizasyonu: %d sipariş güncellendi, %d eşleşme bulunamadı, %d ürünün COGS\'u tanımsız.',
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
        if (!$this->selectedPeriodId) {
            return $this->emptyStats();
        }

        try {
            $reportService = new ReportService();
            return $reportService->getDashboardKpis($this->selectedPeriodId);
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
        if (!$this->selectedPeriodId) return [];

        try {
            $unitService = new UnitEconomicsService();
            $period = MpPeriod::findOrFail($this->selectedPeriodId);
            $data = $unitService->profitBySku($period);

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
        if (!$this->selectedPeriodId) return null;

        // Dönemdeki komisyon faturalarının KDV Hariç toplamı
        $invoiceCommission = \App\Models\MpTransaction::where('period_id', $this->selectedPeriodId)
            ->where(function($q) {
                $q->where('transaction_type', 'like', '%Komisyon%')
                  ->orWhere('description', 'like', '%Komisyon%');
            })
            ->sum('debt'); // Borç her zaman kesintidir

        $invoiceCargo = \App\Models\MpTransaction::where('period_id', $this->selectedPeriodId)
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

        // Bizdeki sipariş 'commission_amount' genelde KDV dahil Trendyol kesintisidir.
        $orderCommissionNet = \App\Models\MpOrder::where('period_id', $this->selectedPeriodId)->sum('commission_amount') / $vatDivisor;
        $orderCargoNet = \App\Models\MpOrder::where('period_id', $this->selectedPeriodId)->sum('cargo_amount') / $vatDivisor;

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

        // Eğer belirli bir Period seçilmişse onu kullan, değilse Tüm Yıl modunda çalış
        $isAllYearMode = ($this->selectedMonth == 0);
        $periodIds = [];

        if ($isAllYearMode) {
            $periodIds = MpPeriod::where('user_id', Auth::id())
                ->where('year', $this->selectedYear)
                ->pluck('id')
                ->toArray();
        } elseif ($this->selectedPeriodId) {
            $periodIds = [$this->selectedPeriodId];
        }

        if (!empty($periodIds)) {
            $ordersQuery = MpOrder::whereIn('period_id', $periodIds)
                ->when($this->orderStatusFilter !== 'all', fn($q) =>
                    $q->where('status', $this->orderStatusFilter)
                )
                ->when($this->advancedOrderFilter === 'lost_payments', fn($q) =>
                    $q->delivered()->doesntHave('settlement')
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
            ->with(['period', 'settlement']) // Yıl/Ay ve Vade (Nakit Akışı) gösterimi için
            ->orderByDesc('order_date');

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
