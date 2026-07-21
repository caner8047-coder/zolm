<?php

namespace App\Services\Marketplace;

use Illuminate\Support\Facades\Session;

class MarketplaceTenantContext
{
    public const SESSION_KEY_ACTING_TENANT = 'marketplace_acting_tenant_user_id';
    public const SESSION_KEY_IMPERSONATED_STORE = 'marketplace_impersonated_store_id';
    public const SESSION_KEY_REASON = 'marketplace_acting_reason';
    public const SESSION_KEY_EXPIRES_AT = 'marketplace_acting_context_expires_at';

    public static function setContext(int $targetStoreId, int $targetTenantUserId, string $reason, int $durationSeconds = 900): void
    {
        if (empty(trim($reason))) {
            throw new \InvalidArgumentException('Tenant context creation requires a mandatory reason.');
        }

        Session::put(self::SESSION_KEY_ACTING_TENANT, $targetTenantUserId);
        Session::put(self::SESSION_KEY_IMPERSONATED_STORE, $targetStoreId);
        Session::put(self::SESSION_KEY_REASON, $reason);
        Session::put(self::SESSION_KEY_EXPIRES_AT, time() + $durationSeconds);
    }

    public static function clearContext(): void
    {
        Session::forget(self::SESSION_KEY_ACTING_TENANT);
        Session::forget(self::SESSION_KEY_IMPERSONATED_STORE);
        Session::forget(self::SESSION_KEY_REASON);
        Session::forget(self::SESSION_KEY_EXPIRES_AT);
    }

    public static function hasActiveContext(): bool
    {
        if (!Session::has(self::SESSION_KEY_ACTING_TENANT) ||
            !Session::has(self::SESSION_KEY_IMPERSONATED_STORE) ||
            !Session::has(self::SESSION_KEY_EXPIRES_AT)) {
            return false;
        }

        if (self::isExpired()) {
            self::clearContext();
            return false;
        }

        return true;
    }

    public static function getTargetStoreId(): ?int
    {
        if (!self::hasActiveContext()) {
            return null;
        }
        return Session::get(self::SESSION_KEY_IMPERSONATED_STORE);
    }

    public static function getTargetTenantUserId(): ?int
    {
        if (!self::hasActiveContext()) {
            return null;
        }
        return Session::get(self::SESSION_KEY_ACTING_TENANT);
    }

    public static function getReason(): ?string
    {
        if (!self::hasActiveContext()) {
            return null;
        }
        return Session::get(self::SESSION_KEY_REASON);
    }

    public static function validateContext(int $storeId): bool
    {
        if (!self::hasActiveContext()) {
            return false;
        }

        return (int) self::getTargetStoreId() === (int) $storeId;
    }

    public static function isExpired(): bool
    {
        $expiresAt = Session::get(self::SESSION_KEY_EXPIRES_AT);
        if (!$expiresAt) {
            return true;
        }

        return time() > $expiresAt;
    }
}
