<?php

namespace App\Jobs;

use App\Models\TrendyolBoosterReviewSync;
use App\Services\Marketplace\TrendyolBoosterReviewService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class TrendyolBoosterReviewSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 1800;

    public $tries = 1;

    public function __construct(
        public int $syncRunId,
    ) {}

    public function handle(TrendyolBoosterReviewService $reviewService): void
    {
        $syncRun = TrendyolBoosterReviewSync::find($this->syncRunId);

        if (! $syncRun || $syncRun->isCompleted()) {
            return;
        }

        try {
            if ($syncRun->created_at?->isAfter(now()->subMinutes(29))) {
                return;
            }

            $reviewService->completeSyncRun(
                $this->syncRunId,
                'Senkronizasyon zaman aşımına uğradı (30 dk).',
                (int) $syncRun->user_id,
            );
        } catch (\Exception $e) {
            $reviewService->completeSyncRun($this->syncRunId, $e->getMessage(), (int) $syncRun->user_id);
        }
    }

    public function failed(\Throwable $exception): void
    {
        $reviewService = app(TrendyolBoosterReviewService::class);
        $syncRun = TrendyolBoosterReviewSync::find($this->syncRunId);

        $reviewService->completeSyncRun(
            $this->syncRunId,
            'Job hatası: '.$exception->getMessage(),
            $syncRun ? (int) $syncRun->user_id : null,
        );
    }
}
