<?php

namespace App\Console\Commands;

use App\Services\WhatsApp\SLAService;
use Illuminate\Console\Command;

class WhatsAppCheckSlaBreachesCommand extends Command
{
    protected $signature = 'whatsapp:check-sla-breaches';

    protected $description = 'SLA ihlallerini kontrol eder.';

    public function handle(SLAService $service): int
    {
        $results = $service->checkBreaches();

        $this->info("SLA kontrolü tamamlandı.");
        $this->line("  Çözüm ihlali: {$results['resolution_breached']}");
        $this->line("  İlk yanıt ihlali: {$results['first_response_breached']}");

        return self::SUCCESS;
    }
}
