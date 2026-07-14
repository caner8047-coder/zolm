<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Support\CustomerCarePilotMonitorService;
use App\Models\MarketplaceStore;
use App\Services\Support\TenantContext;

class PilotMonitorCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'customer-care:pilot-monitor {--store= : İzlenecek mağaza ID\'si}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Müşteri İletişim Merkezi pilot otomasyon metriklerini ve circuit breaker durumunu raporlar';

    /**
     * Execute the console command.
     */
    public function handle(CustomerCarePilotMonitorService $monitorService): int
    {
        if (!config('customer-care.enabled', false) || !config('customer-care.pilot_dashboard_enabled', false)) {
            $this->error('Müşteri iletişim merkezi veya pilot izleme özelliği devre dışı.');
            return self::FAILURE;
        }

        try {
            $actor = TenantContext::getSystemActor();
        } catch (\Throwable $exception) {
            $this->error('Pilot monitor çalıştırılamadı: ' . $exception->getMessage());

            return 1;
        }

        $storeId = $this->option('store');

        if ($storeId) {
            $store = MarketplaceStore::find($storeId);
            if (!$store) {
                $this->error("Belirtilen ID ({$storeId}) ile eşleşen bir mağaza bulunamadı.");
                return 1;
            }
            $stores = collect([$store]);
        } else {
            // Monitor all stores
            $stores = MarketplaceStore::all();
        }

        foreach ($stores as $store) {
            $this->info("=== {$store->store_name} ({$store->marketplace}) Pilot Otomasyon Metrikleri ===");
            $metrics = $monitorService->getStoreMetrics($store->id, $actor);

            $circuitStatus = match ($metrics['circuit_breaker_status'] ?? 'unknown') {
                'open' => '🔴 OPEN (Tripped/Blocked)',
                'closed' => '🟢 CLOSED (Active)',
                'disabled' => '🟠 DISABLED (Doğrulanmamış)',
                default => '⚪ UNKNOWN (Ölçüm yok)',
            };
            $this->line('Circuit Breaker Durumu: ' . $circuitStatus);
            $this->line("Manual Override: " . ($metrics['manual_override'] ? 'Kilitli (Forced Open)' : 'Normal'));
            $this->line("Son 15 Dk Hata Sayısı: {$metrics['dispatch_failures_15m']} (Eşik: {$metrics['max_dispatch_failures_15m']})");
            $this->line("Son 15 Dk Politika Engeli: {$metrics['policy_blocks_15m']} (Eşik: {$metrics['max_policy_blocks_15m']})");
            $this->line("Kuyruk Bekleyen (Outbox Backlog): {$metrics['outbox_backlog']}");
            $this->line("Otomatik Cevap Sayısı (Toplam): {$metrics['auto_reply_count']}");
            $this->line("Temsilciye Aktarma (Handoff Toplam): {$metrics['handoff_count']}");
            $confidence = $metrics['average_confidence'] === null
                ? 'Ölçüm yok'
                : '%' . round($metrics['average_confidence'], 1);
            $this->line("Ortalama Güven Skoru: {$confidence}");
            $this->line("");
        }

        return 0;
    }
}
