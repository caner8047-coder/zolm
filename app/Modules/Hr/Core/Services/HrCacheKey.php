<?php

namespace App\Modules\Hr\Core\Services;

class HrCacheKey
{
    public static function make(string $module, string $resource, ?string $identifier = null): string
    {
        $tenantId = app(TenantContext::class)->getId();

        $key = "hr:{$tenantId}:{$module}:{$resource}";

        if ($identifier) {
            $key .= ":{$identifier}";
        }

        return $key;
    }

    public static function remember(string $module, string $resource, \Closure $callback, ?string $identifier = null, int $ttl = 3600): mixed
    {
        $key = self::make($module, $resource, $identifier);

        return cache()->remember($key, $ttl, $callback);
    }

    public static function forget(string $module, string $resource, ?string $identifier = null): bool
    {
        $key = self::make($module, $resource, $identifier);

        return cache()->forget($key);
    }

    public static function flushModule(string $module): void
    {
        $tenantId = app(TenantContext::class)->getId();
        $prefix = "hr:{$tenantId}:{$module}";

        $keys = cache()->getStore() instanceof \Illuminate\Cache\Repository
            ? collect(cache()->getRedis()->keys("*{$prefix}*"))
            : collect();

        foreach ($keys as $key) {
            cache()->forget($key);
        }
    }
}
