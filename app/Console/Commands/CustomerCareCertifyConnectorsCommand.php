<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SupportChannel;
use App\Services\Support\CustomerCareConnectorCertificationService;
use App\Services\Support\TenantContext;

class CustomerCareCertifyConnectorsCommand extends Command
{
    protected $signature = 'customer-care:certify-connectors {--store= : Mağaza ID} {--dry-run : Sadece raporlar, veritabanına eklemez}';
    protected $description = 'Belirli bir mağazanın tüm aktif kanalları için entegrasyon sertifikasyon denetimlerini çalıştırır';

    public function handle(): int
    {
        $storeId = $this->option('store');
        $dryRun = $this->option('dry-run');

        if (!$storeId) {
            $this->error("Mağaza ID belirtmek zorunludur. Örn: --store=1");
            return 1;
        }

        $activeChannels = SupportChannel::where('store_id', $storeId)->where('is_enabled', true)->get();

        if ($activeChannels->isEmpty()) {
            $this->warn("Bu mağaza için tanımlanmış aktif bir kanal bulunamadı.");
            return 0;
        }

        $service = app(CustomerCareConnectorCertificationService::class);
        try {
            $systemActor = TenantContext::getSystemActor();
        } catch (\Throwable $exception) {
            $this->error('Aktif sistem aktörü bulunamadı; sertifikasyon fail-closed durduruldu.');
            return 1;
        }
        $this->info("Toplam {$activeChannels->count()} kanal denetleniyor...");

        foreach ($activeChannels as $channel) {
            $this->line("--------------------------------------------------");
            $this->info("Kanal Anahtarı: {$channel->key} | Adı: {$channel->name}");

            if ($dryRun) {
                $inspection = $service->inspectChannel((int) $storeId, $channel->key, $systemActor);
                $this->info('Sertifikasyon Önizleme Durumu: ' . strtoupper($inspection['status']));
                foreach ($inspection['checks'] as $check) {
                    $statusSymbol = $check['status'] === 'pass' ? '✓' : ($check['status'] === 'warn' ? '!' : '✗');
                    $this->line("  [{$statusSymbol}] {$check['name']}: {$check['details']}");
                }
            } else {
                $run = $service->certifyChannel((int) $storeId, $channel->key, $systemActor);
                $this->info("Sertifikasyon Durumu: " . strtoupper($run->status));
                foreach ($run->checks as $check) {
                    $statusSymbol = $check->status === 'pass' ? '✓' : ($check->status === 'warn' ? '!' : '✗');
                    $this->line("  [{$statusSymbol}] {$check->check_name}: {$check->details}");
                }
            }
        }

        $this->line("==================================================");
        $this->info("Tüm kanal sertifikasyon süreçleri tamamlandı.");

        return 0;
    }
}
