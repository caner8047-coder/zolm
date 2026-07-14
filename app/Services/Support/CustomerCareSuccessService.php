<?php

namespace App\Services\Support;

use App\Models\SupportSuccessSnapshot;
use App\Models\SupportSuccessTask;
use App\Models\SupportSuccessNote;
use App\Models\SupportLaunchPlan;
use App\Models\MarketplaceStore;
use App\Models\User;
use App\Services\Support\TenantContext;
use App\Services\Support\CustomerCareAiProviderHealthService;
use App\Services\Support\AI\CustomerCareGoldenEvalGateService;
use App\Services\Support\Reliability\CustomerCareQueueHealthService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;

class CustomerCareSuccessService
{
    /**
     * Bir mağaza için sağlık verilerini bellekte (in-memory) hesaplar.
     * DB'ye yazma/kaydetme yapmaz.
     */
    public function calculateSnapshotData(int $storeId, ?User $user = null): array
    {
        $user = $user ?? Auth::user();
        TenantContext::enforceStoreAccess($storeId, $user);

        $scores = [];
        $unknown = [];
        $tasks = [];

        // 1. Launch / Pilot plan durumu
        $launchPlan = SupportLaunchPlan::where('store_id', $storeId)
            ->orderByDesc('created_at')
            ->first();

        if ($launchPlan) {
            $scores['launch'] = match ($launchPlan->status) {
                'completed', 'canary', 'approved' => 90,
                'ready_for_approval', 'draft'     => 50,
                'rolled_back', 'readiness_failed' => 20,
                default                           => 40,
            };
        } else {
            $unknown[] = 'launch';
        }

        // 2. Golden eval skoru
        $evalEvidence = app(CustomerCareGoldenEvalGateService::class)->evaluate($storeId, 'tr');
        if ($evalEvidence['run']) {
            $scores['eval'] = $evalEvidence['passed']
                ? (int) $evalEvidence['metrics']['average_score']
                : 0;

            if (!$evalEvidence['passed']) {
                $tasks[] = [
                    'type' => 'golden_eval_refresh',
                    'description' => 'Golden eval kanıtı geçersiz veya eşik altında: ' . $evalEvidence['detail'],
                ];
            }
        } else {
            $unknown[] = 'eval';
        }

        // 3. Queue health
        $queueService = app(CustomerCareQueueHealthService::class);
        $queueHealth = $queueService->checkBackpressure($storeId);
        if (isset($queueHealth['status'])) {
            $scores['queue'] = match ($queueHealth['status']) {
                'healthy'      => 95,
                'degraded'     => 50,
                'critical'     => 10,
                'backpressure' => 20,
                default        => null,
            };
            if (is_null($scores['queue'])) {
                unset($scores['queue']);
                $unknown[] = 'queue';
            }
            if (($queueHealth['status'] ?? 'unknown') !== 'healthy') {
                $tasks[] = ['type' => 'queue_backlog', 'description' => 'Kuyruk sağlığı: ' . ($queueHealth['status'] ?? 'unknown')];
            }
        } else {
            $unknown[] = 'queue';
        }

        // 4. Provider health
        $providerService = app(CustomerCareAiProviderHealthService::class);
        $defaultProvider = config('customer-care.default_ai_provider', 'gemini');
        try {
            $isHealthy = $providerService->isProviderHealthy($defaultProvider);
            $scores['provider'] = $isHealthy ? 100 : 30;
        } catch (\Throwable) {
            $unknown[] = 'provider';
        }

        // Ortalama skoru hesapla (sadece known bileşenlerden)
        $healthScore = null;
        $healthLabel = 'unknown';
        if (!empty($scores)) {
            $avg = (int) round(array_sum($scores) / count($scores));
            $healthScore = $avg;
            $healthLabel = match (true) {
                $avg >= 80 => 'healthy',
                $avg >= 50 => 'degraded',
                default    => 'critical',
            };
        }

        return [
            'health_score'       => $healthScore,
            'health_label'       => $healthLabel,
            'component_scores'   => $scores,
            'unknown_components' => $unknown,
            'tasks'              => $tasks,
        ];
    }

    /**
     * Bir mağaza için sağlık skorunu hesaplar ve snapshot üretip DB'ye kaydeder.
     */
    public function computeSnapshot(int $storeId, ?User $user = null): SupportSuccessSnapshot
    {
        $user = $user ?? Auth::user();
        $data = $this->calculateSnapshotData($storeId, $user);

        $snapshot = SupportSuccessSnapshot::create([
            'store_id'           => $storeId,
            'health_score'       => $data['health_score'],
            'health_label'       => $data['health_label'],
            'component_scores'   => $data['component_scores'],
            'unknown_components' => $data['unknown_components'],
            'computed_by'        => $user?->id,
            'computed_at'        => now(),
            'is_stale'           => false,
        ]);

        foreach ($data['tasks'] as $task) {
            SupportSuccessTask::create([
                'store_id'    => $storeId,
                'snapshot_id' => $snapshot->id,
                'task_type'   => $task['type'],
                'description' => $task['description'],
                'status'      => 'open',
            ]);
        }

        return $snapshot;
    }

    /**
     * Görevi kapatır — append-only: kapatma tarihi ve kullanıcı kaydedilir.
     */
    public function resolveTask(int $taskId, User $user): SupportSuccessTask
    {
        $task = SupportSuccessTask::findOrFail($taskId);
        TenantContext::enforceStoreAccess($task->store_id, $user);

        if ($task->status === 'resolved') {
            throw new \RuntimeException('Bu görev zaten kapatılmış.');
        }

        $task->update([
            'status'      => 'resolved',
            'resolved_by' => $user->id,
            'resolved_at' => now(),
        ]);

        return $task->fresh();
    }

    /**
     * KVKK: Not ekler; e-posta ve TCKN'yi maskeler, şifreler.
     */
    public function addNote(int $storeId, User $user, string $rawBody): SupportSuccessNote
    {
        TenantContext::enforceStoreAccess($storeId, $user);
        return SupportSuccessNote::createRedacted($storeId, $user->id, $rawBody);
    }

    /**
     * Belirli bir mağaza için snapshot getirir; cross-store engellenir.
     */
    public function getLatestSnapshot(int $storeId, User $user): ?SupportSuccessSnapshot
    {
        TenantContext::enforceStoreAccess($storeId, $user);
        return SupportSuccessSnapshot::where('store_id', $storeId)->latest()->first();
    }

    /**
     * Kullanıcının erişebildiği tüm mağazaların snapshot listesini döner.
     */
    public function getPortfolioSnapshots(User $user): array
    {
        $stores = MarketplaceStore::all()->filter(
            fn($s) => TenantContext::validateStoreAccess($s->id, $user)
        );

        $results = [];
        foreach ($stores as $store) {
            $snapshot = SupportSuccessSnapshot::where('store_id', $store->id)->latest()->first();
            $results[] = [
                'store'    => $store,
                'snapshot' => $snapshot,
            ];
        }

        return $results;
    }
}
