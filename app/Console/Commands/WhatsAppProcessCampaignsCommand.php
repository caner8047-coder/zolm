<?php

namespace App\Console\Commands;

use App\Services\WhatsApp\CampaignSenderService;
use Illuminate\Console\Command;

class WhatsAppProcessCampaignsCommand extends Command
{
    protected $signature = 'whatsapp:process-campaigns';

    protected $description = 'Zamanlanmış ve çalışan kampanyaları işler.';

    public function handle(CampaignSenderService $service): int
    {
        // Zamanlanmış kampanyaları başlat
        $started = $service->processScheduledCampaigns();
        if ($started > 0) {
            $this->info("{$started} kampanya başlatıldı.");
        }

        // Çalışan kampanyaların batch'lerini gönder
        $sent = $service->processRunningCampaigns();
        $this->info("{$sent} mesaj kuyruğa alındı.");

        return self::SUCCESS;
    }
}
