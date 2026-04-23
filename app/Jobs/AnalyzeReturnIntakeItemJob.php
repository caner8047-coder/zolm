<?php

namespace App\Jobs;

use App\Models\ReturnIntakeItem;
use App\Services\Returns\ReturnIntakeProcessingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AnalyzeReturnIntakeItemJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Gemini API geçici hatalarda 3 kere dene.
     */
    public int $tries = 3;

    /**
     * Her yeniden denemede artan bekleme (saniye).
     *
     * @var array<int, int>
     */
    public array $backoff = [10, 30];

    /**
     * Gemini büyük görsellerde yavaş olabilir.
     */
    public int $timeout = 120;

    public function __construct(
        public int $returnIntakeItemId,
    ) {
    }

    public function handle(ReturnIntakeProcessingService $processingService): void
    {
        $item = ReturnIntakeItem::query()
            ->with('media')
            ->find($this->returnIntakeItemId);

        if (!$item) {
            Log::warning('[ReturnAnalysis] Item bulunamadı', ['id' => $this->returnIntakeItemId]);
            return;
        }

        // Zaten analiz edilmişse tekrar çalıştırma (duplicate dispatch koruması)
        if ($item->intake_status === 'decisioned') {
            return;
        }

        $processingService->process($item);
    }

    /**
     * Tüm retry'lar tükendikten sonra çağrılır.
     */
    public function failed(?\Throwable $exception): void
    {
        Log::error('[ReturnAnalysis] Kalıcı hata — 3 deneme de başarısız', [
            'item_id' => $this->returnIntakeItemId,
            'error' => $exception?->getMessage(),
        ]);

        ReturnIntakeItem::query()
            ->where('id', $this->returnIntakeItemId)
            ->whereIn('intake_status', ['queued', 'analyzing'])
            ->update([
                'intake_status' => 'failed',
                'analysis_completed_at' => now(),
                'last_error' => 'Analiz 3 deneme sonunda başarısız oldu: ' . ($exception?->getMessage() ?? 'Bilinmeyen hata'),
            ]);
    }
}
