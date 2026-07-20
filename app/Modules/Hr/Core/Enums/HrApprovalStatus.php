<?php

namespace App\Modules\Hr\Core\Enums;

enum HrApprovalStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Revision = 'revision';
}
