<?php

namespace App\Modules\Hr\Document\Events;

use App\Modules\Hr\Document\Models\HrEmployeeDocument;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EmployeeDocumentUploaded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $legalEntityId,
        public readonly int $employeeDocumentId,
        public readonly int $employeeId,
        public readonly ?int $actorUserId,
        public readonly ?int $documentTypeId = null,
    ) {}
}
