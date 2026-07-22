<?php

namespace App\Services\Marketplace;

use App\Models\MarketplaceStore;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;

class MarketplaceStoreAccessResolver
{
    /**
     * Get accessible stores for the given actor
     */
    public function accessibleStores(User $actor): Builder
    {
        if ($actor->is_active === false) {
            return MarketplaceStore::whereRaw('1 = 0');
        }

        $query = MarketplaceStore::query();

        if (!$actor->isOperator()) {
            return $query->where('user_id', $actor->id);
        }

        // Operator / Admin
        return $query->where(function (Builder $q) use ($actor) {
            $q->where('user_id', $actor->id);

            if (MarketplaceTenantContext::hasActiveContext()) {
                $targetStoreId = MarketplaceTenantContext::getTargetStoreId();
                if ($targetStoreId) {
                    $q->orWhere('id', $targetStoreId);
                }
            }
        });
    }

    /**
     * Resolve store for view action
     */
    public function resolveForView(User $actor, int $storeId): MarketplaceStore
    {
        $store = MarketplaceStore::find($storeId);
        if (!$store) {
            throw new AuthorizationException('Mağaza bulunamadı.');
        }

        if ((int) $store->user_id === (int) $actor->id) {
            return $store;
        }

        if ($actor->isOperator() && MarketplaceTenantContext::validateContext($storeId)) {
            return $store;
        }

        throw new AuthorizationException('Bu mağaza verisine erişim yetkiniz bulunmamaktadır.');
    }

    /**
     * Resolve store for credential management action
     */
    public function resolveForCredentialManagement(User $actor, int $storeId): MarketplaceStore
    {
        $store = MarketplaceStore::find($storeId);
        if (!$store) {
            throw new AuthorizationException('Mağaza bulunamadı.');
        }

        // Owner access
        if ((int) $store->user_id === (int) $actor->id) {
            return $store;
        }

        // Cross-tenant operator/admin access
        if ($actor->isOperator()) {
            if (!MarketplaceTenantContext::validateContext($storeId)) {
                throw new AuthorizationException('Bu mağaza için geçerli bir tenant context bulunmuyor.');
            }

            if (MarketplaceTenantContext::getReason() !== 'credential maintenance') {
                throw new AuthorizationException('Geçersiz tenant context nedeni.');
            }

            return $store;
        }

        throw new AuthorizationException('Mağaza bağlantı bilgilerini yönetmek için yetkiniz bulunmamaktadır.');
    }
}
