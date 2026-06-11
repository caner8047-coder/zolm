<?php

namespace App\Jobs;

use App\Models\IntegrationSyncRun;
use App\Services\Marketplace\MarketplaceSyncService;
use App\Services\Marketplace\Support\CircuitBreaker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncMarketplaceDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public int $tries = 3;

    /**
     * @var array<int>
     */
    public array $backoff = [30, 120, 300];

    public function __construct(public int $syncRunId)
    {
        $this->onQueue((string) config('marketplace.queues.sync', 'default'));
    }

    /**
     * Job middleware: aynı store + sync_type ikilisi için eş zamanlı çalışmayı önle.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        $run = IntegrationSyncRun::find($this->syncRunId);

        if (!$run) {
            return [];
        }

        return [
            (new WithoutOverlapping("marketplace-sync:{$run->store_id}:{$run->sync_type}"))
                ->releaseAfter(600)           // 10 dakika sonra lock serbest bırakılır
                ->expireAfter($this->timeout) // Lock timeout'u job timeout'una eşit
        ];
    }

    public function handle(MarketplaceSyncService $syncService, CircuitBreaker $circuitBreaker): void
    {
        $run = IntegrationSyncRun::find($this->syncRunId);

        if (!$run) {
            Log::warning('[SyncMarketplaceDataJob] Sync run bulunamadı, job atlanıyor.', [
                'sync_run_id' => $this->syncRunId,
            ]);

            return;
        }

        if (!in_array($run->status, ['queued', 'processing'], true)) {
            Log::info('[SyncMarketplaceDataJob] Sync run aktif değil, job atlanıyor.', [
                'sync_run_id' => $this->syncRunId,
                'status' => $run->status,
            ]);

            return;
        }

        // Circuit breaker kontrolü
        $state = $circuitBreaker->state($run->store_id, $run->sync_type);

        if ($state === 'open') {
            $inspection = $circuitBreaker->inspect($run->store_id, $run->sync_type);

            Log::warning('[SyncMarketplaceDataJob] Circuit breaker açık, sync atlanıyor.', [
                'sync_run_id' => $this->syncRunId,
                'store_id' => $run->store_id,
                'sync_type' => $run->sync_type,
                'circuit_state' => $state,
                'seconds_until_half_open' => $inspection['seconds_until_half_open'],
                'last_error' => $inspection['last_error'],
            ]);

            $run->forceFill([
                'status' => 'skipped',
                'finished_at' => now(),
                'notes_json' => array_merge($run->notes_json ?? [], [
                    'skipped_reason' => 'circuit_breaker_open',
                    'circuit_state' => $state,
                    'last_circuit_error' => $inspection['last_error'],
                ]),
            ])->save();

            return;
        }

        if ($state === 'half_open') {
            Log::info('[SyncMarketplaceDataJob] Circuit breaker half-open, deneme yapılıyor.', [
                'sync_run_id' => $this->syncRunId,
                'store_id' => $run->store_id,
                'sync_type' => $run->sync_type,
            ]);
        }

        try {
            $syncService->run($this->syncRunId);

            // Başarılıysa circuit breaker'ı sıfırla
            $circuitBreaker->recordSuccess($run->store_id, $run->sync_type);
        } catch (Throwable $exception) {
            // Hata kaydı
            $circuitBreaker->recordFailure(
                $run->store_id,
                $run->sync_type,
                $exception->getMessage(),
            );

            throw $exception;
        }
    }
}
