<?php

namespace App\Services\Support;

use App\Models\User;
use App\Models\MarketplaceStore;
use App\Models\SupportConversation;
use App\Models\SupportRoleAssignment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Auth\Access\AuthorizationException;

class TenantContext
{
    /**
     * Kullanıcının belirtilen mağazaya erişim yetkisi olup olmadığını doğrular.
     */
    public static function validateStoreAccess(int $storeId, ?User $user = null): bool
    {
        $user = $user ?? Auth::user();
        if (!$user || $user->is_active === false) {
            return false;
        }

        $store = MarketplaceStore::find($storeId);
        if (!$store) {
            return false;
        }

        // Admin rolündeki kullanıcılar veya explicit System Actor tüm mağaza verilerine erişebilir
        if ($user->role === 'admin' || $user->email === config('customer-care.system_actor_email')) {
            return true;
        }

        // Mağaza doğrudan kullanıcıya aitse veya legal entity sahipliği uyuşuyorsa yetkilidir
        if ((int) $store->user_id === (int) $user->id) {
            return true;
        }

        if (SupportRoleAssignment::where('store_id', $storeId)->where('user_id', $user->id)->exists()) {
            return true;
        }

        if ($store->legal_entity_id) {
            $legalEntity = $store->legalEntity;
            if ($legalEntity && (int) $legalEntity->user_id === (int) $user->id) {
                return true;
            }

            // Organizasyon üyelikleri ve aktif servis hesapları da mağaza erişim
            // sınırının parçasıdır. Listeleme ve servis katmanı aynı tenant
            // kararını vermelidir.
            if (CustomerCareOrganizationContext::validateOrganizationAccess((int) $store->legal_entity_id, $user)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Kullanıcının belirtilen birleşik konuşmaya erişim yetkisi olup olmadığını doğrular.
     */
    public static function validateConversationAccess(int $conversationId, ?User $user = null): bool
    {
        $user = $user ?? Auth::user();
        if (!$user) {
            return false;
        }

        $conversation = SupportConversation::find($conversationId);
        if (!$conversation) {
            return false;
        }

        // Konuşmanın store_id'sine erişimi var mı kontrol et
        if ($conversation->store_id) {
            return self::validateStoreAccess($conversation->store_id, $user);
        }

        return false;
    }

    /**
     * Erişim doğrulaması yapar, yetki yoksa istisna fırlatır.
     */
    public static function enforceStoreAccess(int $storeId, ?User $user = null): void
    {
        if (!self::validateStoreAccess($storeId, $user)) {
            throw new AuthorizationException('Bu mağaza verisine erişim yetkiniz bulunmamaktadır.');
        }
    }

    /**
     * Konuşma erişim doğrulaması yapar, yetki yoksa istisna fırlatır.
     */
    public static function enforceConversationAccess(int $conversationId, ?User $user = null): void
    {
        if (!self::validateConversationAccess($conversationId, $user)) {
            throw new AuthorizationException('Bu konuşmaya erişim yetkiniz bulunmamaktadır.');
        }
    }

    public static function getSystemActor(): User
    {
        $email = config('customer-care.system_actor_email');
        if (empty($email)) {
            throw new AuthorizationException('System actor email configuration is missing.');
        }

        $systemUser = User::where('email', $email)->where('is_active', true)->first();
        if (!$systemUser) {
            throw new AuthorizationException('System actor user not provisioned or active in the database: ' . $email);
        }

        return $systemUser;
    }
}
