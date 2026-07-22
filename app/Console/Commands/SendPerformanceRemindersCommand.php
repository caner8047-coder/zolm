<?php

namespace App\Console\Commands;

use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Performance\Enums\PerformanceCycleStatus;
use App\Modules\Hr\Performance\Models\HrPerformanceCycle;
use App\Modules\Hr\Performance\Services\PerformanceReminderService;
use Illuminate\Console\Command;

class SendPerformanceRemindersCommand extends Command
{
    protected $signature = 'hr:performance-reminders';
    protected $description = 'Yaklaşan performans değerlendirmeleri için idempotent hatırlatmalar gönderir';

    public function handle(PerformanceReminderService $reminders, TenantContext $context): int
    {
        $cycles = HrPerformanceCycle::withoutGlobalScope('tenant')
            ->where('status', PerformanceCycleStatus::Evaluation->value)
            ->where('auto_reminders', true)
            ->whereDate('evaluation_starts_on', '<=', today())
            ->whereDate('evaluation_ends_on', '>=', today())
            ->get();
        $sent = 0;
        foreach ($cycles as $cycle) {
            $daysLeft = today()->diffInDays($cycle->evaluation_ends_on, false);
            if (! in_array($daysLeft, $cycle->reminder_days_before ?? [7, 3, 1], true)) continue;
            $context->set($cycle->legal_entity_id);
            try {
                $sent += $reminders->send($cycle);
            } finally {
                $context->clear();
            }
        }
        $this->info("{$sent} performans hatırlatması gönderildi.");

        return self::SUCCESS;
    }
}
