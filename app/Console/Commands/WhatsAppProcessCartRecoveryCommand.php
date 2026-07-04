<?php

namespace App\Console\Commands;

use App\Jobs\WhatsApp\ProcessCartRecoveryJob;
use Illuminate\Console\Command;

class WhatsAppProcessCartRecoveryCommand extends Command
{
    protected $signature = 'whatsapp:process-cart-recovery';

    protected $description = 'Bekleyen sepet kurtarma mesajlarını işler.';

    public function handle(): int
    {
        ProcessCartRecoveryJob::dispatch();
        $this->info('Sepet kurtarma job kuyruğa alındı.');
        return self::SUCCESS;
    }
}
