<?php

namespace App\Livewire;

use App\Services\Marketplace\MarketplaceProviderRegistry;
use App\Services\MpSettingsService;
use App\Services\RecipeProductCostSyncService;
use Livewire\Component;

class MarketplaceSettings extends Component
{
    public bool $helpTipsEnabled = true;

    public string $defaultProfitMarketplace = 'average';

    public $woocommerceCommissionRate = 0;

    public bool $recipeCostSyncEnabled = false;

    public int $ordersPerPage = 20;

    public int $productsPerPage = 25;

    public int $ordersDefaultDateRangeDays = 0;

    public int $financeDefaultDateRangeDays = 30;

    public int $autoRecommendThreshold = 100;

    public array $matchingWeights = [
        'barcode_exact' => 120,
        'stock_code_exact' => 100,
        'model_exact' => 90,
        'model_family' => 70,
        'brand_exact' => 12,
        'category_exact' => 8,
        'title_token' => 6,
        'title_max' => 30,
    ];

    public string $matchingStopWords = '';

    public int $candidateSearchLimit = 12;

    public int $candidateResultLimit = 8;

    public bool $autoRunMatchingOnSync = true;

    public array $labelPrintSettings = [];

    public array $dispatchPrintSettings = [];

    public array $companyForm = [
        'name' => '',
        'phone' => '',
        'tax_number' => '',
        'address' => '',
    ];

    public function mount(): void
    {
        $this->loadSettings();
    }

    public function loadSettings(): void
    {
        $settings = app(MpSettingsService::class);

        $this->helpTipsEnabled = $settings->helpTipsEnabled();
        $this->defaultProfitMarketplace = $settings->getProductProfitDefaultMarketplace();
        $this->woocommerceCommissionRate = $settings->getProductProfitWooCommerceCommissionRate();
        $this->recipeCostSyncEnabled = $settings->recipeCostSyncEnabled();
        $this->ordersPerPage = $settings->getOrdersPerPage();
        $this->productsPerPage = $settings->getProductsPerPage();
        $this->ordersDefaultDateRangeDays = $settings->getOrdersDefaultDateRangeDays();
        $this->financeDefaultDateRangeDays = $settings->getFinanceDefaultDateRangeDays();
        $this->autoRecommendThreshold = $settings->getAutoRecommendThreshold();
        $this->matchingWeights = $settings->getMatchingWeights();
        $this->matchingStopWords = implode(', ', $settings->getMatchingStopWords());
        $this->candidateSearchLimit = $settings->getMatchingCandidateSearchLimit();
        $this->candidateResultLimit = $settings->getMatchingCandidateResultLimit();
        $this->autoRunMatchingOnSync = $settings->getAutoRunMatchingOnSync();
        $this->labelPrintSettings = $settings->getArray('print.label', $this->defaultLabelPrintSettings());
        $this->dispatchPrintSettings = $settings->getArray('print.dispatch', $this->defaultDispatchPrintSettings());
        $this->companyForm = [
            'name' => (string) $settings->get('company.name', ''),
            'phone' => (string) $settings->get('company.phone', ''),
            'tax_number' => (string) $settings->get('company.tax_number', ''),
            'address' => (string) $settings->get('company.address', ''),
        ];
    }

    public function saveSettings(): void
    {
        $validated = $this->validate([
            'helpTipsEnabled' => ['boolean'],
            'defaultProfitMarketplace' => ['required', 'string', 'in:'.implode(',', array_keys($this->productProfitMarketplaceOptions()))],
            'woocommerceCommissionRate' => ['required', 'numeric', 'min:0', 'max:100'],
            'recipeCostSyncEnabled' => ['boolean'],
            'ordersPerPage' => ['required', 'integer', 'in:10,20,25,50,100'],
            'productsPerPage' => ['required', 'integer', 'in:10,20,25,50,100'],
            'ordersDefaultDateRangeDays' => ['required', 'integer', 'in:0,7,30,60,90,180,365'],
            'financeDefaultDateRangeDays' => ['required', 'integer', 'in:0,7,30,60,90,180,365'],
            'autoRecommendThreshold' => ['required', 'integer', 'min:1', 'max:500'],
            'matchingWeights.barcode_exact' => ['required', 'integer', 'min:0', 'max:500'],
            'matchingWeights.stock_code_exact' => ['required', 'integer', 'min:0', 'max:500'],
            'matchingWeights.model_exact' => ['required', 'integer', 'min:0', 'max:500'],
            'matchingWeights.model_family' => ['required', 'integer', 'min:0', 'max:500'],
            'matchingWeights.brand_exact' => ['required', 'integer', 'min:0', 'max:500'],
            'matchingWeights.category_exact' => ['required', 'integer', 'min:0', 'max:500'],
            'matchingWeights.title_token' => ['required', 'integer', 'min:0', 'max:500'],
            'matchingWeights.title_max' => ['required', 'integer', 'min:0', 'max:500'],
            'matchingStopWords' => ['required', 'string', 'max:5000'],
            'candidateSearchLimit' => ['required', 'integer', 'min:1', 'max:100'],
            'candidateResultLimit' => ['required', 'integer', 'min:1', 'max:50'],
            'autoRunMatchingOnSync' => ['boolean'],
        ]);

        $normalizedWeights = [];
        $defaults = app(MpSettingsService::class)->getDefaults()['matching']['weights'];
        foreach ($defaults as $key => $default) {
            $normalizedWeights[$key] = app(MpSettingsService::class)->normalizeMatchingWeight(
                (int) ($validated['matchingWeights'][$key] ?? $default),
                $default
            );
        }

        $stopWordsRaw = preg_split('/[,\n]+/', $validated['matchingStopWords']) ?? [];
        $stopWordsDefaults = app(MpSettingsService::class)->getDefaults()['matching']['stop_words'];
        $normalizedStopWords = app(MpSettingsService::class)->normalizeStopWords($stopWordsRaw, $stopWordsDefaults);

        app(MpSettingsService::class)->setMany([
            'ui.help_tips_enabled' => (bool) $validated['helpTipsEnabled'],
            'marketplace_products.profit.default_marketplace' => $validated['defaultProfitMarketplace'],
            'marketplace_products.profit.woocommerce_commission_rate' => round((float) $validated['woocommerceCommissionRate'], 2),
            'marketplace_products.recipe_cost_sync_enabled' => (bool) $validated['recipeCostSyncEnabled'],
            'ui.orders_per_page' => (int) $validated['ordersPerPage'],
            'ui.products_per_page' => (int) $validated['productsPerPage'],
            'ui.orders_default_date_range_days' => (int) $validated['ordersDefaultDateRangeDays'],
            'ui.finance_default_date_range_days' => (int) $validated['financeDefaultDateRangeDays'],
            'matching.auto_recommend_threshold' => (int) $validated['autoRecommendThreshold'],
            'matching.weights' => $normalizedWeights,
            'matching.stop_words' => $normalizedStopWords,
            'matching.candidate_search_limit' => (int) $validated['candidateSearchLimit'],
            'matching.candidate_result_limit' => min((int) $validated['candidateResultLimit'], (int) $validated['candidateSearchLimit']),
            'matching.auto_run_on_sync' => (bool) $validated['autoRunMatchingOnSync'],
        ]);

        $syncSummary = null;
        if ((bool) $validated['recipeCostSyncEnabled']) {
            $syncSummary = app(RecipeProductCostSyncService::class)->syncAllForUser((int) auth()->id(), true);
        }

        $this->loadSettings();

        $message = 'Genel ayarlar kaydedildi.';
        if ($syncSummary) {
            $message .= " Reçete maliyeti {$syncSummary['matched_products']} stok kartına uygulandı.";
        }

        session()->flash('settings_success', $message);
    }

    public function saveDocumentSettings(): void
    {
        $validated = $this->validate([
            'labelPrintSettings.template' => ['required', 'in:courier,compact,minimal'],
            'labelPrintSettings.paper' => ['required', 'in:thermal_100x150,a6,a6_landscape,a5'],
            'labelPrintSettings.barcode_type' => ['required', 'in:code128'],
            'labelPrintSettings.barcode_height' => ['required', 'integer', 'min:32', 'max:96'],
            'labelPrintSettings.show_sender' => ['boolean'],
            'labelPrintSettings.show_customer_phone' => ['boolean'],
            'labelPrintSettings.show_items' => ['boolean'],
            'labelPrintSettings.show_marketplace' => ['boolean'],
            'labelPrintSettings.show_tracking_number' => ['boolean'],
            'labelPrintSettings.show_barcode_text' => ['boolean'],
            'labelPrintSettings.show_item_summary' => ['boolean'],
            'labelPrintSettings.footer_note' => ['nullable', 'string', 'max:240'],
            'dispatchPrintSettings.template' => ['required', 'in:classic,compact,warehouse'],
            'dispatchPrintSettings.paper' => ['required', 'in:a4,a4_landscape,a5,a5_landscape'],
            'dispatchPrintSettings.barcode_type' => ['required', 'in:code128'],
            'dispatchPrintSettings.barcode_height' => ['required', 'integer', 'min:32', 'max:96'],
            'dispatchPrintSettings.show_sender' => ['boolean'],
            'dispatchPrintSettings.show_customer_phone' => ['boolean'],
            'dispatchPrintSettings.show_billing_info' => ['boolean'],
            'dispatchPrintSettings.show_items' => ['boolean'],
            'dispatchPrintSettings.show_barcode' => ['boolean'],
            'dispatchPrintSettings.show_barcode_text' => ['boolean'],
            'dispatchPrintSettings.show_marketplace' => ['boolean'],
            'dispatchPrintSettings.show_signature_area' => ['boolean'],
            'dispatchPrintSettings.footer_note' => ['nullable', 'string', 'max:240'],
            'companyForm.name' => ['nullable', 'string', 'max:150'],
            'companyForm.phone' => ['nullable', 'string', 'max:32'],
            'companyForm.tax_number' => ['nullable', 'string', 'max:32'],
            'companyForm.address' => ['nullable', 'string', 'max:500'],
        ]);

        app(MpSettingsService::class)->setMany([
            'print.label' => $this->normalizeDocumentSettings($validated['labelPrintSettings']),
            'print.dispatch' => $this->normalizeDocumentSettings($validated['dispatchPrintSettings']),
            'company.name' => trim((string) ($validated['companyForm']['name'] ?? '')),
            'company.phone' => trim((string) ($validated['companyForm']['phone'] ?? '')),
            'company.tax_number' => trim((string) ($validated['companyForm']['tax_number'] ?? '')),
            'company.address' => trim((string) ($validated['companyForm']['address'] ?? '')),
        ]);

        $this->loadSettings();

        session()->flash('document_settings_success', 'Kargo barkod ve çıktı ayarları kaydedildi.');
    }

    public function resetUiSettings(): void
    {
        app(MpSettingsService::class)->setMany([
            'ui.help_tips_enabled' => true,
            'marketplace_products.profit.default_marketplace' => 'average',
            'marketplace_products.profit.woocommerce_commission_rate' => 0.00,
            'marketplace_products.recipe_cost_sync_enabled' => false,
            'ui.orders_per_page' => 20,
            'ui.products_per_page' => 25,
            'ui.orders_default_date_range_days' => 0,
            'ui.finance_default_date_range_days' => 30,
            'matching.auto_recommend_threshold' => 100,
            'matching.weights' => [
                'barcode_exact' => 120,
                'stock_code_exact' => 100,
                'model_exact' => 90,
                'model_family' => 70,
                'brand_exact' => 12,
                'category_exact' => 8,
                'title_token' => 6,
                'title_max' => 30,
            ],
            'matching.stop_words' => [
                'adet', 'one', 'size', 'olan', 'icin', 'için', 'ile', 've', 'bir', 'iki',
                'tak', 'takim', 'takimi', 'takımı', 'urun', 'ürün', 'seti',
            ],
            'matching.candidate_search_limit' => 12,
            'matching.candidate_result_limit' => 8,
            'matching.auto_run_on_sync' => true,
        ]);

        $this->helpTipsEnabled = true;
        $this->defaultProfitMarketplace = 'average';
        $this->woocommerceCommissionRate = 0.00;
        $this->recipeCostSyncEnabled = false;
        $this->ordersPerPage = 20;
        $this->productsPerPage = 25;
        $this->ordersDefaultDateRangeDays = 0;
        $this->financeDefaultDateRangeDays = 30;
        $this->autoRecommendThreshold = 100;
        $this->matchingWeights = [
            'barcode_exact' => 120,
            'stock_code_exact' => 100,
            'model_exact' => 90,
            'model_family' => 70,
            'brand_exact' => 12,
            'category_exact' => 8,
            'title_token' => 6,
            'title_max' => 30,
        ];
        $this->matchingStopWords = implode(', ', [
            'adet', 'one', 'size', 'olan', 'icin', 'için', 'ile', 've', 'bir', 'iki',
            'tak', 'takim', 'takimi', 'takımı', 'urun', 'ürün', 'seti',
        ]);
        $this->candidateSearchLimit = 12;
        $this->candidateResultLimit = 8;
        $this->autoRunMatchingOnSync = true;

        session()->flash('settings_success', 'Genel ayarlar varsayılan değerlere döndürüldü.');
    }

    public function resetDocumentSettings(): void
    {
        $settings = app(MpSettingsService::class);
        $defaults = $settings->getDefaults();

        $settings->setMany([
            'print.label' => $defaults['print']['label'] ?? $this->defaultLabelPrintSettings(),
            'print.dispatch' => $defaults['print']['dispatch'] ?? $this->defaultDispatchPrintSettings(),
            'company.name' => '',
            'company.phone' => '',
            'company.tax_number' => '',
            'company.address' => '',
        ]);

        $this->loadSettings();

        session()->flash('document_settings_success', 'Çıktı ayarları varsayılan değerlere döndürüldü.');
    }

    public function render()
    {
        return view('livewire.marketplace-settings', [
            'helpTipCoverage' => [
                'Özet',
                'Entegrasyonlar',
                'Siparişler',
                'Ürünler',
                'Eşleştirme',
                'Finans',
                'Muhasebe',
            ],
            'labelTemplateOptions' => [
                'courier' => 'Kurye standart',
                'compact' => 'Kompakt operasyon',
                'minimal' => 'Minimal termal',
            ],
            'labelPaperOptions' => [
                'thermal_100x150' => 'Termal 100x150',
                'a6' => 'A6 dikey',
                'a6_landscape' => 'A6 yatay',
                'a5' => 'A5 dikey',
            ],
            'dispatchTemplateOptions' => [
                'classic' => 'Klasik irsaliye',
                'compact' => 'Kompakt sevk',
                'warehouse' => 'Depo operasyon',
            ],
            'dispatchPaperOptions' => [
                'a4' => 'A4 dikey',
                'a4_landscape' => 'A4 yatay',
                'a5' => 'A5 dikey',
                'a5_landscape' => 'A5 yatay',
            ],
            'productProfitMarketplaceOptions' => $this->productProfitMarketplaceOptions(),
        ])->layout('layouts.app', ['title' => 'Pazaryeri Ayarları']);
    }

    protected function productProfitMarketplaceOptions(): array
    {
        return [
            'average' => 'Mağaza ortalaması',
            'worst' => 'En düşük kâr senaryosu',
        ] + MarketplaceProviderRegistry::options();
    }

    protected function normalizeDocumentSettings(array $settings): array
    {
        $normalized = $settings;

        foreach ($normalized as $key => $value) {
            if (is_bool($value)) {
                $normalized[$key] = (bool) $value;

                continue;
            }

            if ($key === 'barcode_height') {
                $normalized[$key] = (int) $value;

                continue;
            }

            $normalized[$key] = is_string($value) ? trim($value) : $value;
        }

        return $normalized;
    }

    protected function defaultLabelPrintSettings(): array
    {
        return [
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
        ];
    }

    protected function defaultDispatchPrintSettings(): array
    {
        return [
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
        ];
    }
}
