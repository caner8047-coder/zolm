<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use App\Models\MarketplaceStore;
use App\Models\SupportAgentAction;
use App\Services\Support\TenantContext;
use App\Services\Support\Security\SupportRbacService;

class CircuitBreakerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'customer-care:circuit-breaker {--store= : Hedef mağaza ID\'si} {--enable : Circuit breaker\'ı aktif et (manuel durdur)} {--disable : Circuit breaker\'ı devreden çıkar (sıfırla)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Müşteri İletişim Merkezi store bazlı otomasyonunu manuel olarak durdurur veya sıfırlar (Circuit Breaker Override)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $storeId = $this->option('store');
        $enable = $this->option('enable');
        $disable = $this->option('disable');

        if (!$storeId) {
            $this->error('Lütfen mağaza ID\'sini belirtin: --store=ID');
            return 1;
        }

        $store = MarketplaceStore::find($storeId);
        if (!$store) {
            $this->error("Belirtilen ID ({$storeId}) ile eşleşen bir mağaza bulunamadı.");
            return 1;
        }

        if ((!$enable && !$disable) || ($enable && $disable)) {
            $this->error('Lütfen bir işlem seçin: --enable (manuel durdur) veya --disable (sıfırla)');
            return 1;
        }

        try {
            $actor = TenantContext::getSystemActor();
            TenantContext::enforceStoreAccess((int) $storeId, $actor);
            $rbac = app(SupportRbacService::class);
            $rbac->enforcePermission($actor, (int) $storeId, 'force_circuit_breaker');

            // Fail-safe durdurma anında uygulanabilir; otomasyonu yeniden açan
            // disable işlemi ise varlık-bağlı iki aşamalı onay gerektirir.
            if ($disable) {
                $rbac->enforceApproval($actor, (int) $storeId, 'circuit_breaker_disable_' . $storeId, [
                    'store_id' => (int) $storeId,
                ]);
            }
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return 1;
        }

        if ($enable) {
            Cache::put("circuit_breaker_forced_open_{$storeId}", true);
            SupportAgentAction::create([
                'conversation_id' => null,
                'user_id' => $actor->id,
                'action' => 'circuit_breaker_forced_open',
                'details_json' => ['store_id' => (int) $storeId],
            ]);
            $this->info("🔴 Mağaza {$store->store_name} için circuit breaker manuel olarak 'OPEN' yapıldı. Otomatik yanıtlar DURDURULDU.");

            // Dalga U: Pending AI dispatch'leri güvenli şekilde iptal et
            try {
                $anonymizationService = app(\App\Services\Support\CustomerCareAnonymizationService::class);
                $cancelled = $anonymizationService->cancelPendingAiDispatches((int)$storeId);
                if ($cancelled > 0) {
                    $this->warn("⚠️  {$cancelled} adet bekleyen AI gönderimi iptal edildi.");
                } else {
                    $this->line("ℹ️  İptal edilecek bekleyen AI gönderimi bulunamadı.");
                }
            } catch (\Throwable $e) {
                $this->warn('Pending AI dispatch iptali başarısız: ' . $e->getMessage());
            }
        } else {
            Cache::forget("circuit_breaker_forced_open_{$storeId}");
            SupportAgentAction::create([
                'conversation_id' => null,
                'user_id' => $actor->id,
                'action' => 'circuit_breaker_forced_closed',
                'details_json' => ['store_id' => (int) $storeId],
            ]);
            $this->info("🟢 Mağaza {$store->store_name} için circuit breaker sıfırlandı ('CLOSED').");
            $this->line("ℹ️  Not: Circuit breaker kapatıldı, ancak otomatik gönderim yeniden başlamamıştır.");
            $this->line("   Outbox işçisi yalnızca yeni mesajlar için çalışacaktır.");
        }

        return 0;
    }
}
