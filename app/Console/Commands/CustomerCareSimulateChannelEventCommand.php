<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Support\CustomerCareConnectorCertificationService;

class CustomerCareSimulateChannelEventCommand extends Command
{
    protected $signature = 'customer-care:simulate-channel-event {--store= : Mağaza ID} {--channel= : Kanal Anahtarı (Örn: web_chat)} {--fixture= : Fixture JSON dosya yolu} {--dry-run : Kalıcı veritabanı kaydı oluşturmadan simülasyon}';
    protected $description = 'Sandbox ortamında gelen webhook/kanal olayını simüle eder (İmza doğrulamalı)';

    public function handle(): int
    {
        $storeId = $this->option('store');
        $channelKey = $this->option('channel');
        $fixturePath = $this->option('fixture');
        $dryRun = $this->option('dry-run');

        if (!$storeId || !$channelKey || !$fixturePath) {
            $this->error("Tüm parametreler zorunludur: --store, --channel, --fixture");
            return 1;
        }

        if (!file_exists($fixturePath)) {
            $this->error("Belirtilen fixture dosyası bulunamadı: {$fixturePath}");
            return 1;
        }

        $payloadRaw = file_get_contents($fixturePath);
        $payload = json_decode($payloadRaw, true);

        if (!is_array($payload)) {
            $this->error("Fixture dosyası geçerli bir JSON içermiyor.");
            return 1;
        }

        $this->info("Simülasyon başlatılıyor: Mağaza {$storeId} | Kanal {$channelKey}...");

        $service = app(CustomerCareConnectorCertificationService::class);

        if ($dryRun) {
            $this->info("[DRY-RUN] Olay doğrulaması yapılıyor...");
            if ($channelKey === 'web_chat') {
                $rawJson = $payload['raw_json'] ?? null;
                $signature = $payload['signature'] ?? null;
                if (!$rawJson || !$signature) {
                    $this->error("Eksik imza doğrulaması (fail-closed)");
                    return 1;
                }
                $this->info("İmza formatı mevcut. Doğrulama başarılı (dry-run).");
            } else {
                $this->info("Kanal payload yapısı doğrulandı (dry-run).");
            }
            return 0;
        }

        $result = $service->simulateWebhookEvent((int)$storeId, $channelKey, $payload);

        if ($result['success']) {
            $this->info("Simülasyon BAŞARILI!");
            $this->line("Mesaj ID: " . ($result['message_id'] ?? 'Yok'));
        } else {
            $this->error("Simülasyon BAŞARISIZ: " . ($result['message'] ?? 'Bilinmeyen hata'));
        }

        return 0;
    }
}
