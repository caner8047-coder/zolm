<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Support\CustomerCareProductionReadinessService;

class CustomerCareProductionRollbackDrillCommand extends Command
{
    protected $signature = 'customer-care:production-rollback-drill {--store= : Mağaza ID} {--dry-run : Geri alma tatbikatını çalıştırır (varsayılan: true)}';
    protected $description = 'Müşteri İletişim Merkezi için üretim geri alma tatbikatı (Rollback Drill) raporu üretir';

    public function handle(): int
    {
        $storeId = $this->option('store');
        
        if (!$storeId) {
            $this->error("Mağaza ID belirtmek zorunludur. Örn: --store=1");
            return 1;
        }

        $this->info("Üretim Geri Alma (Rollback Drill) Tatbikatı Başlatılıyor...");
        $this->line("Hedef Mağaza: {$storeId}");

        $service = app(CustomerCareProductionReadinessService::class);
        $result = $service->runRollbackDrill((int)$storeId);

        $this->line("--------------------------------------------------");
        $this->info("Geri Alma Planı: " . strtoupper($result['rollback_path']));
        $this->line("Bekleyen outbox mesaj sayısı: " . $result['pending_dispatches']);
        $this->line("Otomasyon Devre Kesici (Circuit Breaker) Durumu: " . ($result['automation_circuit_breaker_active'] ? "AKTİF" : "PASİF"));
        $this->line("Tatbikat Zamanı: " . $result['drill_timestamp']);
        $this->line("--------------------------------------------------");
        $this->info("Tatbikat başarıyla tamamlandı (dry-run). Herhangi bir kalıcı veritabanı değişikliği yapılmadı.");

        return 0;
    }
}
