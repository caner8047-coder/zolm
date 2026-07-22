<?php

namespace App\Modules\Hr\Performance\Actions;

use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Performance\Enums\PerformanceCycleStatus;
use App\Modules\Hr\Performance\Models\HrPerformanceCycle;
use App\Modules\Hr\Performance\Services\PerformanceReminderService;

class SendPerformanceRemindersAction
{
    public function __construct(private PerformanceReminderService $reminders) {}

    public function execute(HrPerformanceCycle $cycle, bool $force = false): int
    {
        abort_unless(auth()->user()?->hasHrPermission('hr.performance.manage_templates'), 403);
        abort_unless($cycle->legal_entity_id === app(TenantContext::class)->getId(), 404);
        abort_unless($cycle->status === PerformanceCycleStatus::Evaluation, 422, 'Yalnız değerlendirme aşamasındaki döngü için hatırlatma gönderilebilir.');
        return $this->reminders->send($cycle, $force);
    }
}
