<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SupportDispatch;
use App\Models\SupportIntegrationDelivery;

class CustomerCareQueueHealthCommand extends Command
{
    protected $signature = 'customer-care:queue-health {--store= : Store ID}';
    protected $description = 'Kuyruk sağlığı ve lag durumunu analiz eder.';

    public function handle()
    {
        $storeId = $this->option('store');
        $this->info("Kuyruk Sağlığı Analiz Ediliyor... Store: " . ($storeId ?? 'Tümü'));

        $dispatchQuery = SupportDispatch::query();
        $deliveryQuery = SupportIntegrationDelivery::query();

        if ($storeId) {
            $dispatchQuery->whereHas('conversation', function ($q) use ($storeId) {
                $q->where('store_id', $storeId);
            });
            $deliveryQuery->whereHas('event', function ($q) use ($storeId) {
                $q->where('store_id', $storeId);
            });
        }

        $pendingDispatches = (clone $dispatchQuery)->whereIn('status', ['pending', 'sending'])->count();
        $failedDispatches = (clone $dispatchQuery)->where('status', 'failed')->count();
        $exhaustedDispatches = (clone $dispatchQuery)->where('status', 'exhausted')->count();

        $pendingDeliveries = (clone $deliveryQuery)->where('status', 'pending')->count();
        $deadLetters = (clone $deliveryQuery)->where('status', 'dead_letter')->count();

        $this->line("Bekleyen Gönderimler (Outbound Queue): " . $pendingDispatches);
        $this->line("Yeniden Denenecek Gönderimler: " . $failedDispatches);
        $this->line("Başarısız Gönderimler (Exhausted): " . $exhaustedDispatches);
        $this->line("Bekleyen Webhook Teslimatları: " . $pendingDeliveries);
        $this->line("Dead-letter Webhook'lar: " . $deadLetters);

        return 0;
    }
}
