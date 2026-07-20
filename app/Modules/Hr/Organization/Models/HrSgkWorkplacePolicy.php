<?php

namespace App\Modules\Hr\Organization\Models;

use App\Modules\Hr\Core\Policies\HrBasePolicy;
use App\Models\User;

class HrSgkWorkplacePolicy extends HrBasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->checkPermission($user, 'hr.org_structure.view');
    }

    public function view(User $user, HrSgkWorkplace $sgkWorkplace): bool
    {
        return $this->checkPermission($user, 'hr.org_structure.view');
    }

    public function create(User $user): bool
    {
        return $this->checkPermission($user, 'hr.org_structure.manage');
    }

    public function update(User $user, HrSgkWorkplace $sgkWorkplace): bool
    {
        return $this->checkPermission($user, 'hr.org_structure.manage');
    }

    public function delete(User $user, HrSgkWorkplace $sgkWorkplace): bool
    {
        return $this->checkPermission($user, 'hr.org_structure.manage');
    }
}
