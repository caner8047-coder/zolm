<?php

namespace App\Services\Support;

use App\Models\User;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\SupportOrganizationMembership;
use App\Models\SupportOrganizationSetting;
use App\Models\SupportServiceAccount;
use App\Models\SupportRoleAssignment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Auth\Access\AuthorizationException;

class CustomerCareOrganizationContext
{
    /**
     * Kullanıcının belirtilen organizasyona (legal entity) erişim yetkisi olup olmadığını doğrular.
     */
    public static function validateOrganizationAccess(int $legalEntityId, ?User $user = null): bool
    {
        $user = $user ?? Auth::user();
        if (!$user || $user->is_active === false) {
            return false;
        }

        $legalEntity = LegalEntity::find($legalEntityId);
        if (!$legalEntity) {
            return false;
        }

        // Global admin her organizasyona erişebilir
        if ($user->role === 'admin' || $user->email === config('customer-care.system_actor_email')) {
            return true;
        }

        // Doğrudan sahiplik kontrolü
        if ((int) $legalEntity->user_id === (int) $user->id) {
            return true;
        }

        // Membership kontrolü (Dalga AQ)
        $membership = SupportOrganizationMembership::where('legal_entity_id', $legalEntityId)
            ->where('user_id', $user->id)
            ->exists();

        if ($membership) {
            return true;
        }

        // Service Account kontrolü (Dalga AQ)
        $serviceAccount = SupportServiceAccount::where('legal_entity_id', $legalEntityId)
            ->where('email', $user->email)
            ->where('is_active', true)
            ->exists();

        if ($serviceAccount) {
            return true;
        }

        return false;
    }

    /**
     * Yetki doğrulaması yapar, yetki yoksa fırlatır (fail-closed).
     */
    public static function enforceOrganizationAccess(int $legalEntityId, ?User $user = null): void
    {
        if (!self::validateOrganizationAccess($legalEntityId, $user)) {
            throw new AuthorizationException('Bu organizasyon verisine erişim yetkiniz bulunmamaktadır.');
        }
    }

    /**
     * Bir store'un ait olduğu organizasyona (legal entity) erişimi doğrular.
     */
    public static function validateStoreOrganizationAccess(int $storeId, ?User $user = null): bool
    {
        $store = MarketplaceStore::find($storeId);
        if (!$store || !$store->legal_entity_id) {
            return false;
        }

        return self::validateOrganizationAccess($store->legal_entity_id, $user);
    }

    public static function enforceStoreOrganizationAccess(int $storeId, ?User $user = null): void
    {
        if (!self::validateStoreOrganizationAccess($storeId, $user)) {
            throw new AuthorizationException('Bu mağazanın bağlı olduğu organizasyon verisine erişim yetkiniz bulunmamaktadır.');
        }
    }

    /**
     * Organizasyona özel System Actor döner. Ayarlanmamışsa global fallback yerine fail-closed hata verir.
     */
    public static function getSystemActor(int $legalEntityId): User
    {
        $settings = SupportOrganizationSetting::where('legal_entity_id', $legalEntityId)->first();
        $email = $settings?->system_actor_email;

        // Sadece local veya testing ortamlarında global fallback yapılabilir
        if (empty($email)) {
            if (app()->environment('local', 'testing')) {
                $email = config('customer-care.system_actor_email');
            }
        }

        if (empty($email)) {
            throw new AuthorizationException("Bu organizasyon için özel System Actor tanımlanmamıştır.");
        }

        $systemUser = User::where('email', $email)->where('is_active', true)->first();
        if (!$systemUser) {
            throw new AuthorizationException("Sistem aktör kullanıcısı veritabanında aktif değil: " . $email);
        }

        return $systemUser;
    }

    /**
     * Kullanıcının service account olup olmadığını kontrol eder.
     */
    public static function isServiceAccount(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        return SupportServiceAccount::where('email', $user->email)->where('is_active', true)->exists();
    }

    /**
     * Kullanıcının erişebildiği organizasyonları (LegalEntity) döner.
     */
    public static function getAccessibleOrganizations(User $user): \Illuminate\Database\Eloquent\Builder
    {
        if ($user->is_active === false) {
            return LegalEntity::whereRaw('1 = 0');
        }

        if ($user->role === 'admin' || $user->email === config('customer-care.system_actor_email')) {
            return LegalEntity::query();
        }

        $ownedIds = LegalEntity::where('user_id', $user->id)->pluck('id')->toArray();

        $memberIds = SupportOrganizationMembership::where('user_id', $user->id)
            ->pluck('legal_entity_id')
            ->toArray();

        $saIds = SupportServiceAccount::where('email', $user->email)
            ->where('is_active', true)
            ->pluck('legal_entity_id')
            ->toArray();

        $allIds = array_unique(array_merge($ownedIds, $memberIds, $saIds));

        return LegalEntity::whereIn('id', $allIds);
    }

    /**
     * Kullanıcının erişebildiği mağazaları (MarketplaceStore) döner.
     */
    public static function getAccessibleStores(User $user): \Illuminate\Database\Eloquent\Builder
    {
        if ($user->is_active === false) {
            return MarketplaceStore::whereRaw('1 = 0');
        }

        if ($user->role === 'admin' || $user->email === config('customer-care.system_actor_email')) {
            return MarketplaceStore::query();
        }

        $orgIds = self::getAccessibleOrganizations($user)->pluck('id')->toArray();
        $roleStoreIds = SupportRoleAssignment::where('user_id', $user->id)
            ->pluck('store_id')
            ->toArray();

        return MarketplaceStore::where(function ($query) use ($orgIds, $roleStoreIds, $user) {
            $query->whereIn('legal_entity_id', $orgIds)
                  ->orWhereIn('id', $roleStoreIds)
                  ->orWhere('user_id', $user->id);
        });
    }

    /**
     * Bir mağazada rol atanabilecek kullanıcı havuzunu organizasyon sınırında döndürür.
     */
    public static function getAssignableUsersForStore(int $storeId, User $actor): \Illuminate\Support\Collection
    {
        TenantContext::enforceStoreAccess($storeId, $actor);
        $store = MarketplaceStore::with('legalEntity')->findOrFail($storeId);

        $userIds = collect([$store->user_id, $store->legalEntity?->user_id])
            ->filter()
            ->merge(SupportRoleAssignment::where('store_id', $storeId)->pluck('user_id'));

        if ($store->legal_entity_id) {
            $userIds = $userIds->merge(
                SupportOrganizationMembership::where('legal_entity_id', $store->legal_entity_id)->pluck('user_id')
            );

            $serviceEmails = SupportServiceAccount::where('legal_entity_id', $store->legal_entity_id)
                ->where('is_active', true)
                ->pluck('email');
            $userIds = $userIds->merge(User::whereIn('email', $serviceEmails)->pluck('id'));
        }

        return User::whereIn('id', $userIds->unique()->values())->orderBy('name')->get();
    }
}
