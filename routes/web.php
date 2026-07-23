<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CompensationDownloadController;
use App\Http\Controllers\LiveNotificationController;
use App\Http\Controllers\Marketplace\IdeaSoftOAuthController;
use App\Http\Controllers\MarketplaceOrderDocumentController;
use App\Http\Controllers\SupplyLabelController;
use App\Http\Controllers\TrendyolBoosterCompanionController;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\AdsAccessMiddleware;
use App\Http\Middleware\EnsureReturnFeatureEnabled;
use App\Http\Middleware\EnsureWhatsAppFeatureEnabled;
use App\Livewire\Accounting\AccountingDashboard;
use App\Livewire\Accounting\Assistant;
use App\Livewire\Accounting\AuditLogs;
use App\Livewire\Accounting\CashBank;
use App\Livewire\Accounting\ChartOfAccounts;
use App\Livewire\Accounting\CollectionsPayments;
use App\Livewire\Accounting\EDocuments;
use App\Livewire\Accounting\Journal;
use App\Livewire\Accounting\MarketplaceBridge;
use App\Livewire\Accounting\Parties;
use App\Livewire\Accounting\PartyLedgerWorkspace;
use App\Livewire\Accounting\PilotCenter;
use App\Livewire\Accounting\Pos;
use App\Livewire\Accounting\Products;
use App\Livewire\Accounting\Purchases;
use App\Livewire\Accounting\Reports;
use App\Livewire\Accounting\Sales;
use App\Livewire\Accounting\Stock;
use App\Livewire\Admin\ActivityLogs;
use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\UserManager;
use App\Livewire\Ads\AdImportCenter;
use App\Livewire\Ads\AdsDashboard;
use App\Livewire\Ads\AdsSettings;
use App\Livewire\Ads\AiActionCenter;
use App\Livewire\Ads\InfluencerAdsPage;
use App\Livewire\Ads\ProductAdsCampaignDetail;
use App\Livewire\Ads\ProductAdsPage;
use App\Livewire\Ads\ProfitabilityCenter;
use App\Livewire\Ads\StoreAdsPage;
use App\Livewire\AIChat;
use App\Livewire\ApiDev;
use App\Livewire\BadgePricing;
use App\Livewire\BasketDiscountCampaign;
use App\Livewire\CampaignDecisionCenter;
use App\Livewire\CampaignReports;
use App\Livewire\CargoReports;
use App\Livewire\CrmCustomerLedger;
use App\Livewire\CrmWorkspace;
use App\Livewire\CustomerCare\AdminCenter;
use App\Livewire\CustomerCare\AgentWorkspace;
use App\Livewire\CustomerCare\Analytics;
use App\Livewire\CustomerCare\Api;
use App\Livewire\CustomerCare\Certification;
use App\Livewire\CustomerCare\Commercial;
use App\Livewire\CustomerCare\Compliance;
use App\Livewire\CustomerCare\Experiments;
use App\Livewire\CustomerCare\Governance;
use App\Livewire\CustomerCare\Home;
use App\Livewire\CustomerCare\Inbox;
use App\Livewire\CustomerCare\Integrations;
use App\Livewire\CustomerCare\KnowledgeSuggestions;
use App\Livewire\CustomerCare\Launch;
use App\Livewire\CustomerCare\Onboarding;
use App\Livewire\CustomerCare\OpsCenter;
use App\Livewire\CustomerCare\Organization;
use App\Livewire\CustomerCare\PilotDashboard;
use App\Livewire\CustomerCare\Production;
use App\Livewire\CustomerCare\ProductQuestions;
use App\Livewire\CustomerCare\QualityCenter;
use App\Livewire\CustomerCare\Reconciliation;
use App\Livewire\CustomerCare\Releases;
use App\Livewire\CustomerCare\Reliability;
use App\Livewire\CustomerCare\Security;
use App\Livewire\CustomerCare\Settings;
use App\Livewire\CustomerCare\Success;
use App\Livewire\CustomMotor;
use App\Livewire\CustomMotorWizard;
use App\Livewire\FlashProducts;
use App\Livewire\Marketplace\BuyboxAnalysis;
use App\Livewire\Marketplace\CargoInvoiceReconciliation;
use App\Livewire\Marketplace\ClaimReasonMapping;
use App\Livewire\Marketplace\TrendyolHealthCenter;
use App\Livewire\MarketplaceAccounting;
use App\Livewire\MarketplaceCampaignSimulator;
use App\Livewire\MarketplaceFinance;
use App\Livewire\MarketplaceIntegrations;
use App\Livewire\MarketplaceMatchingCenter;
use App\Livewire\MarketplaceOrders;
use App\Livewire\MarketplaceOverview;
use App\Livewire\MarketplacePricingSimulator;
use App\Livewire\MarketplaceProfitCenter;
use App\Livewire\MarketplaceQuestions;
use App\Livewire\MarketplaceReportDigestSettings;
use App\Livewire\MarketplaceRiskCenter;
use App\Livewire\MarketplaceSettings;
use App\Livewire\MarketplaceSettlementAudit;
use App\Livewire\MpProductsManager;
use App\Livewire\OnboardingWizard;
use App\Livewire\OperationMotor;
use App\Livewire\PlusCommission;
use App\Livewire\ProductionMotor;
use App\Livewire\ProductionPlanner;
use App\Livewire\ProductionRevenue;
use App\Livewire\ProfileManager;
use App\Livewire\ProfileWizard;
use App\Livewire\PublicTrendyolProfitCalculator;
use App\Livewire\RecipeBuilder;
use App\Livewire\RecipeMaterialsManager;
use App\Livewire\ReportHistory;
use App\Livewire\Returns\ReturnWorkspace;
use App\Livewire\SupplyReports;
use App\Livewire\TariffOptimizer;
use App\Livewire\TrendyolBooster;
use App\Livewire\WhatsApp\WhatsAppAccountSettings;
use App\Livewire\WhatsApp\WhatsAppAuditLogs;
use App\Livewire\WhatsApp\WhatsAppAutomationSettings;
use App\Livewire\WhatsApp\WhatsAppCampaignCreate;
use App\Livewire\WhatsApp\WhatsAppCampaignDetail;
use App\Livewire\WhatsApp\WhatsAppCampaigns;
use App\Livewire\WhatsApp\WhatsAppCustomerProfile;
use App\Livewire\WhatsApp\WhatsAppInbox;
use App\Livewire\WhatsApp\WhatsAppOverview;
use App\Livewire\WhatsApp\WhatsAppSegments;
use App\Livewire\WhatsApp\WhatsAppShippingSettings;
use App\Livewire\WhatsApp\WhatsAppTemplateManager;
use App\Models\IntegrationSyncRun;
use App\Models\MarketplaceStore;
use App\Models\ReportFile;
use App\Models\WaTrackingLink;
use App\Services\Marketplace\MarketplaceConnectorManager;
use App\Services\Marketplace\MarketplaceSyncService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Public routes
Route::get('/', function () {
    return redirect()->route('login');
});

Route::view('/privacy/trendyol-booster-companion', 'legal.trendyol-booster-privacy')
    ->name('legal.trendyol-booster-privacy');

Route::get('/tools/trendyol-kar-hesaplama', PublicTrendyolProfitCalculator::class)
    ->name('tools.trendyol-profit-calculator')
    ->middleware('mp.feature:public_trendyol_profit_tool_enabled');

Route::get('/whatsapp/recovery/{token}', function (string $token) {
    $link = WaTrackingLink::where('token_hash', hash('sha256', $token))->first();

    abort_if(! $link || $link->isExpired(), 404);

    $link->update([
        'click_count' => DB::raw('COALESCE(click_count, 0) + 1'),
        'clicked_at' => $link->clicked_at ?? now(),
    ]);

    return redirect()->away($link->destination_url ?: url('/'));
})->name('whatsapp.recovery.track');

// Auth routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

Route::post('/logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');

// Protected routes
Route::middleware('auth')->group(function () {
    Route::prefix('notifications')->name('notifications.')->middleware('mp.feature:notifications_enabled')->group(function () {
        Route::get('/feed', [LiveNotificationController::class, 'feed'])->name('feed');
        Route::get('/stream', [LiveNotificationController::class, 'stream'])->name('stream');
        Route::post('/read-all', [LiveNotificationController::class, 'markAllRead'])->name('read-all');
        Route::post('/preferences', [LiveNotificationController::class, 'preferences'])->name('preferences');
        Route::post('/{notification}/read', [LiveNotificationController::class, 'markRead'])->name('read');
    });

    Route::get('/onboarding', OnboardingWizard::class)->name('mp.onboarding');

    // Dashboard (redirect to mp.orders by default)
    Route::get('/dashboard', function () {
        $preferredRoute = match (true) {
            config('marketplace.features.v2_enabled') && config('marketplace.features.profit_center_enabled') => 'mp.profit-center',
            config('marketplace.features.v2_enabled') && config('marketplace.features.orders_v2_enabled') => 'mp.orders',
            config('marketplace.features.v2_enabled') && config('marketplace.features.overview_enabled') => 'mp.overview',
            default => 'marketplace-accounting',
        };

        return redirect()->route($preferredRoute);
    })->name('dashboard');

    // Production Motor
    Route::get('/production', ProductionMotor::class)
        ->name('production')
        ->middleware('can:accessProduction');

    // Operation Motor
    Route::get('/operation', OperationMotor::class)
        ->name('operation')
        ->middleware('can:accessOperation');

    // Production Revenue
    Route::get('/factory/production-revenue', ProductionRevenue::class)
        ->name('factory.production-revenue')
        ->middleware('can:accessProduction');

    // Custom Motor
    Route::get('/custom-motors', CustomMotor::class)
        ->name('custom-motors')
        ->middleware('can:accessCustomMotor');

    // Report History
    Route::get('/reports', ReportHistory::class)
        ->name('reports')
        ->middleware('can:accessReports');

    // AI Chat
    Route::get('/ai-chat', AIChat::class)->name('ai-chat');

    Route::get('/crm', CrmWorkspace::class)
        ->name('crm.workspace')
        ->middleware('mp.feature:crm_enabled')
        ->middleware('can:accessCrm');

    Route::get('/crm/customer-ledger', CrmCustomerLedger::class)
        ->name('crm.customer-ledger')
        ->middleware('mp.feature:crm_enabled')
        ->middleware('can:accessCrm');

    // ============================================
    // COMING SOON - V0.2 Features
    // ============================================
    Route::get('/cargo-reports', CargoReports::class)->name('cargo-reports');
    Route::get('/supply-reports', SupplyReports::class)->name('supply-reports');
    // Trendyol Kampanya Modülleri
    Route::get('/campaigns', CampaignReports::class)->name('campaigns.index');
    Route::prefix('campaigns')->group(function () {
        Route::get('/decision-center', CampaignDecisionCenter::class)
            ->name('campaigns.decision-center')
            ->middleware('mp.feature:campaign_decision_center_enabled');
        Route::get('/simulator', MarketplaceCampaignSimulator::class)
            ->name('campaigns.simulator');
        Route::get('/product-commission', TariffOptimizer::class)->name('campaigns.product-commission');
        Route::get('/plus-commission', PlusCommission::class)->name('campaigns.plus-commission');
        Route::get('/badge-pricing', BadgePricing::class)->name('campaigns.badge-pricing');
        Route::get('/flash-products', FlashProducts::class)->name('campaigns.flash-products');
        Route::get('/basket-discount', BasketDiscountCampaign::class)->name('campaigns.basket-discount');
    });
    // Backwards compat alias
    Route::get('/tariff-optimizer', fn () => redirect()->route('campaigns.product-commission'))->name('tariff-optimizer');

    // Supply Label Download
    Route::get('/supply-label/{id}', [SupplyLabelController::class, 'download'])->name('supply.label');

    // Compensation Downloads
    Route::prefix('compensation')->group(function () {
        Route::get('/{id}/petition', [CompensationDownloadController::class, 'downloadPetition'])->name('compensation.petition');
        Route::get('/{id}/form', [CompensationDownloadController::class, 'downloadForm'])->name('compensation.form');
        Route::get('/{id}/download-all', [CompensationDownloadController::class, 'downloadAll'])->name('compensation.download-all');
    });
    Route::get('/marketplace-accounting', MarketplaceAccounting::class)
        ->name('marketplace-accounting')
        ->middleware(AdminMiddleware::class);

    Route::get('/accounting/party-ledger', PartyLedgerWorkspace::class)
        ->name('accounting.party-ledger')
        ->middleware('mp.feature:accounting_enabled')
        ->middleware(AdminMiddleware::class);

    Route::get('/accounting', AccountingDashboard::class)
        ->name('accounting.dashboard')
        ->middleware('mp.feature:accounting_enabled')
        ->middleware(AdminMiddleware::class);

    Route::get('/accounting/pilot-center', PilotCenter::class)
        ->name('accounting.pilot-center')
        ->middleware('mp.feature:accounting_enabled')
        ->middleware(AdminMiddleware::class);

    Route::get('/accounting/parties', Parties::class)
        ->name('accounting.parties')
        ->middleware('mp.feature:accounting_enabled')
        ->middleware(AdminMiddleware::class);

    Route::get('/accounting/chart-of-accounts', ChartOfAccounts::class)
        ->name('accounting.chart-of-accounts')
        ->middleware('mp.feature:accounting_enabled')
        ->middleware(AdminMiddleware::class);

    Route::get('/accounting/journal', Journal::class)
        ->name('accounting.journal')
        ->middleware('mp.feature:accounting_enabled')
        ->middleware(AdminMiddleware::class);

    Route::get('/accounting/cash-bank', CashBank::class)
        ->name('accounting.cash-bank')
        ->middleware('mp.feature:accounting_enabled')
        ->middleware(AdminMiddleware::class);

    Route::get('/accounting/stock', Stock::class)
        ->name('accounting.stock')
        ->middleware('mp.feature:accounting_enabled')
        ->middleware(AdminMiddleware::class);

    Route::get('/accounting/products', Products::class)
        ->name('accounting.products')
        ->middleware('mp.feature:accounting_enabled')
        ->middleware(AdminMiddleware::class);

    Route::get('/accounting/sales', Sales::class)
        ->name('accounting.sales')
        ->middleware('mp.feature:accounting_enabled')
        ->middleware(AdminMiddleware::class);

    Route::get('/accounting/purchases', Purchases::class)
        ->name('accounting.purchases')
        ->middleware('mp.feature:accounting_enabled')
        ->middleware(AdminMiddleware::class);

    Route::get('/accounting/collections-payments', CollectionsPayments::class)
        ->name('accounting.collections-payments')
        ->middleware('mp.feature:accounting_enabled')
        ->middleware(AdminMiddleware::class);

    Route::get('/accounting/pos', Pos::class)
        ->name('accounting.pos')
        ->middleware('mp.feature:accounting_enabled')
        ->middleware(AdminMiddleware::class);

    Route::get('/accounting/e-documents', EDocuments::class)
        ->name('accounting.e-documents')
        ->middleware('mp.feature:accounting_enabled')
        ->middleware(AdminMiddleware::class);

    Route::get('/accounting/reports', Reports::class)
        ->name('accounting.reports')
        ->middleware('mp.feature:accounting_enabled')
        ->middleware(AdminMiddleware::class);

    Route::get('/accounting/assistant', Assistant::class)
        ->name('accounting.assistant')
        ->middleware('mp.feature:accounting_enabled')
        ->middleware(AdminMiddleware::class);

    Route::get('/accounting/marketplace-bridge', MarketplaceBridge::class)
        ->name('accounting.marketplace-bridge')
        ->middleware('mp.feature:accounting_enabled')
        ->middleware(AdminMiddleware::class);

    Route::get('/accounting/audit-logs', AuditLogs::class)
        ->name('accounting.audit-logs')
        ->middleware('mp.feature:accounting_enabled')
        ->middleware(AdminMiddleware::class);

    // Legacy aliases kept for backward compatibility.
    // Sorular ekranı yeniden aktif; diğer kaldırılan sayfalar siparişlere yönlenir.
    Route::get('/marketplace-messages', MarketplaceQuestions::class)
        ->name('marketplace-messages')
        ->middleware('mp.feature:questions_enabled')
        ->middleware(AdminMiddleware::class);

    Route::get('/marketplace-claims', fn () => redirect()->route('returns.workspace', ['tab' => 'pazaryeri']))
        ->name('marketplace-claims')
        ->middleware(AdminMiddleware::class);

    Route::get('/marketplace-performance', fn () => redirect()->route('mp.orders'))
        ->name('marketplace-performance')
        ->middleware(AdminMiddleware::class);

    Route::get('/marketplace-listing-push', fn () => redirect()->route('mp.orders'))
        ->name('marketplace-listing-push')
        ->middleware(AdminMiddleware::class);

    Route::get('/marketplace-orders', MarketplaceOrders::class)
        ->name('mp.orders')
        ->middleware('mp.feature:orders_v2_enabled')
        ->middleware(AdminMiddleware::class);

    Route::get('/marketplace-orders/documents/{documentType}', [MarketplaceOrderDocumentController::class, 'download'])
        ->name('mp.orders.documents.download')
        ->middleware('mp.feature:orders_v2_enabled')
        ->middleware(AdminMiddleware::class);

    Route::get('/marketplace-overview', MarketplaceOverview::class)
        ->name('mp.overview')
        ->middleware('mp.feature:overview_enabled')
        ->middleware(AdminMiddleware::class);

    Route::get('/marketplace-profit-center', MarketplaceProfitCenter::class)
        ->name('mp.profit-center')
        ->middleware('mp.feature:profit_center_enabled')
        ->middleware(AdminMiddleware::class);

    Route::get('/marketplace-trendyol-booster', TrendyolBooster::class)
        ->name('mp.trendyol-booster')
        ->middleware([
            'mp.feature:trendyol_booster_enabled',
            AdminMiddleware::class,
            'booster.release',
        ]);

    Route::prefix('/marketplace-trendyol-booster/companion')
        ->name('mp.trendyol-booster.companion.')
        ->middleware([
            'mp.feature:trendyol_booster_enabled',
            AdminMiddleware::class,
            'throttle:booster-companion',
            'booster.release',
            'booster.metric',
        ])
        ->group(function () {
            Route::get('/session', [TrendyolBoosterCompanionController::class, 'session'])->name('session');
            Route::get('/status', [TrendyolBoosterCompanionController::class, 'status'])->name('status');
            Route::post('/preview', [TrendyolBoosterCompanionController::class, 'preview'])->name('preview');
            Route::post('/product-analysis', [TrendyolBoosterCompanionController::class, 'productAnalysis'])->name('product-analysis');
            Route::post('/track', [TrendyolBoosterCompanionController::class, 'track'])->name('track');
            Route::post('/stock-check', [TrendyolBoosterCompanionController::class, 'stockCheck'])->name('stock-check');
            Route::post('/store-scan', [TrendyolBoosterCompanionController::class, 'storeScan'])->name('store-scan');
            Route::post('/bestseller-capture', [TrendyolBoosterCompanionController::class, 'bestsellerCapture'])->name('bestseller-capture');
            Route::post('/opportunity-scan', [TrendyolBoosterCompanionController::class, 'opportunityScan'])->name('opportunity-scan');
            Route::get('/pending-jobs', [TrendyolBoosterCompanionController::class, 'pendingJobs'])->name('pending-jobs');
            Route::post('/market-research', [TrendyolBoosterCompanionController::class, 'marketResearch'])->name('market-research');
            Route::post('/review-scan/start', [TrendyolBoosterCompanionController::class, 'reviewScanStart'])->name('review-scan.start');
            Route::post('/review-scan/ingest', [TrendyolBoosterCompanionController::class, 'reviewScanIngest'])->name('review-scan.ingest');
            Route::get('/review-scan/status/{syncRunId}', [TrendyolBoosterCompanionController::class, 'reviewScanStatus'])->name('review-scan.status');
            Route::post('/review-scan/verify', [TrendyolBoosterCompanionController::class, 'reviewScanVerify'])->name('review-scan.verify');
            Route::get('/pricing-cost-lookup', [TrendyolBoosterCompanionController::class, 'pricingCostLookup'])->name('pricing-cost-lookup');
            Route::post('/update-product-cost', [TrendyolBoosterCompanionController::class, 'updateProductCost'])->name('update-product-cost');
            Route::post('/order-profit-lookup', [TrendyolBoosterCompanionController::class, 'orderProfitLookup'])->name('order-profit-lookup');
        });

    Route::get('/marketplace-pricing-simulator', MarketplacePricingSimulator::class)
        ->name('mp.pricing-simulator')
        ->middleware('mp.feature:pricing_simulator_enabled')
        ->middleware(AdminMiddleware::class);

    Route::get('/marketplace-settlement-audit', MarketplaceSettlementAudit::class)
        ->name('mp.settlement-audit')
        ->middleware('mp.feature:settlement_audit_enabled')
        ->middleware(AdminMiddleware::class);

    Route::get('/marketplace-risk-center', MarketplaceRiskCenter::class)
        ->name('mp.risk-center')
        ->middleware('mp.feature:risk_center_enabled')
        ->middleware(AdminMiddleware::class);

    Route::get('/marketplace-report-digests', MarketplaceReportDigestSettings::class)
        ->name('mp.report-digests')
        ->middleware('mp.feature:report_digest_enabled')
        ->middleware(AdminMiddleware::class);

    Route::get('/marketplace-integrations', MarketplaceIntegrations::class)
        ->name('mp.integrations')
        ->middleware(['auth', 'mp.feature:integrations_enabled']);

    Route::get('/marketplace-integrations/ideasoft/authorize/{store}', [IdeaSoftOAuthController::class, 'redirect'])
        ->name('mp.integrations.ideasoft.authorize')
        ->middleware('mp.feature:integrations_enabled');

    Route::get('/marketplace-integrations/ideasoft/callback', [IdeaSoftOAuthController::class, 'callback'])
        ->name('mp.integrations.ideasoft.callback')
        ->middleware('mp.feature:integrations_enabled');

    Route::get('/marketplace-products', MpProductsManager::class)
        ->name('mp.products')
        ->middleware('mp.feature:products_v2_enabled')
        ->middleware(AdminMiddleware::class);
    Route::post('/marketplace-products/{productId}/refresh-current-status', \App\Http\Controllers\MarketplaceProductCurrentStatusController::class)
        ->name('mp.products.refresh-current-status')
        ->middleware('mp.feature:products_v2_enabled')
        ->middleware(AdminMiddleware::class);

    Route::get('/marketplace-matching-center', MarketplaceMatchingCenter::class)
        ->name('mp.matching')
        ->middleware('mp.feature:matching_center_enabled')
        ->middleware(AdminMiddleware::class);
    Route::post('/marketplace-matching-center/select', [\App\Http\Controllers\MarketplaceMatchingBulkSelectionController::class, 'select'])->name('mp.matching.select');
    Route::post('/marketplace-matching-center/apply', [\App\Http\Controllers\MarketplaceMatchingBulkSelectionController::class, 'apply'])->name('mp.matching.apply');
    Route::post('/marketplace-matching-center/clear-selection', [\App\Http\Controllers\MarketplaceMatchingBulkSelectionController::class, 'clear'])->name('mp.matching.clear-selection');
    Route::post('/marketplace-matching-center/toggle-selection', [\App\Http\Controllers\MarketplaceMatchingBulkSelectionController::class, 'toggle'])->name('mp.matching.toggle-selection');
    Route::post('/marketplace-matching-center/create-product', [\App\Http\Controllers\MarketplaceMatchingBulkSelectionController::class, 'createProduct'])->name('mp.matching.create-product');
    Route::post('/marketplace-matching-center/defer', [\App\Http\Controllers\MarketplaceMatchingBulkSelectionController::class, 'defer'])->name('mp.matching.defer');

    Route::get('/marketplace-finance', MarketplaceFinance::class)
        ->name('mp.finance')
        ->middleware('mp.feature:finance_v2_enabled')
        ->middleware(AdminMiddleware::class);

    Route::get('/marketplace-settings', MarketplaceSettings::class)
        ->name('mp.settings')
        ->middleware(AdminMiddleware::class);

    // Trendyol V2 UI Modules
    Route::get('/marketplace-trendyol-health', TrendyolHealthCenter::class)
        ->name('mp.trendyol.health')
        ->middleware(AdminMiddleware::class);
    Route::get('/marketplace-buybox-analysis', BuyboxAnalysis::class)
        ->name('mp.buybox')
        ->middleware(AdminMiddleware::class);
    Route::get('/marketplace-claim-mapping', ClaimReasonMapping::class)
        ->name('mp.claim.mapping')
        ->middleware(AdminMiddleware::class);
    Route::get('/marketplace-cargo-invoice', CargoInvoiceReconciliation::class)
        ->name('mp.cargo.invoice')
        ->middleware(AdminMiddleware::class);

    // Reçete Modülü
    Route::get('/recipe-materials', RecipeMaterialsManager::class)
        ->name('recipe.materials')
        ->middleware(AdminMiddleware::class);
    Route::get('/recipe-builder/{recipeId?}', RecipeBuilder::class)
        ->name('recipe.builder')
        ->middleware(AdminMiddleware::class);
    Route::get('/production-planner', ProductionPlanner::class)
        ->name('production.planner')
        ->middleware(AdminMiddleware::class);

    if (
        class_exists(ReturnWorkspace::class)
        && class_exists(EnsureReturnFeatureEnabled::class)
    ) {
        Route::get('/returns', ReturnWorkspace::class)
            ->name('returns.workspace')
            ->middleware(EnsureReturnFeatureEnabled::class);

        Route::get('/returns/intake', function () {
            return redirect()->route('returns.workspace', array_merge(request()->query(), ['tab' => 'kabul']));
        })
            ->name('returns.intake')
            ->middleware(EnsureReturnFeatureEnabled::class)
            ->middleware('can:accessReturnsIntake');

        Route::get('/returns/center', function () {
            return redirect()->route('returns.workspace', array_merge(request()->query(), ['tab' => 'havuz']));
        })
            ->name('returns.center')
            ->middleware(EnsureReturnFeatureEnabled::class)
            ->middleware('can:accessReturnsReview');

        Route::get('/returns/marketplace-claims', function () {
            return redirect()->route('returns.workspace', array_merge(request()->query(), ['tab' => 'pazaryeri']));
        })
            ->name('returns.marketplace-claims')
            ->middleware(EnsureReturnFeatureEnabled::class)
            ->middleware('can:accessReturnsReview');

        Route::get('/returns/whatsapp-bridge', function () {
            return redirect()->route('returns.workspace', array_merge(request()->query(), ['tab' => 'whatsapp']));
        })
            ->name('returns.whatsapp-bridge')
            ->middleware(EnsureReturnFeatureEnabled::class)
            ->middleware('can:accessReturnsReview');
    }

    Route::get('/api-dev', ApiDev::class)->name('api-dev');

    if (app()->environment('local')) {
        Route::get('/fix-routes', function () {
            Artisan::call('optimize:clear');

            return 'Optimize cache temizlendi. <a href="/dashboard">Dashboard\'a dön</a>';
        });

        Route::get('/force-migrate', function () {
            try {
                Artisan::call('migrate', ['--force' => true]);

                return 'Migrasyon başarıyla tamamlandı: <br><pre>'.Artisan::output().'</pre>';
            } catch (Exception $e) {
                return 'Hata: '.$e->getMessage();
            }
        });
    }

    // Profile Management
    Route::get('/profiles', ProfileManager::class)
        ->name('profiles')
        ->middleware('can:admin');

    // Profile Wizard (AI-powered profile creation)
    Route::get('/profiles/create', ProfileWizard::class)
        ->name('profile.wizard')
        ->middleware('can:admin');

    // Custom Profile Wizard
    Route::get('/custom-motors/create', CustomMotorWizard::class)
        ->name('custom-motors.create')
        ->middleware('can:accessCustomMotor');

    // File downloads - doğrudan binary response
    Route::get('/download/{reportFile}', function (ReportFile $reportFile) {
        $user = auth()->user();
        if (! $user->isAdmin()) {
            if (! $reportFile->report || $reportFile->report->user_id !== $user->id) {
                abort(403, 'Bu dosyaya erişim yetkiniz yok.');
            }
        }

        $fullPath = Storage::disk('local')->path($reportFile->file_path);

        if (! file_exists($fullPath)) {
            abort(404, 'Dosya bulunamadı');
        }

        // BinaryFileResponse kullan - output buffering sorununu önler
        return response()->download($fullPath, $reportFile->filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$reportFile->filename.'"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    })->name('download');

    // Report History alias
    Route::get('/report-history', ReportHistory::class)->name('report-history');

    // ============================================
    // REKLAM ZEKÂSI MODÜLÜ
    // ============================================
    Route::prefix('ads')->middleware(AdsAccessMiddleware::class)->group(function () {
        Route::get('/', AdsDashboard::class)->name('ads.dashboard');
        Route::get('/import', AdImportCenter::class)->name('ads.import');
        Route::get('/product-ads', ProductAdsPage::class)->name('ads.product-ads');
        Route::get('/product-ads/{campaignId}', ProductAdsCampaignDetail::class)->name('ads.product-ads.detail');
        Route::get('/store-ads', StoreAdsPage::class)->name('ads.store-ads');
        Route::get('/influencer-ads', InfluencerAdsPage::class)->name('ads.influencer-ads');
        Route::get('/profitability', ProfitabilityCenter::class)->name('ads.profitability');
        Route::get('/action-center', AiActionCenter::class)->name('ads.action-center');
        Route::get('/settings', AdsSettings::class)->name('ads.settings');
    });

    // ============================================
    // ADMIN ROUTES
    // ============================================
    // ============================================
    // WHATSAPP MODÜLÜ
    // ============================================
    Route::middleware(EnsureWhatsAppFeatureEnabled::class)
        ->prefix('whatsapp')->name('whatsapp.')->group(function () {
            Route::get('/', WhatsAppOverview::class)->name('overview');
            Route::get('/account', WhatsAppAccountSettings::class)->name('account');
            Route::get('/templates', WhatsAppTemplateManager::class)->name('templates');
            Route::get('/shipping', WhatsAppShippingSettings::class)->name('shipping');
            Route::get('/inbox', WhatsAppInbox::class)->name('inbox');
            Route::get('/campaigns', WhatsAppCampaigns::class)->name('campaigns');
            Route::get('/campaigns/create', WhatsAppCampaignCreate::class)->name('campaign-create');
            Route::get('/campaigns/{id}', WhatsAppCampaignDetail::class)->name('campaign-detail');
            Route::get('/segments', WhatsAppSegments::class)->name('segments');
            Route::get('/customer/{id?}', WhatsAppCustomerProfile::class)->name('customer-profile');
            Route::get('/audit-logs', WhatsAppAuditLogs::class)->name('audit-logs');
            Route::get('/automation', WhatsAppAutomationSettings::class)->name('automation');
        });

    Route::middleware(AdminMiddleware::class)->prefix('admin')->group(function () {
        Route::get('/', Dashboard::class)->name('admin.dashboard');
        Route::get('/users', UserManager::class)->name('admin.users');
        Route::get('/logs', ActivityLogs::class)->name('admin.logs');
    });

    Route::get('/customer-care', Home::class)
        ->name('customer-care.home')
        ->middleware('customer-care.feature:inbox_enabled');

    Route::get('/customer-care/inbox', Inbox::class)
        ->name('customer-care.inbox')
        ->middleware('customer-care.feature:inbox_enabled');

    Route::get('/customer-care/pilot', PilotDashboard::class)
        ->name('customer-care.pilot')
        ->middleware('customer-care.feature:pilot_dashboard_enabled');

    Route::get('/customer-care/suggestions', KnowledgeSuggestions::class)
        ->name('customer-care.suggestions')
        ->middleware('customer-care.feature:knowledge_enabled');

    Route::get('/customer-care/product-questions', ProductQuestions::class)
        ->name('customer-care.product-questions')
        ->middleware('customer-care.feature:knowledge_enabled');

    Route::get('/customer-care/analytics', Analytics::class)
        ->name('customer-care.analytics')
        ->middleware('customer-care.feature:analytics_enabled');

    Route::get('/customer-care/settings', Settings::class)
        ->name('customer-care.settings')
        ->middleware('customer-care.feature:settings_enabled');

    Route::get('/customer-care/onboarding', Onboarding::class)
        ->name('customer-care.onboarding')
        ->middleware('customer-care.feature:onboarding_enabled');

    Route::get('/customer-care/admin', AdminCenter::class)
        ->name('customer-care.admin')
        ->middleware('customer-care.feature:admin_center_enabled');

    Route::get('/customer-care/quality', QualityCenter::class)
        ->name('customer-care.quality')
        ->middleware('customer-care.feature:quality_center_enabled');

    Route::get('/customer-care/integrations', Integrations::class)
        ->name('customer-care.integrations')
        ->middleware('customer-care.feature:integration_hub_enabled');

    Route::get('/customer-care/ops', OpsCenter::class)
        ->name('customer-care.ops')
        ->middleware('customer-care.feature:ops_center_enabled');

    Route::get('/customer-care/governance', Governance::class)
        ->name('customer-care.governance')
        ->middleware('customer-care.feature:governance_enabled');

    Route::get('/customer-care/compliance', Compliance::class)
        ->name('customer-care.compliance')
        ->middleware('customer-care.feature:compliance_enabled');

    Route::get('/customer-care/reliability', Reliability::class)
        ->name('customer-care.reliability')
        ->middleware('customer-care.feature:reliability_enabled');

    Route::get('/customer-care/launch', Launch::class)
        ->name('customer-care.launch')
        ->middleware('customer-care.feature:launch_center_enabled');

    Route::get('/customer-care/reconciliation', Reconciliation::class)
        ->name('customer-care.reconciliation')
        ->middleware('customer-care.feature:reconciliation_enabled');

    Route::get('/customer-care/releases', Releases::class)
        ->name('customer-care.releases')
        ->middleware('customer-care.feature:release_center_enabled');

    // Waves AN / AO / AP
    Route::get('/customer-care/success', Success::class)
        ->name('customer-care.success')
        ->middleware('customer-care.feature:success_center_enabled');

    Route::get('/customer-care/experiments', Experiments::class)
        ->name('customer-care.experiments')
        ->middleware('customer-care.feature:experiments_enabled');

    Route::get('/customer-care/security', Security::class)
        ->name('customer-care.security')
        ->middleware('customer-care.feature:security_center_enabled');

    // Waves AQ / AR / AS
    Route::get('/customer-care/organization', Organization::class)
        ->name('customer-care.organization')
        ->middleware('customer-care.feature:org_center_enabled');

    Route::get('/customer-care/api', Api::class)
        ->name('customer-care.api')
        ->middleware('customer-care.feature:enterprise_api_enabled');

    Route::get('/customer-care/commercial', Commercial::class)
        ->name('customer-care.commercial')
        ->middleware('customer-care.feature:commercial_center_enabled');

    // Waves AT / AU / AV
    Route::get('/customer-care/agent-workspace', AgentWorkspace::class)
        ->name('customer-care.agent-workspace')
        ->middleware('customer-care.feature:agent_workspace_enabled');

    Route::get('/customer-care/certification', Certification::class)
        ->name('customer-care.certification')
        ->middleware('customer-care.feature:connector_certification_enabled');

    Route::get('/customer-care/production', Production::class)
        ->name('customer-care.production')
        ->middleware('customer-care.feature:production_center_enabled');
});

// ============================================
// DEBUG ROUTES - ONLY LOCAL ENVIRONMENT
// ============================================
if (app()->environment('local')) {
    // Excel test route - sorun tespiti için
    Route::get('/test-excel', function () {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Test');
        $sheet->setCellValue('A1', 'Merhaba');
        $sheet->setCellValue('B1', 'Dünya');
        $sheet->setCellValue('A2', 'Test');
        $sheet->setCellValue('B2', '123');

        $tempFile = storage_path('app/test-excel.xlsx');
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        return response()->download($tempFile, 'test-dosya.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    });

    // Son rapor dosyasını test et
    Route::get('/test-last-file', function () {
        $lastFile = ReportFile::latest()->first();
        if (! $lastFile) {
            return 'Dosya bulunamadı';
        }

        $fullPath = Storage::disk('local')->path($lastFile->file_path);

        if (! file_exists($fullPath)) {
            return 'Dosya mevcut değil: '.$lastFile->file_path;
        }

        try {
            $spreadsheet = IOFactory::load($fullPath);
            $info = [
                'path' => $fullPath,
                'size' => filesize($fullPath),
                'sheet_count' => $spreadsheet->getSheetCount(),
                'sheets' => [],
            ];

            foreach ($spreadsheet->getSheetNames() as $name) {
                $sheet = $spreadsheet->getSheetByName($name);
                $info['sheets'][] = [
                    'name' => $name,
                    'rows' => $sheet->getHighestRow(),
                    'cols' => $sheet->getHighestColumn(),
                ];
            }

            return response()->json($info);

        } catch (Exception $e) {
            return 'Hata: '.$e->getMessage();
        }
    });

    // Kuyrukta bekleyen tüm entegrasyon işlerini anında senkronize et
    Route::get('/force-sync', function () {
        $runs = IntegrationSyncRun::where('status', 'queued')
            ->orderBy('id', 'desc')
            ->take(10) // Sadece en yeni 10 işi al, eski takılıları çekme
            ->get();

        if ($runs->isEmpty()) {
            return 'Kuyrukta bekleyen (queued) yeni senkronizasyon işi yok.';
        }

        $count = 0;
        $errors = [];
        $syncService = app(MarketplaceSyncService::class);

        foreach ($runs as $run) {
            try {
                $syncService->run($run->id);
                $count++;
            } catch (Exception $e) {
                // Eski run'u fail yapıp kurtul
                $run->status = 'failed';
                $run->save();
                $errors[] = "Run #{$run->id}: ".$e->getMessage();
            }
        }

        $output = "{$count} adet senkronizasyon kuyruğu başarıyla işlendi! Siparişler ekrana düşmüş olmalı.<br><br>";
        if (! empty($errors)) {
            $output .= '<b>Alınan Hatalar (Bu hatalar eski veya geçersiz bağlantılara ait olabilir ve atlanmıştır):</b><br>'.implode('<br>', $errors);
        }

        return $output;
    });

    Route::get('/debug-runs', function () {
        $runs = IntegrationSyncRun::where('status', 'completed')
            ->orderBy('id', 'desc')
            ->take(5)
            ->get();

        $output = '';
        foreach ($runs as $run) {
            $notes = json_encode($run->notes_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $output .= "Run #{$run->id} (Store {$run->store_id} - {$run->sync_type}): <pre>{$notes}</pre><hr>";
        }

        return $output;
    });

    Route::get('/woo-reset', function () {
        $storeIds = MarketplaceStore::where('marketplace', 'woocommerce')->pluck('id');
        if ($storeIds->isEmpty()) {
            return 'WooCommerce mağazası bulunamadı.';
        }

        $runs = IntegrationSyncRun::whereIn('store_id', $storeIds)
            ->where('sync_type', 'orders')
            ->where('status', 'completed')
            ->update(['status' => 'failed', 'notes_json' => []]);

        return "Tüm WooCommerce mağazalarının geçmiş kilitleri sıfırlandı ({$runs} adet işlem iptal edildi). Şimdi 'Siparişleri Çek' butonuna bastığınızda tüm son 7 günü baştan tarayacaktır!";
    });

    Route::get('/woo-test', function () {
        $store = MarketplaceStore::where('marketplace', 'woocommerce')->orderBy('id', 'desc')->first();
        if (! $store) {
            return 'Store not found';
        }

        $startDate = CarbonImmutable::now()->subDays(7);
        $connector = app(MarketplaceConnectorManager::class)->resolve('woocommerce');

        try {
            $options = ['start_date' => $startDate->toIso8601String(), 'end_date' => now()->toIso8601String(), 'page_size' => 100];
            $response = $connector->pullOrders($store, $options);

            $results = [];
            $items = $response['items'] ?? [];
            foreach (array_slice($items, 0, 10) as $order) {
                // Return simple structure
                $results[] = [
                    'external_id' => $order['order']['external_order_id'] ?? null,
                    'status' => $order['order']['store_status_code'] ?? null,
                    'date' => $order['order']['ordered_at'] ?? null,
                    'customer' => $order['customer']['full_name'] ?? null,
                ];
            }

            return response()->json([
                'success' => true,
                'count' => count($items),
                'top_10' => $results,
                'meta' => $response['meta'] ?? null,
            ]);
        } catch (Exception $e) {
            return $e->getMessage();
        }
    });
}
