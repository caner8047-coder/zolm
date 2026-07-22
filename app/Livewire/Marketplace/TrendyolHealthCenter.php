<?php

namespace App\Livewire\Marketplace;

use App\Models\MarketplaceStore;
use App\Models\IntegrationSyncRun;
use App\Services\Marketplace\MarketplaceManualSyncDispatchService;
use App\Services\Marketplace\TrendyolHealthStatusResolver;
use Livewire\Component;

class TrendyolHealthCenter extends Component
{
    public int $selectedStoreId = 0;

    /** @var array<string, mixed> */
    public array $metrics = [];

    /** Onay dialog'u için açık olan sync tipi */
    public ?string $confirmingSyncType = null;

    public function mount(): void
    {
        $store = MarketplaceStore::where('marketplace', 'trendyol')
            ->where('user_id', auth()->id())
            ->first();

        $this->selectedStoreId = $store?->id ?? 0;
    }

    public function updatedSelectedStoreId(): void
    {
        if ($this->selectedStoreId) {
            $store = $this->resolveStore();
            if (! $store) {
                $this->selectedStoreId = 0;
            }
        }
        $this->metrics = [];
    }

    /**
     * Store sahipliği doğrulaması — her action'da kullanılır.
     */
    protected function resolveStore(): ?MarketplaceStore
    {
        if (! $this->selectedStoreId) {
            return null;
        }

        return MarketplaceStore::where('id', $this->selectedStoreId)
            ->where('user_id', auth()->id())
            ->first();
    }

    /**
     * Onay dialogunu aç.
     */
    public function confirmSync(string $syncType): void
    {
        if (! auth()->user()?->isOperator()) {
            $this->dispatch('toast', ['type' => 'error', 'message' => 'Yetkiniz yok.']);
            return;
        }

        $this->confirmingSyncType = $syncType;
    }

    /**
     * Onay iptal.
     */
    public function cancelSync(): void
    {
        $this->confirmingSyncType = null;
    }

    /**
     * Manuel senkronizasyon dispatch — duplicate job engelleme, feature flag ve yetki kontrolü dahil.
     */
    public function dispatchManualSync(MarketplaceManualSyncDispatchService $dispatchService): void
    {
        $syncType = $this->confirmingSyncType;
        $this->confirmingSyncType = null;

        if (! $syncType) {
            return;
        }

        // Authorization
        if (! auth()->user()?->isOperator()) {
            $this->dispatch('toast', ['type' => 'error', 'message' => 'Yetkiniz yok.']);
            return;
        }

        // Feature flag map
        $flagMap = [
            'orders' => 'marketplace.trendyol.order_stream_enabled',
            'buybox' => 'marketplace.trendyol.buybox_sync_enabled',
            'cargo_invoice' => 'marketplace.trendyol.cargo_invoice_sync_enabled',
            'reference' => 'marketplace.trendyol.reference_sync_enabled',
            'batch' => 'marketplace.trendyol.batch_tracking_enabled',
        ];

        $flagKey = $flagMap[$syncType] ?? null;
        if ($flagKey && ! config($flagKey, false)) {
            $this->dispatch('toast', ['type' => 'error', 'message' => 'Bu özellik şu an devre dışı.']);
            return;
        }

        $store = $this->resolveStore();
        if (! $store) {
            $this->dispatch('toast', ['type' => 'error', 'message' => 'Mağaza bulunamadı.']);
            return;
        }

        try {
            $result = $dispatchService->dispatch($store, $syncType, ['trigger' => 'manual_health_center']);

            if ($result['debounced']) {
                $reason = $result['reason'] === 'active'
                    ? 'Senkronizasyon zaten çalışıyor.'
                    : 'Kısa süre önce senkronizasyon yapıldı; lütfen bekleyin.';
                $this->dispatch('toast', ['type' => 'warning', 'message' => $reason]);
            } else {
                $this->dispatch('toast', ['type' => 'success', 'message' => 'Senkronizasyon kuyruğa eklendi.']);
            }
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function render(TrendyolHealthStatusResolver $resolver)
    {
        $stores = MarketplaceStore::where('marketplace', 'trendyol')
            ->where('user_id', auth()->id())
            ->get();

        $metrics = null;
        $recentRuns = collect();
        $store = $this->resolveStore();

        if ($store) {
            $metrics = $resolver->resolve($store);
            $recentRuns = IntegrationSyncRun::where('store_id', $store->id)
                ->orderByDesc('created_at')
                ->limit(10)
                ->get();
        }

        return view('livewire.marketplace.trendyol-health-center', [
            'stores' => $stores,
            'metrics' => $metrics,
            'recentRuns' => $recentRuns,
            'flags' => [
                'order_stream' => config('marketplace.trendyol.order_stream_enabled', true),
                'buybox' => config('marketplace.trendyol.buybox_sync_enabled', false),
                'cargo_invoice' => config('marketplace.trendyol.cargo_invoice_sync_enabled', false),
                'reference' => config('marketplace.trendyol.reference_sync_enabled', false),
                'batch' => config('marketplace.trendyol.batch_tracking_enabled', false),
            ],
        ])->layout('layouts.app');
    }
}
