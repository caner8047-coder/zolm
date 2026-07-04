<?php

namespace App\Console\Commands;

use App\Services\WhatsApp\WebhookRetryService;
use Illuminate\Console\Command;

class WhatsAppRetryWebhooksCommand extends Command
{
    protected $signature = 'whatsapp:retry-webhooks';
    protected $description = 'Başarısız webhook\'ları exponential backoff ile tekrar dener.';

    public function handle(WebhookRetryService $retryService): int
    {
        $results = $retryService->retryFailedWebhooks();

        $this->info("Webhook retry tamamlandı:");
        $this->line("  Toplam: {$results['total']}");
        $this->line("  Tekrar denenen: {$results['retried']}");
        $this->line("  Başarılı: {$results['succeeded']}");
        $this->line("  Başarısız: {$results['failed_again']}");

        return Command::SUCCESS;
    }
}
