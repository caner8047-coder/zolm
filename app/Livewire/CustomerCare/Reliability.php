<?php

namespace App\Livewire\CustomerCare;

use Livewire\Component;
use App\Models\SupportDispatch;
use App\Models\SupportIntegrationDelivery;
use App\Services\Support\Reliability\CustomerCareQueueHealthService;
use App\Services\Support\Security\SupportRbacService;
use Illuminate\Support\Facades\Artisan;
use App\Livewire\CustomerCare\Concerns\ResolvesAccessibleStores;

class Reliability extends Component
{
    use ResolvesAccessibleStores;

    public int $selectedStoreId = 0;
    public string $errorMessage = '';
    public string $successMessage = '';

    protected $queryString = ['selectedStoreId'];

    public function mount()
    {
        if (!config('customer-care.reliability_enabled', false)) {
            abort(404);
        }

        $user = auth()->user();
        if (!$user || !in_array($user->role, ['admin', 'operator'], true)) {
            abort(403);
        }

        $this->resolveAccessibleStores();
    }

    public function replayAllExhausted()
    {
        $this->enforceSelectedStoreAccess();
        $rbac = app(SupportRbacService::class);
        $user = auth()->user();

        try {
            $rbac->enforcePermission($user, $this->selectedStoreId, 'force_circuit_breaker');
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
            return;
        }

        // If governance enabled, enforce risk approval
        try {
            $rbac->enforceApproval($user, $this->selectedStoreId, 'replay_deadletters', [
                'type' => 'dispatch'
            ]);
        } catch (\App\Exceptions\ApprovalRequiredException $e) {
            $this->successMessage = $e->getMessage() . ' Onaylandıktan sonra tekrar tetikleyebilirsiniz.';
            return;
        }

        $exitCode = Artisan::call('customer-care:replay-deadletters', [
            '--store' => $this->selectedStoreId,
            '--type' => 'dispatch',
            '--execute' => true,
        ]);

        if ($exitCode === 0) {
            $this->successMessage = 'Tüm başarısız gönderimler (exhausted dispatches) yeniden kuyruğa alındı ve tetiklendi.';
        } else {
            $this->errorMessage = 'Gönderim replay işlemi başarısız oldu.';
        }
    }

    public function replayAllWebhooks()
    {
        $this->enforceSelectedStoreAccess();
        $rbac = app(SupportRbacService::class);
        $user = auth()->user();

        try {
            $rbac->enforcePermission($user, $this->selectedStoreId, 'force_circuit_breaker');
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
            return;
        }

        // If governance enabled, enforce risk approval
        try {
            $rbac->enforceApproval($user, $this->selectedStoreId, 'replay_deadletters', [
                'type' => 'integration'
            ]);
        } catch (\App\Exceptions\ApprovalRequiredException $e) {
            $this->successMessage = $e->getMessage() . ' Onaylandıktan sonra tekrar tetikleyebilirsiniz.';
            return;
        }

        $exitCode = Artisan::call('customer-care:replay-deadletters', [
            '--store' => $this->selectedStoreId,
            '--type' => 'integration',
            '--execute' => true,
        ]);

        if ($exitCode === 0) {
            $this->successMessage = 'Tüm dead-letter webhook teslimatları başarıyla yeniden gönderildi.';
        } else {
            $this->errorMessage = 'Webhook replay işlemi başarısız oldu.';
        }
    }

    public function render()
    {
        $stores = $this->resolveAccessibleStores();

        // Queue counts
        $pendingDispatches = SupportDispatch::whereHas('conversation', function ($q) {
                $q->where('store_id', $this->selectedStoreId);
            })
            ->whereIn('status', ['pending', 'sending'])
            ->count();

        $exhaustedDispatches = SupportDispatch::whereHas('conversation', function ($q) {
                $q->where('store_id', $this->selectedStoreId);
            })
            ->where('status', 'exhausted')
            ->count();

        $deadLetters = SupportIntegrationDelivery::whereHas('event', function ($q) {
                $q->where('store_id', $this->selectedStoreId);
            })
            ->where('status', 'dead_letter')
            ->count();

        // Backpressure state
        $healthService = app(CustomerCareQueueHealthService::class);
        $backpressure = $healthService->checkBackpressure($this->selectedStoreId);

        // Rate limits doluluk oranları
        $limits = config('customer-care.rate_limits', [
            'whatsapp' => ['max_attempts' => 100, 'decay_seconds' => 3600],
            'trendyol' => ['max_attempts' => 50, 'decay_seconds' => 3600],
            'hepsiburada' => ['max_attempts' => 50, 'decay_seconds' => 3600],
            'n11' => ['max_attempts' => 50, 'decay_seconds' => 3600],
            'meta' => ['max_attempts' => 100, 'decay_seconds' => 3600],
            'google_reviews' => ['max_attempts' => 30, 'decay_seconds' => 3600],
            'web_chat' => ['max_attempts' => 200, 'decay_seconds' => 3600],
        ]);

        $channelUsage = [];
        $channelAliases = [
            'meta' => ['meta', 'meta_social', 'instagram', 'facebook'],
            'google_reviews' => ['google', 'google_reviews', 'google_business'],
        ];
        foreach ($limits as $chan => $lim) {
            $since = now()->subSeconds($lim['decay_seconds']);
            $keys = $channelAliases[$chan] ?? [$chan];
            $count = SupportDispatch::whereHas('channel', function ($q) use ($keys) {
                    $q->whereIn('key', $keys);
                })
                ->whereHas('conversation', function ($q) {
                    $q->where('store_id', $this->selectedStoreId);
                })
                ->where('created_at', '>=', $since)
                ->count();

            $channelUsage[$chan] = [
                'sent' => $count,
                'max' => $lim['max_attempts'],
                'percentage' => min(100, ($count / $lim['max_attempts']) * 100),
            ];
        }

        return view('livewire.customer-care.reliability', [
            'stores' => $stores,
            'pendingDispatches' => $pendingDispatches,
            'exhaustedDispatches' => $exhaustedDispatches,
            'deadLetters' => $deadLetters,
            'backpressure' => $backpressure,
            'channelUsage' => $channelUsage,
        ])->layout('layouts.app');
    }
}
