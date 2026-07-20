<?php

namespace App\Modules\Hr\Core\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class HrBasePolicy
{
    use HandlesAuthorization;

    protected function checkPermission(User $user, string $permission): bool
    {
        return $user->hasHrPermission($permission);
    }

    protected function checkAnyPermission(User $user, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($user->hasHrPermission($permission)) {
                return true;
            }
        }
        return false;
    }
}
