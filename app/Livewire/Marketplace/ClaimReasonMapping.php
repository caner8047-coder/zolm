<?php

namespace App\Livewire\Marketplace;

use App\Enums\ZolmClaimReason;
use App\Models\MarketplaceStore;
use App\Models\MpClaimReason;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

class ClaimReasonMapping extends Component
{
    use WithPagination;

    public int $selectedStoreId = 0;
    
    // Standard table columns
    public array $visibleColumns = ['platform_reason_id', 'name', 'mapped_zolm_reason_code', 'is_active', 'updated_at'];
    public string $sortBy = 'name';
    public string $sortDir = 'asc';

    public static array $sortableColumns = [
        'platform_reason_id' => 'platform_reason_id',
        'name' => 'name',
        'mapped_zolm_reason_code' => 'mapped_zolm_reason_code',
        'updated_at' => 'updated_at',
    ];

    public static array $allColumnDefs = [
        'platform_reason_id' => 'Platform ID',
        'name' => 'Trendyol İade Nedeni',
        'mapped_zolm_reason_code' => 'ZOLM İç Nedeni',
        'is_active' => 'Durum',
        'updated_at' => 'Son Güncelleme',
    ];

    /**
     * Computed property: get ZOLM reason options from central Enum.
     */
    public function getZolmReasonsProperty(): array
    {
        return ZolmClaimReason::options();
    }

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
            $exists = $this->resolveStore() !== null;
            if (! $exists) {
                $this->selectedStoreId = 0;
            }
        }
    }

    /**
     * Resolve the store and ensure it belongs to the authenticated user.
     * Returns null if the store is not owned by the current user.
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

    public function toggleColumn(string $column): void
    {
        if (in_array($column, $this->visibleColumns)) {
            $this->visibleColumns = array_values(array_diff($this->visibleColumns, [$column]));
        } else {
            $this->visibleColumns[] = $column;
        }
    }

    public function sortTable(string $column): void
    {
        if (! isset(self::$sortableColumns[$column])) {
            return;
        }

        if ($this->sortBy === self::$sortableColumns[$column]) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = self::$sortableColumns[$column];
            $this->sortDir = 'asc';
        }

        $this->resetPage();
    }

    /**
     * Update the ZOLM reason mapping for a claim reason.
     * Security: validates enum value, scopes record to user's store, logs audit trail.
     */
    public function updateMapping(int $reasonId, string|null $zolmCode): void
    {
        // Authorization: operator veya üstü gerekli
        if (! auth()->user()?->isOperator()) {
            $this->dispatch('toast', ['type' => 'error', 'message' => 'Bu işlem için yetkiniz yok.']);
            return;
        }

        // Feature flag kontrolü
        if (! config('marketplace.trendyol.reference_sync_enabled', false)) {
            $this->dispatch('toast', ['type' => 'error', 'message' => 'İade nedeni özelliği şu an devre dışı.']);
            return;
        }

        // Enum validation: boş string veya null mapping kaldırma anlamına gelir
        $normalizedCode = ($zolmCode === '' || $zolmCode === null) ? null : $zolmCode;

        if ($normalizedCode !== null) {
            $validValues = array_map(fn ($case) => $case->value, ZolmClaimReason::cases());
            if (! in_array($normalizedCode, $validValues, true)) {
                $this->dispatch('toast', ['type' => 'error', 'message' => 'Geçersiz iade nedeni kodu.']);
                throw ValidationException::withMessages(['zolm_code' => 'Geçersiz değer.']);
            }
        }

        // Store sahiplik doğrulaması
        $store = $this->resolveStore();
        if (! $store) {
            $this->dispatch('toast', ['type' => 'error', 'message' => 'Mağaza bulunamadı veya erişim yetkiniz yok.']);
            return;
        }

        // Claim reason IDOR koruması: store_id + user'a ait olmak zorunda
        $reason = MpClaimReason::where('id', $reasonId)
            ->where('store_id', $store->id)
            ->first();

        if (! $reason) {
            $this->dispatch('toast', ['type' => 'error', 'message' => 'Kayıt bulunamadı.']);
            return;
        }

        $oldCode = $reason->mapped_zolm_reason_code;

        // Audit log kaydı
        \Illuminate\Support\Facades\Log::info('[ClaimReasonMapping] Mapping güncellendi', [
            'user_id' => auth()->id(),
            'store_id' => $store->id,
            'claim_reason_id' => $reason->id,
            'platform_reason_id' => $reason->platform_reason_id,
            'old_value' => $oldCode,
            'new_value' => $normalizedCode,
        ]);

        $reason->mapped_zolm_reason_code = $normalizedCode;
        $reason->save();

        $message = $normalizedCode
            ? 'Eşleştirme kaydedildi: ' . (ZolmClaimReason::from($normalizedCode)->label())
            : 'Eşleştirme kaldırıldı.';

        $this->dispatch('toast', ['type' => 'success', 'message' => $message]);
    }

    public function render()
    {
        $stores = MarketplaceStore::where('marketplace', 'trendyol')
            ->where('user_id', auth()->id())
            ->get();

        $reasons = collect();
        if ($this->selectedStoreId && $this->resolveStore()) {
            $reasons = MpClaimReason::where('store_id', $this->selectedStoreId)
                ->orderBy($this->sortBy, $this->sortDir)
                ->paginate(25);
        }

        return view('livewire.marketplace.claim-reason-mapping', [
            'stores' => $stores,
            'reasons' => $reasons,
            'featureEnabled' => config('marketplace.trendyol.reference_sync_enabled', false),
        ])->layout('layouts.app');
    }
}
