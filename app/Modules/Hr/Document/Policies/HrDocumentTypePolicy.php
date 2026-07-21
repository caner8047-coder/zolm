<?php

namespace App\Modules\Hr\Document\Policies;

use App\Models\User;
use App\Modules\Hr\Core\Policies\HrBasePolicy;

class HrDocumentTypePolicy extends HrBasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->checkPermission($user, 'hr.documents.view') || $this->checkPermission($user, 'hr.documents.manage_types');
    }

    public function create(User $user): bool
    {
        return $this->checkPermission($user, 'hr.documents.manage_types');
    }

    public function update(User $user): bool
    {
        return $this->checkPermission($user, 'hr.documents.manage_types');
    }
}
