<?php

namespace App\Services\Support\Reliability;

use App\Models\SupportDispatch;
use App\Models\SupportIntegrationDelivery;
use App\Models\SupportAiRun;
use Illuminate\Support\Facades\Log;

class CustomerCareQueueHealthService
{
    /**
     * Store ve channel bazlı backpressure (aşırı yük) durumunu kontrol eder.
     */
    public function checkBackpressure(int $storeId): array
    {
        $enabled = config('customer-care.reliability_enabled', false);
        if (!$enabled) {
            return [
                'backpressure' => false,
                'status' => 'unknown',
                'reason' => 'Güvenilirlik merkezi kapalı; kuyruk sağlığı doğrulanamadı.',
            ];
        }

        // Count total database records to determine if we have any data
        $totalDispatches = SupportDispatch::whereHas('conversation', function ($q) use ($storeId) {
                $q->where('store_id', $storeId);
            })->count();
        $totalDeliveries = SupportIntegrationDelivery::whereHas('event', function ($q) use ($storeId) {
                $q->where('store_id', $storeId);
            })->count();
        $totalAiRuns = SupportAiRun::where('store_id', $storeId)->count();

        $totalRecords = $totalDispatches + $totalDeliveries + $totalAiRuns;
        if ($totalRecords === 0) {
            return [
                'backpressure' => false,
                'status' => 'unknown',
                'reason' => 'Sistemde henüz kuyruk veya çalıştırma verisi bulunmamaktadır.',
            ];
        }

        // 1. Pending dispatch count
        $pendingDispatchCount = SupportDispatch::whereHas('conversation', function ($q) use ($storeId) {
                $q->where('store_id', $storeId);
            })
            ->whereIn('status', ['pending', 'sending'])
            ->count();

        // 2. Dead-letter/failed deliveries count (attempts >= 3)
        $deadLetterCount = SupportIntegrationDelivery::whereHas('event', function ($q) use ($storeId) {
                $q->where('store_id', $storeId);
            })
            ->where('status', 'dead_letter')
            ->count();

        // 3. AI Run error count (failures in last 15 minutes)
        $failedAiRuns = SupportAiRun::where('store_id', $storeId)
            ->where('status', 'failed')
            ->where('created_at', '>=', now()->subMinutes(15))
            ->count();

        // Eşiklerin Kontrolü
        if ($pendingDispatchCount >= 50) {
            return [
                'backpressure' => true,
                'status' => 'backpressure',
                'reason' => "Backpressure Active: Kuyrukta biriken mesaj sayısı ({$pendingDispatchCount}) kritik eşiği (50) aştı."
            ];
        }

        if ($deadLetterCount >= 10) {
            return [
                'backpressure' => true,
                'status' => 'backpressure',
                'reason' => "Backpressure Active: Entegrasyon kuyruğundaki dead-letter sayısı ({$deadLetterCount}) kritik eşiği (10) aştı."
            ];
        }

        if ($failedAiRuns >= 20) {
            return [
                'backpressure' => true,
                'status' => 'backpressure',
                'reason' => "Backpressure Active: Son 15 dakikada başarısız olan AI çalıştırma sayısı ({$failedAiRuns}) kritik eşiği (20) aştı."
            ];
        }

        return [
            'backpressure' => false,
            'status' => 'healthy',
            'reason' => 'Kuyruklar normal durumda.',
        ];
    }
}
