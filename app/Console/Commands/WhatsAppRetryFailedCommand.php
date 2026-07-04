<?php

namespace App\Console\Commands;

use App\Jobs\WhatsApp\RetryFailedMessageJob;
use Illuminate\Console\Command;

class WhatsAppRetryFailedCommand extends Command
{
    protected $signature = 'whatsapp:retry-failed';

    protected $description = 'Başarısız WhatsApp mesajlarını tekrar dener.';

    public function handle(): int
    {
        RetryFailedMessageJob::dispatch();

        $this->info('Retry job kuyruğa alındı.');

        return self::SUCCESS;
    }
}
