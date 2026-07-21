<?php

namespace App\Modules\Hr\Core\Policies;

use App\Models\HrFile;
use App\Models\User;
use App\Modules\Hr\Core\Services\TenantContext;

class HrFilePolicy extends HrBasePolicy
{
    public function view(User $user, HrFile $file): bool
    {
        if ($file->legal_entity_id !== app(TenantContext::class)->getId()) {
            return false;
        }

        return $this->checkPermission($user, 'hr.employees.view')
            || $this->checkPermission($user, 'hr.documents.view')
            || ($file->category === 'expenses' && $this->checkPermission($user, 'hr.expenses.view'));
    }

    public function download(User $user, HrFile $file): bool
    {
        if ($file->legal_entity_id !== app(TenantContext::class)->getId()) {
            return false;
        }

        return $this->checkPermission($user, 'hr.employees.view')
            || $this->checkPermission($user, 'hr.documents.view')
            || ($file->category === 'expenses' && $this->checkPermission($user, 'hr.expenses.view'));
    }

    public function delete(User $user, HrFile $file): bool
    {
        if ($file->legal_entity_id !== app(TenantContext::class)->getId()) {
            return false;
        }

        return $this->checkPermission($user, 'hr.documents.manage');
    }
}
