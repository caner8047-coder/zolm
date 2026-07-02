<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\LiveNotificationController;
use App\Http\Controllers\MarketplaceOrderDocumentController;
use App\Http\Controllers\TrendyolBoosterCompanionController;
use App\Livewire\AIChat;
use App\Livewire\CustomMotor;
use App\Livewire\CustomMotorWizard;
use App\Livewire\ProductionMotor;
use App\Livewire\OperationMotor;
use App\Livewire\ProfileManager;
use App\Livewire\ProfileWizard;
use App\Livewire\ProductionRevenue;
use App\Livewire\ReportHistory;
use Illuminate\Support\Facades\Route;

// Public routes
Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/tools/trendyol-kar-hesaplama', \App\Livewire\PublicTrendyolProfitCalculator::class)
    ->name('tools.trendyol-profit-calculator')
    ->middleware('mp.feature:public_trendyol_profit_tool_enabled');

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

    Route::get('/onboarding', \App\Livewire\OnboardingWizard::class)->name('mp.onboarding');

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

    Route::get('/crm', \App\Livewire\CrmWorkspace::class)
        ->name('crm.workspace')
        ->middleware('mp.feature:crm_enabled')
        ->middleware('can:accessCrm');

    Route::get('/crm/customer-ledger', \App\Livewire\CrmCustomerLedger::class)
        ->name('crm.customer-ledger')
        ->middleware('mp.feature:crm_enabled')
        ->middleware('can:accessCrm');

    // ============================================
    // COMING SOON - V0.2 Features
    // ============================================
    Route::get('/cargo-reports', \App\Livewire\CargoReports::class)->name('cargo-reports');
    Route::get('/supply-reports', \App\Livewire\SupplyReports::class)->name('supply-reports');
    // Trendyol Kampanya Modülleri
    Route::get('/campaigns', \App\Livewire\CampaignReports::class)->name('campaigns.index');
    Route::prefix('campaigns')->group(function () {
        Route::get('/decision-center', \App\Livewire\CampaignDecisionCenter::class)
            ->name('campaigns.decision-center')
            ->middleware('mp.feature:campaign_decision_center_enabled');
        Route::get('/simulator', \App\Livewire\MarketplaceCampaignSimulator::class)
            ->name('campaigns.simulator');
        Route::get('/product-commission', \App\Livewire\TariffOptimizer::class)->name('campaigns.product-commission');
        Route::get('/plus-commission', \App\Livewire\PlusCommission::class)->name('campaigns.plus-commission');
        Route::get('/badge-pricing', \App\Livewire\BadgePricing::class)->name('campaigns.badge-pricing');
        Route::get('/flash-products', \App\Livewire\FlashProducts::class)->name('campaigns.flash-products');
        Route::get('/basket-discount', \App\Livewire\BasketDiscountCampaign::class)->name('campaigns.basket-discount');
    });
    // Backwards compat alias
    Route::get('/tariff-optimizer', fn() => redirect()->route('campaigns.product-commission'))->name('tariff-optimizer');
    
    // Supply Label Download
    Route::get('/supply-label/{id}', [\App\Http\Controllers\SupplyLabelController::class, 'download'])->name('supply.label');

    // Compensation Downloads
    Route::prefix('compensation')->group(function () {
        Route::get('/{id}/petition', [\App\Http\Controllers\CompensationDownloadController::class, 'downloadPetition'])->name('compensation.petition');
        Route::get('/{id}/form', [\App\Http\Controllers\CompensationDownloadController::class, 'downloadForm'])->name('compensation.form');
        Route::get('/{id}/download-all', [\App\Http\Controllers\CompensationDownloadController::class, 'downloadAll'])->name('compensation.download-all');
    });
    Route::get('/marketplace-accounting', \App\Livewire\MarketplaceAccounting::class)
        ->name('marketplace-accounting')
        ->middleware(\App\Http\Middleware\AdminMiddleware::class);

    // Legacy aliases kept for backward compatibility.
    // Sorular ekranı yeniden aktif; diğer kaldırılan sayfalar siparişlere yönlenir.
    Route::get('/marketplace-messages', \App\Livewire\MarketplaceQuestions::class)
        ->name('marketplace-messages')
        ->middleware('mp.feature:questions_enabled')
        ->middleware(\App\Http\Middleware\AdminMiddleware::class);

    Route::get('/marketplace-claims', fn () => redirect()->route('returns.workspace', ['tab' => 'pazaryeri']))
        ->name('marketplace-claims')
        ->middleware(\App\Http\Middleware\AdminMiddleware::class);

    Route::get('/marketplace-performance', fn () => redirect()->route('mp.orders'))
        ->name('marketplace-performance')
        ->middleware(\App\Http\Middleware\AdminMiddleware::class);

    Route::get('/marketplace-listing-push', fn () => redirect()->route('mp.orders'))
        ->name('marketplace-listing-push')
        ->middleware(\App\Http\Middleware\AdminMiddleware::class);

    Route::get('/marketplace-orders', \App\Livewire\MarketplaceOrders::class)
        ->name('mp.orders')
        ->middleware('mp.feature:orders_v2_enabled')
        ->middleware(\App\Http\Middleware\AdminMiddleware::class);

    Route::get('/marketplace-orders/documents/{documentType}', [MarketplaceOrderDocumentController::class, 'download'])
        ->name('mp.orders.documents.download')
        ->middleware('mp.feature:orders_v2_enabled')
        ->middleware(\App\Http\Middleware\AdminMiddleware::class);

    Route::get('/marketplace-overview', \App\Livewire\MarketplaceOverview::class)
        ->name('mp.overview')
        ->middleware('mp.feature:overview_enabled')
        ->middleware(\App\Http\Middleware\AdminMiddleware::class);

    Route::get('/marketplace-profit-center', \App\Livewire\MarketplaceProfitCenter::class)
        ->name('mp.profit-center')
        ->middleware('mp.feature:profit_center_enabled')
        ->middleware(\App\Http\Middleware\AdminMiddleware::class);

    Route::get('/marketplace-trendyol-booster', \App\Livewire\TrendyolBooster::class)
        ->name('mp.trendyol-booster')
        ->middleware('mp.feature:trendyol_booster_enabled')
        ->middleware(\App\Http\Middleware\AdminMiddleware::class);

    Route::prefix('/marketplace-trendyol-booster/companion')
        ->name('mp.trendyol-booster.companion.')
        ->middleware('mp.feature:trendyol_booster_enabled')
        ->middleware(\App\Http\Middleware\AdminMiddleware::class)
        ->group(function () {
            Route::get('/session', [TrendyolBoosterCompanionController::class, 'session'])->name('session');
            Route::get('/status', [TrendyolBoosterCompanionController::class, 'status'])->name('status');
            Route::post('/preview', [TrendyolBoosterCompanionController::class, 'preview'])->name('preview');
            Route::post('/product-analysis', [TrendyolBoosterCompanionController::class, 'productAnalysis'])->name('product-analysis');
            Route::post('/track', [TrendyolBoosterCompanionController::class, 'track'])->name('track');
            Route::post('/stock-check', [TrendyolBoosterCompanionController::class, 'stockCheck'])->name('stock-check');
            Route::post('/store-scan', [TrendyolBoosterCompanionController::class, 'storeScan'])->name('store-scan');
            Route::get('/pending-jobs', [TrendyolBoosterCompanionController::class, 'pendingJobs'])->name('pending-jobs');
            Route::post('/market-research', [TrendyolBoosterCompanionController::class, 'marketResearch'])->name('market-research');
        });

    Route::get('/marketplace-pricing-simulator', \App\Livewire\MarketplacePricingSimulator::class)
        ->name('mp.pricing-simulator')
        ->middleware('mp.feature:pricing_simulator_enabled')
        ->middleware(\App\Http\Middleware\AdminMiddleware::class);

    Route::get('/marketplace-settlement-audit', \App\Livewire\MarketplaceSettlementAudit::class)
        ->name('mp.settlement-audit')
        ->middleware('mp.feature:settlement_audit_enabled')
        ->middleware(\App\Http\Middleware\AdminMiddleware::class);

    Route::get('/marketplace-risk-center', \App\Livewire\MarketplaceRiskCenter::class)
        ->name('mp.risk-center')
        ->middleware('mp.feature:risk_center_enabled')
        ->middleware(\App\Http\Middleware\AdminMiddleware::class);

    Route::get('/marketplace-report-digests', \App\Livewire\MarketplaceReportDigestSettings::class)
        ->name('mp.report-digests')
        ->middleware('mp.feature:report_digest_enabled')
        ->middleware(\App\Http\Middleware\AdminMiddleware::class);

    Route::get('/marketplace-integrations', \App\Livewire\MarketplaceIntegrations::class)
        ->name('mp.integrations')
        ->middleware('mp.feature:integrations_enabled')
        ->middleware(\App\Http\Middleware\AdminMiddleware::class);

    Route::get('/marketplace-products', \App\Livewire\MpProductsManager::class)
        ->name('mp.products')
        ->middleware('mp.feature:products_v2_enabled')
        ->middleware(\App\Http\Middleware\AdminMiddleware::class);

    Route::get('/marketplace-matching-center', \App\Livewire\MarketplaceMatchingCenter::class)
        ->name('mp.matching')
        ->middleware('mp.feature:matching_center_enabled')
        ->middleware(\App\Http\Middleware\AdminMiddleware::class);

    Route::get('/marketplace-finance', \App\Livewire\MarketplaceFinance::class)
        ->name('mp.finance')
        ->middleware('mp.feature:finance_v2_enabled')
        ->middleware(\App\Http\Middleware\AdminMiddleware::class);

    Route::get('/marketplace-settings', \App\Livewire\MarketplaceSettings::class)
        ->name('mp.settings')
        ->middleware(\App\Http\Middleware\AdminMiddleware::class);

    // Reçete Modülü
    Route::get('/recipe-materials', \App\Livewire\RecipeMaterialsManager::class)
        ->name('recipe.materials')
        ->middleware(\App\Http\Middleware\AdminMiddleware::class);
    Route::get('/recipe-builder/{recipeId?}', \App\Livewire\RecipeBuilder::class)
        ->name('recipe.builder')
        ->middleware(\App\Http\Middleware\AdminMiddleware::class);
    Route::get('/production-planner', \App\Livewire\ProductionPlanner::class)
        ->name('production.planner')
        ->middleware(\App\Http\Middleware\AdminMiddleware::class);

    if (
        class_exists(\App\Livewire\Returns\ReturnWorkspace::class)
        && class_exists(\App\Http\Middleware\EnsureReturnFeatureEnabled::class)
    ) {
        Route::get('/returns', \App\Livewire\Returns\ReturnWorkspace::class)
            ->name('returns.workspace')
            ->middleware(\App\Http\Middleware\EnsureReturnFeatureEnabled::class);

        Route::get('/returns/intake', function () {
            return redirect()->route('returns.workspace', array_merge(request()->query(), ['tab' => 'kabul']));
        })
            ->name('returns.intake')
            ->middleware(\App\Http\Middleware\EnsureReturnFeatureEnabled::class)
            ->middleware('can:accessReturnsIntake');

        Route::get('/returns/center', function () {
            return redirect()->route('returns.workspace', array_merge(request()->query(), ['tab' => 'havuz']));
        })
            ->name('returns.center')
            ->middleware(\App\Http\Middleware\EnsureReturnFeatureEnabled::class)
            ->middleware('can:accessReturnsReview');

        Route::get('/returns/marketplace-claims', function () {
            return redirect()->route('returns.workspace', array_merge(request()->query(), ['tab' => 'pazaryeri']));
        })
            ->name('returns.marketplace-claims')
            ->middleware(\App\Http\Middleware\EnsureReturnFeatureEnabled::class)
            ->middleware('can:accessReturnsReview');

        Route::get('/returns/whatsapp-bridge', function () {
            return redirect()->route('returns.workspace', array_merge(request()->query(), ['tab' => 'whatsapp']));
        })
            ->name('returns.whatsapp-bridge')
            ->middleware(\App\Http\Middleware\EnsureReturnFeatureEnabled::class)
            ->middleware('can:accessReturnsReview');
    }

    Route::get('/api-dev', \App\Livewire\ApiDev::class)->name('api-dev');

    if (app()->environment('local')) {
        Route::get('/fix-routes', function () {
            \Illuminate\Support\Facades\Artisan::call('optimize:clear');

            return 'Optimize cache temizlendi. <a href="/dashboard">Dashboard\'a dön</a>';
        });

        Route::get('/force-migrate', function () {
            try {
                \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);

                return 'Migrasyon başarıyla tamamlandı: <br><pre>' . \Illuminate\Support\Facades\Artisan::output() . '</pre>';
            } catch (\Exception $e) {
                return 'Hata: ' . $e->getMessage();
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
    Route::get('/download/{reportFile}', function (\App\Models\ReportFile $reportFile) {
        $user = auth()->user();
        if (!$user->isAdmin()) {
            if (!$reportFile->report || $reportFile->report->user_id !== $user->id) {
                abort(403, 'Bu dosyaya erişim yetkiniz yok.');
            }
        }

        $fullPath = \Illuminate\Support\Facades\Storage::disk('local')->path($reportFile->file_path);
        
        if (!file_exists($fullPath)) {
            abort(404, 'Dosya bulunamadı');
        }

        // BinaryFileResponse kullan - output buffering sorununu önler
        return response()->download($fullPath, $reportFile->filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $reportFile->filename . '"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    })->name('download');

    // Report History alias
    Route::get('/report-history', ReportHistory::class)->name('report-history');

    // ============================================
    // ADMIN ROUTES
    // ============================================
    Route::middleware(\App\Http\Middleware\AdminMiddleware::class)->prefix('admin')->group(function () {
        Route::get('/', \App\Livewire\Admin\Dashboard::class)->name('admin.dashboard');
        Route::get('/users', \App\Livewire\Admin\UserManager::class)->name('admin.users');
        Route::get('/logs', \App\Livewire\Admin\ActivityLogs::class)->name('admin.logs');
    });
});

// ============================================
// DEBUG ROUTES - ONLY LOCAL ENVIRONMENT
// ============================================
if (app()->environment('local')) {
    // Excel test route - sorun tespiti için
    Route::get('/test-excel', function () {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Test');
        $sheet->setCellValue('A1', 'Merhaba');
        $sheet->setCellValue('B1', 'Dünya');
        $sheet->setCellValue('A2', 'Test');
        $sheet->setCellValue('B2', '123');
        
        $tempFile = storage_path('app/test-excel.xlsx');
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($tempFile);
        
        return response()->download($tempFile, 'test-dosya.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    });

    // Son rapor dosyasını test et
    Route::get('/test-last-file', function () {
        $lastFile = \App\Models\ReportFile::latest()->first();
        if (!$lastFile) {
            return 'Dosya bulunamadı';
        }
        
        $fullPath = \Illuminate\Support\Facades\Storage::disk('local')->path($lastFile->file_path);
        
        if (!file_exists($fullPath)) {
            return 'Dosya mevcut değil: ' . $lastFile->file_path;
        }
        
        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($fullPath);
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
            
        } catch (\Exception $e) {
            return 'Hata: ' . $e->getMessage();
        }
    });

    // Kuyrukta bekleyen tüm entegrasyon işlerini anında senkronize et
    Route::get('/force-sync', function () {
        $runs = \App\Models\IntegrationSyncRun::where('status', 'queued')
                    ->orderBy('id', 'desc')
                    ->take(10) // Sadece en yeni 10 işi al, eski takılıları çekme
                    ->get();

        if ($runs->isEmpty()) {
            return 'Kuyrukta bekleyen (queued) yeni senkronizasyon işi yok.';
        }

        $count = 0;
        $errors = [];
        $syncService = app(\App\Services\Marketplace\MarketplaceSyncService::class);
        
        foreach($runs as $run) {
            try {
                $syncService->run($run->id);
                $count++;
            } catch (\Exception $e) {
                // Eski run'u fail yapıp kurtul
                $run->status = 'failed';
                $run->save();
                $errors[] = "Run #{$run->id}: " . $e->getMessage();
            }
        }
        
        $output = "{$count} adet senkronizasyon kuyruğu başarıyla işlendi! Siparişler ekrana düşmüş olmalı.<br><br>";
        if (!empty($errors)) {
            $output .= "<b>Alınan Hatalar (Bu hatalar eski veya geçersiz bağlantılara ait olabilir ve atlanmıştır):</b><br>" . implode('<br>', $errors);
        }
        return $output;
    });

    Route::get('/debug-runs', function () {
        $runs = \App\Models\IntegrationSyncRun::where('status', 'completed')
                    ->orderBy('id', 'desc')
                    ->take(5)
                    ->get();
        
        $output = "";
        foreach($runs as $run) {
            $notes = json_encode($run->notes_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $output .= "Run #{$run->id} (Store {$run->store_id} - {$run->sync_type}): <pre>{$notes}</pre><hr>";
        }
        return $output;
    });

    Route::get('/woo-reset', function () {
        $storeIds = \App\Models\MarketplaceStore::where('marketplace', 'woocommerce')->pluck('id');
        if ($storeIds->isEmpty()) return 'WooCommerce mağazası bulunamadı.';
        
        $runs = \App\Models\IntegrationSyncRun::whereIn('store_id', $storeIds)
            ->where('sync_type', 'orders')
            ->where('status', 'completed')
            ->update(['status' => 'failed', 'notes_json' => []]);
            
        return "Tüm WooCommerce mağazalarının geçmiş kilitleri sıfırlandı ({$runs} adet işlem iptal edildi). Şimdi 'Siparişleri Çek' butonuna bastığınızda tüm son 7 günü baştan tarayacaktır!";
    });

    Route::get('/woo-test', function () {
        $store = \App\Models\MarketplaceStore::where('marketplace', 'woocommerce')->orderBy('id', 'desc')->first();
        if (!$store) return 'Store not found';
        
        $startDate = \Carbon\CarbonImmutable::now()->subDays(7);
        $connector = app(\App\Services\Marketplace\MarketplaceConnectorManager::class)->resolve('woocommerce');
        
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
                'meta' => $response['meta'] ?? null
            ]);
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    });
}
