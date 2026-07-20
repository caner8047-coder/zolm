<?php

namespace App\Console\Commands;

use App\Models\MarketplaceStore;
use App\Models\MpPriceEmergencyStop;
use App\Models\MpPricePilotProduct;
use App\Services\Marketplace\MarketplacePricePilotService;
use App\Services\Marketplace\MarketplacePriceEmergencyStopService;
use Illuminate\Console\Command;

class MarketplacePricePilotCommand extends Command
{
    protected $signature = 'marketplace:price-pilot
                            {action : status, enable-shadow, add-product, remove-product, pause, emergency-stop, report, enable-canary}
                            {store_id : The ID of the marketplace store}
                            {barcode? : Product barcode for add/remove product}
                            {--confirm : Required for enable-canary double confirmation}
                            {--hours=24 : Report duration in hours}
                            {--format=table : Output format: table, json, excel}
                            {--include-products : Include product list in report}
                            {--include-blocked : Include blocked recommendations in report}
                            {--include-errors : Include errors/failed actions in report}';

    protected $description = 'ZOLM Trendyol Buybox Shadow Mode, Pilot and Canary control center';

    public function handle(
        MarketplacePricePilotService $pilotService,
        MarketplacePriceEmergencyStopService $emergencyStopService
    ): int {
        $storeId = (int) $this->argument('store_id');
        $store = MarketplaceStore::find($storeId);

        if (!$store) {
            $this->error("Mağaza (ID: {$storeId}) bulunamadı.");
            return Command::FAILURE;
        }

        $action = $this->argument('action');

        switch ($action) {
            case 'status':
                return $this->showStatus($store, $pilotService, $emergencyStopService);
            case 'enable-shadow':
                return $this->enableShadow($store);
            case 'add-product':
                return $this->addProduct($store, $pilotService);
            case 'remove-product':
                return $this->removeProduct($store, $pilotService);
            case 'pause':
                return $this->pausePilot($store);
            case 'emergency-stop':
                return $this->emergencyStop($store, $emergencyStopService);
            case 'enable-canary':
                return $this->enableCanary($store);
            case 'report':
                return $this->generateReport($store);
            default:
                $this->error("Geçersiz aksiyon: {$action}");
                return Command::FAILURE;
        }
    }

    protected function showStatus(
        MarketplaceStore $store,
        MarketplacePricePilotService $pilotService,
        MarketplacePriceEmergencyStopService $emergencyStopService
    ): int {
        $this->info("=== Mağaza Durumu: {$store->store_name} ===");
        $this->line("Emergency Stop: " . ($emergencyStopService->isEmergencyStopActive($store->id) ? '🔴 AKTİF' : '🟢 PASİF'));
        
        $pilotProducts = MpPricePilotProduct::where('store_id', $store->id)
            ->where('mode', '!=', 'disabled')
            ->get();
            
        $this->info("Pilot Ürün Sayısı: " . $pilotProducts->count() . " / Maks Limit: " . $pilotService->maxPilotProductLimit($store));
        
        $headers = ['Barkod', 'Mod', 'Eklenme Tarihi'];
        $data = $pilotProducts->map(fn ($p) => [$p->barcode, $p->mode, $p->created_at])->toArray();
        $this->table($headers, $data);

        return Command::SUCCESS;
    }

    protected function enableShadow(MarketplaceStore $store): int
    {
        config(['marketplace.trendyol.shadow_mode_enabled' => true]);
        $this->info("Gölge Mod (Shadow Mode) etkinleştirildi. Fiyat push işlemleri simüle edilecektir.");
        return Command::SUCCESS;
    }

    protected function addProduct(MarketplaceStore $store, MarketplacePricePilotService $pilotService): int
    {
        $barcode = $this->argument('barcode');
        if (!$barcode) {
            $this->error("Lütfen eklenecek ürün barkodunu girin.");
            return Command::FAILURE;
        }

        try {
            $pilotService->addProductToPilot($store, $barcode, 'shadow', 'Artisan CLI added');
            $this->info("Ürün ({$barcode}) başarıyla pilot listesine (Shadow Mod) eklendi.");
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            return Command::FAILURE;
        }
    }

    protected function removeProduct(MarketplaceStore $store, MarketplacePricePilotService $pilotService): int
    {
        $barcode = $this->argument('barcode');
        if (!$barcode) {
            $this->error("Lütfen çıkarılacak ürün barkodunu girin.");
            return Command::FAILURE;
        }

        $pilotService->removeProductFromPilot($store->id, $barcode);
        $this->info("Ürün ({$barcode}) pilot listesinden çıkarıldı (devre dışı bırakıldı).");
        return Command::SUCCESS;
    }

    protected function pausePilot(MarketplaceStore $store): int
    {
        MpPricePilotProduct::where('store_id', $store->id)->update(['mode' => 'paused']);
        $this->info("Tüm pilot ürünler 'DURAKLATILDI' (paused) durumuna alındı.");
        return Command::SUCCESS;
    }

    protected function emergencyStop(MarketplaceStore $store, MarketplacePriceEmergencyStopService $emergencyStopService): int
    {
        $emergencyStopService->activateEmergencyStop($store->id, 'Artisan CLI emergency stop');
        $this->error("Acil Durdurma (Emergency Stop) {$store->store_name} mağazası için tetiklendi! Tüm API fiyat push akışları kesildi.");
        return Command::SUCCESS;
    }

    protected function enableCanary(MarketplaceStore $store): int
    {
        if (!$this->option('confirm')) {
            $this->error("Canary otomasyonunu açmak kritik risk taşır! Lütfen komutu '--confirm' bayrağıyla çalıştırın.");
            return Command::FAILURE;
        }

        config([
            'marketplace.trendyol.automatic_price_actions_enabled' => true,
            'marketplace.trendyol.canary_enabled' => true,
        ]);
        
        $this->warn("=== CANARY OTOMASYONU AKTİF EDİLDİ ===");
        $this->line("Pilot ürünler artık kural sınırları dahilinde otomatik fiyatlandırılacaktır.");
        return Command::SUCCESS;
    }

    protected function generateReport(MarketplaceStore $store): int
    {
        $hours = (int) $this->option('hours');
        $format = $this->option('format');
        $includeProducts = $this->option('include-products');
        $includeBlocked = $this->option('include-blocked');
        $includeErrors = $this->option('include-errors');

        $since = now()->subHours($hours);

        // Fetch Shadow Mode records
        $query = \App\Models\MpPriceShadowRecord::where('store_id', $store->id)
            ->where('simulated_at', '>=', $since);

        if (!$includeBlocked) {
            $query->where('is_actionable', true);
        }

        $records = $query->get();
        $totalCount = $records->count();

        // Calculate KPIs
        $actionableCount = $records->where('is_actionable', true)->count();
        $blockedCount = $totalCount - $actionableCount;

        $evaluations = \App\Models\MpPriceShadowEvaluation::where('store_id', $store->id)
            ->where('created_at', '>=', $since)
            ->get();

        $evalCount = $evaluations->count();
        $wouldWinCount = $evaluations->where('would_win_buybox', true)->count();
        $wouldPreserveMarginCount = $evaluations->where('would_preserve_margin', true)->count();
        $unnecessaryDrops = $evaluations->where('was_unnecessary_drop', true)->count();
        $correctRaises = $evaluations->where('was_raise_opportunity_correct', true)->count();

        $avgPriceDev = $evaluations->avg('price_deviation') ?? 0.0;
        $avgDuration = $evaluations->avg('validity_duration_minutes') ?? 0;

        $errorsQuery = \App\Models\MpPriceAction::where('store_id', $store->id)
            ->where('created_at', '>=', $since)
            ->where('status', 'failed');
        $errorsCount = $errorsQuery->count();

        $kpis = [
            'store_id' => $store->id,
            'store_name' => $store->store_name,
            'hours' => $hours,
            'total_shadow_records' => $totalCount,
            'actionable_records' => $actionableCount,
            'actionable_rate_pct' => $totalCount > 0 ? round(($actionableCount / $totalCount) * 100, 2) : 0.0,
            'blocked_records' => $blockedCount,
            'blocked_rate_pct' => $totalCount > 0 ? round(($blockedCount / $totalCount) * 100, 2) : 0.0,
            'evaluated_records' => $evalCount,
            'estimated_buybox_win_pct' => $evalCount > 0 ? round(($wouldWinCount / $evalCount) * 100, 2) : 0.0,
            'margin_preserved_pct' => $evalCount > 0 ? round(($wouldPreserveMarginCount / $evalCount) * 100, 2) : 0.0,
            'unnecessary_drop_pct' => $evalCount > 0 ? round(($unnecessaryDrops / $evalCount) * 100, 2) : 0.0,
            'correct_raise_pct' => $evalCount > 0 ? round(($correctRaises / $evalCount) * 100, 2) : 0.0,
            'average_price_deviation' => round($avgPriceDev, 2),
            'average_validity_duration_minutes' => round($avgDuration, 1),
            'failed_actions_count' => $errorsCount,
        ];

        if ($format === 'json') {
            $output = ['kpis' => $kpis];
            if ($includeProducts) {
                $output['products'] = $records->map(fn($r) => [
                    'barcode' => $r->barcode,
                    'current_price' => $r->current_price,
                    'recommended_price' => $r->recommended_price,
                    'buybox_price' => $r->buybox_price,
                    'type' => $r->recommendation_type,
                    'risk' => $r->risk_level,
                    'is_actionable' => $r->is_actionable,
                ])->toArray();
            }
            if ($includeErrors) {
                $output['errors'] = $errorsQuery->get()->map(fn($e) => [
                    'barcode' => $e->barcode,
                    'code' => $e->failure_code,
                    'message' => $e->failure_message,
                    'at' => $e->created_at->toDateTimeString(),
                ])->toArray();
            }
            $this->output->write(json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return Command::SUCCESS;
        }

        if ($format === 'excel') {
            $excelService = app(\App\Services\ExcelService::class);
            $sheet1Data = [
                [
                    'Metrik' => 'Mağaza Adı',
                    'Değer' => $kpis['store_name'],
                ],
                [
                    'Metrik' => 'Gözlem Süresi (Saat)',
                    'Değer' => $kpis['hours'],
                ],
                [
                    'Metrik' => 'Toplam Gölge Öneri',
                    'Değer' => $kpis['total_shadow_records'],
                ],
                [
                    'Metrik' => 'Uygulanabilir Gölge Öneri',
                    'Değer' => $kpis['actionable_records'] . ' (%' . $kpis['actionable_rate_pct'] . ')',
                ],
                [
                    'Metrik' => 'Engellenen Gölge Öneri',
                    'Değer' => $kpis['blocked_records'] . ' (%' . $kpis['blocked_rate_pct'] . ')',
                ],
                [
                    'Metrik' => 'Tahmini Buybox Kazanım Oranı',
                    'Değer' => '%' . $kpis['estimated_buybox_win_pct'],
                ],
                [
                    'Metrik' => 'Marj Koruma Oranı',
                    'Değer' => '%' . $kpis['margin_preserved_pct'],
                ],
                [
                    'Metrik' => 'Gereksiz Fiyat Düşürme Oranı',
                    'Değer' => '%' . $kpis['unnecessary_drop_pct'],
                ],
                [
                    'Metrik' => 'Doğru Yükseltme Oranı',
                    'Değer' => '%' . $kpis['correct_raise_pct'],
                ],
                [
                    'Metrik' => 'Ortalama Fiyat Sapması',
                    'Değer' => '₺' . $kpis['average_price_deviation'],
                ],
                [
                    'Metrik' => 'Ortalama Geçerlilik Süresi (Dk)',
                    'Değer' => $kpis['average_validity_duration_minutes'],
                ],
            ];

            $sheet2Data = $records->map(fn($r) => [
                'Barkod' => $excelService->cleanString($r->barcode),
                'Mevcut Fiyat' => (float) $r->current_price,
                'Önerilen Fiyat' => (float) $r->recommended_price,
                'Buybox Fiyatı' => (float) $r->buybox_price,
                'Öneri Türü' => $excelService->cleanString($r->recommendation_type),
                'Risk Seviyesi' => $excelService->cleanString($r->risk_level),
                'Uygulanabilir' => $r->is_actionable ? 'EVET' : 'HAYIR',
            ])->toArray();

            $sheets = [
                ['name' => 'Özet Metrikler', 'data' => $sheet1Data],
                ['name' => 'Öneriler Detay', 'data' => $sheet2Data],
            ];

            $tempPath = storage_path('app/temp_pilot_report_' . uniqid() . '.xlsx');
            $excelService->exportToXlsx($sheets, $tempPath);

            $this->info("Excel raporu başarıyla oluşturuldu: {$tempPath}");
            return Command::SUCCESS;
        }

        // Default 'table' format
        $this->info("=== ZOLM Pilot & Shadow Mode KPI Raporu ===");
        $this->line("Mağaza: {$kpis['store_name']} (ID: {$kpis['store_id']})");
        $this->line("Süre: Son {$kpis['hours']} saat");
        $this->line("--------------------------------------------------");
        $this->line("Toplam Gölge Öneri: {$kpis['total_shadow_records']}");
        $this->line("Uygulanabilir Gölge Öneri: {$kpis['actionable_records']} (%{$kpis['actionable_rate_pct']})");
        $this->line("Engellenen Gölge Öneri: {$kpis['blocked_records']} (%{$kpis['blocked_rate_pct']})");
        $this->line("Değerlendirilen Öneri Sayısı: {$kpis['evaluated_records']}");
        $this->line("Tahmini Buybox Kazanım Oranı: %{$kpis['estimated_buybox_win_pct']}");
        $this->line("Marj Koruma Oranı: %{$kpis['margin_preserved_pct']}");
        $this->line("Gereksiz Fiyat Düşürme Oranı: %{$kpis['unnecessary_drop_pct']}");
        $this->line("Fiyat Artırma Fırsatı Doğruluğu: %{$kpis['correct_raise_pct']}");
        $this->line("Ortalama Fiyat Sapması: ₺{$kpis['average_price_deviation']}");
        $this->line("Ortalama Öneri Geçerlilik Süresi: {$kpis['average_validity_duration_minutes']} dakika");
        $this->line("Hatalı/Engellenen Aksiyon Sayısı: {$kpis['failed_actions_count']}");

        if ($includeProducts && $totalCount > 0) {
            $this->info("\n--- Detaylı Ürün Listesi ---");
            $headers = ['Barkod', 'Mevcut', 'Önerilen', 'Buybox', 'Tür', 'Risk', 'Uygulanabilir'];
            $tableData = $records->map(fn($r) => [
                $r->barcode,
                '₺' . $r->current_price,
                $r->recommended_price ? '₺' . $r->recommended_price : '-',
                $r->buybox_price ? '₺' . $r->buybox_price : '-',
                $r->recommendation_type,
                $r->risk_level,
                $r->is_actionable ? 'EVET' : 'HAYIR',
            ])->toArray();
            $this->table($headers, $tableData);
        }

        return Command::SUCCESS;
    }
}
