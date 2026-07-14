<?php

namespace App\Livewire\CustomerCare\Concerns;

use App\Services\Support\CustomerCareOrganizationContext;
use App\Services\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;

trait ResolvesAccessibleStores
{
    /**
     * Mağaza seçicisini ve sorguları aynı tenant kapsamına sabitler.
     */
    protected function resolveAccessibleStores(): Collection
    {
        $user = auth()->user();
        if (!$user) {
            abort(403);
        }

        $stores = CustomerCareOrganizationContext::getAccessibleStores($user)->get();
        if ($this->selectedStoreId && !$stores->contains('id', (int) $this->selectedStoreId)) {
            throw new AuthorizationException('Bu mağazanın verilerine erişim yetkiniz yok.');
        }

        if (!$this->selectedStoreId) {
            $this->selectedStoreId = $stores->first()?->id ?? 0;
        }

        return $stores;
    }

    protected function enforceSelectedStoreAccess(): void
    {
        $user = auth()->user();
        if (!$user || !$this->selectedStoreId) {
            abort(403);
        }

        TenantContext::enforceStoreAccess((int) $this->selectedStoreId, $user);
    }
}
