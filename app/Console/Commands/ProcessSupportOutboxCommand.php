<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Support\SupportOutboxService;

class ProcessSupportOutboxCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'support:process-outbox';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Müşteri İletişim Merkezi giden mesaj kuyruğunu (outbox) işler.';

    /**
     * Execute the console command.
     */
    public function handle(SupportOutboxService $outboxService): int
    {
        $this->info('Müşteri İletişim Merkezi outbox kuyruğu işleniyor...');
        
        $outboxService->processPendingDispatches();
        
        $this->info('Outbox kuyruğu başarıyla işlendi.');
        
        return 0;
    }
}
