<?php

namespace App\Modules\Hr\Leave\Policies;

use App\Models\User;
use App\Modules\Hr\Core\Policies\HrBasePolicy;
use App\Modules\Hr\Leave\Models\HrLeaveType;

class HrLeaveTypePolicy extends HrBasePolicy
{
    public function viewAny(User $user): bool { return $this->checkAnyPermission($user, ['hr.leaves.view', 'hr.leaves.manage_type']); }
    public function create(User $user): bool { return $this->checkPermission($user, 'hr.leaves.manage_type'); }
    public function update(User $user, HrLeaveType $type): bool { return $this->checkPermission($user, 'hr.leaves.manage_type'); }
}
