<?php

namespace App\Modules\Hr\Core\Traits;

use App\Modules\Hr\Core\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToLegalEntity
{
    public static function bootBelongsToLegalEntity(): void
    {
        static::addGlobalScope('tenant', function (Builder $query) {
            if (app()->bound(TenantContext::class)) {
                $tenantId = app(TenantContext::class)->getId();
                $query->where('legal_entity_id', $tenantId);
            }
        });
    }

    public function scopeForCurrentTenant(Builder $query): Builder
    {
        return $query->where('legal_entity_id', app(TenantContext::class)->getId());
    }

    public function scopeWithoutTenantScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope('tenant');
    }
}
