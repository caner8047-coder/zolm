<?php

namespace App\Modules\Hr\Leave\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LeaveRequested
{
    use Dispatchable, SerializesModels;
    public function __construct(public readonly int $legalEntityId, public readonly int $leaveRequestId, public readonly int $employeeId, public readonly ?int $actorUserId) {}
}
