<?php

namespace App\Modules\Hr\Core\Observers;

use App\Models\ActivityLog;

class HrAuditObserver
{
    public function created(ActivityLog $log): void
    {
        // HR modülünden gelen loglar için ek işlemler gerekirse burada yapılır
    }
}
