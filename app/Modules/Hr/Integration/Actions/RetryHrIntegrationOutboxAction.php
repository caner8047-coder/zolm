<?php

namespace App\Modules\Hr\Integration\Actions;

use App\Modules\Hr\Core\Models\HrIntegrationOutbox;
use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\TenantContext;
use Illuminate\Support\Facades\DB;

class RetryHrIntegrationOutboxAction
{
    public function __construct(private HrAuditService $audit) {}

    public function execute(HrIntegrationOutbox $outbox): HrIntegrationOutbox
    {
        abort_unless(auth()->user()?->hasHrPermission('hr.integrations.manage'), 403);
        abort_unless($outbox->legal_entity_id === app(TenantContext::class)->getId(), 404);

        return DB::transaction(function () use ($outbox) {
            $locked = HrIntegrationOutbox::withoutGlobalScope('tenant')->whereKey($outbox->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'failed', 422, 'Yalnız başarısız entegrasyon kayıtları yeniden kuyruğa alınabilir.');
            $locked->update(['status' => 'pending', 'processed_at' => null, 'last_error' => null]);
            $this->audit->log('hr_integration_requeued', $locked, ['status' => 'failed'], ['status' => 'pending']);

            return $locked->fresh();
        });
    }
}
