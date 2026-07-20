<?php

namespace App\Modules\Hr\Core\Traits;

use App\Modules\Hr\Core\Services\HrAuditService;

trait HrAuditLoggable
{
    public static function bootHrAuditLoggable(): void
    {
        static::created(function ($model) {
            if (method_exists($model, 'auditCreated')) {
                $model->auditCreated();
            }
        });

        static::updated(function ($model) {
            if (method_exists($model, 'auditUpdated')) {
                $model->auditUpdated();
            }
        });

        static::deleted(function ($model) {
            if (method_exists($model, 'auditDeleted')) {
                $model->auditDeleted();
            }
        });
    }

    protected function logHrAudit(string $action, ?array $old = null, ?array $new = null): void
    {
        app(HrAuditService::class)->log($action, $this, $old, $new);
    }
}
