<?php

namespace App\Modules\Hr\Document\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EmployeeDocumentVerified
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $legalEntityId,
        public readonly int $employeeDocumentId,
        public readonly int $employeeId,
        public readonly ?int $actorUserId,
    ) {}
}
