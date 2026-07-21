<?php

namespace App\Modules\Hr\Leave\Enums;

enum LeaveApprovalStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Skipped = 'skipped';
}
