<?php

namespace App\Services\Marketplace;

use Illuminate\Support\Facades\Session;

class MarketplaceTenantContext
{
    public const SESSION_KEY_ACTING_TENANT = 'marketplace_acting_tenant_user_id';
    public const SESSION_KEY_IMPERSONATED_STORE = 'marketplace_impersonated_store_id';
    public const SESSION_KEY_REASON = 'marketplace_acting_reason';

    public static function setContext(int $targetStoreId, int $targetTenantUserId, string $reason): void
    {
        Session::put(self::SESSION_KEY_ACTING_TENANT, $targetTenantUserId);
        Session::put(self::SESSION_KEY_IMPERSONATED_STORE, $targetStoreId);
        Session::put(self::SESSION_KEY_REASON, $reason);
    }

    public static function clearContext(): void
    {
        Session::forget(self::SESSION_KEY_ACTING_TENANT);
        Session::forget(self::SESSION_KEY_IMPERSONATED_STORE);
        Session::forget(self::SESSION_KEY_REASON);
    }

    public static function hasActiveContext(): bool
    {
        return Session::has(self::SESSION_KEY_ACTING_TENANT) 
            && Session::has(self::SESSION_KEY_IMPERSONATED_STORE);
    }

    public static function getTargetStoreId(): ?int
    {
        return Session::get(self::SESSION_KEY_IMPERSONATED_STORE);
    }

    public static function getTargetTenantUserId(): ?int
    {
        return Session::get(self::SESSION_KEY_ACTING_TENANT);
    }

    public static function getReason(): ?string
    {
        return Session::get(self::SESSION_KEY_REASON);
    }

    public static function validateContext(int $storeId): bool
    {
        if (!self::hasActiveContext()) {
            return false;
        }

        return (int) self::getTargetStoreId() === (int) $storeId;
    }
}
