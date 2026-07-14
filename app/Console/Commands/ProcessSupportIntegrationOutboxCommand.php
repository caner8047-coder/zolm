<?php

namespace App\Console\Commands;

use App\Services\Support\Integration\CustomerCareIntegrationHubService;
use Illuminate\Console\Command;

class ProcessSupportIntegrationOutboxCommand extends Command
{
    protected $signature = 'customer-care:process-integration-outbox {--limit=100}';
    protected $description = 'CRM, ERP ve webhook outbound teslimat kuyruğunu işler';

    public function handle(CustomerCareIntegrationHubService $service): int
    {
        $result = $service->processPending((int) $this->option('limit'));
        $this->info("processed={$result['processed']} succeeded={$result['succeeded']} failed={$result['failed']}");
        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
