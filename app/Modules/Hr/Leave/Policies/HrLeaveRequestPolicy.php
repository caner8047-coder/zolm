<?php

namespace App\Modules\Hr\Leave\Policies;

use App\Models\User;
use App\Modules\Hr\Core\Policies\HrBasePolicy;
use App\Modules\Hr\Leave\Models\HrLeaveRequest;

class HrLeaveRequestPolicy extends HrBasePolicy
{
    public function view(User $user, HrLeaveRequest $request): bool
    {
        return $this->checkPermission($user, 'hr.leaves.view');
    }

    public function create(User $user): bool { return $this->checkPermission($user, 'hr.leaves.create'); }
    public function approve(User $user, HrLeaveRequest $request): bool { return $this->checkPermission($user, 'hr.leaves.approve'); }
    public function manageBalance(User $user): bool { return $this->checkPermission($user, 'hr.leaves.manage_balance'); }
}
