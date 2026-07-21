<?php

namespace App\Modules\Hr\Core\Jobs;

use App\Models\LegalEntity;
use App\Modules\Hr\Core\Services\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

abstract class HrJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public int $tenantId;

    public function __construct()
    {
        $this->tenantId = app(TenantContext::class)->getId();
    }

    public function handle(): void
    {
        $tenant = LegalEntity::find($this->tenantId);

        if (!$tenant) {
            return;
        }

        app(TenantContext::class)->set($tenant);

        try {
            $this->execute();
        } finally {
            // İşlem sonunda tenant context temizlenir; queue worker bir sonraki işe
            // veya başka bir tenant'a ait veriyi sızdırmaz.
            app(TenantContext::class)->clear();
        }
    }

    abstract protected function execute(): void;
}
