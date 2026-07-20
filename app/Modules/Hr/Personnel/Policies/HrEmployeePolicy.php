<?php

namespace App\Modules\Hr\Personnel\Policies;

use App\Models\User;
use App\Modules\Hr\Core\Policies\HrBasePolicy;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Personnel\Models\HrEmployee;

class HrEmployeePolicy extends HrBasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->checkPermission($user, 'hr.employees.view');
    }

    public function view(User $user, HrEmployee $employee): bool
    {
        if ($employee->legal_entity_id !== app(TenantContext::class)->getId()) {
            return false;
        }

        return $this->checkPermission($user, 'hr.employees.view');
    }

    public function create(User $user): bool
    {
        return $this->checkPermission($user, 'hr.employees.create');
    }

    public function update(User $user, HrEmployee $employee): bool
    {
        if ($employee->legal_entity_id !== app(TenantContext::class)->getId()) {
            return false;
        }

        return $this->checkPermission($user, 'hr.employees.update');
    }

    public function delete(User $user, HrEmployee $employee): bool
    {
        if ($employee->legal_entity_id !== app(TenantContext::class)->getId()) {
            return false;
        }

        return $this->checkPermission($user, 'hr.employees.terminate');
    }

    public function viewIdentity(User $user, HrEmployee $employee): bool
    {
        if ($employee->legal_entity_id !== app(TenantContext::class)->getId()) {
            return false;
        }

        return $this->checkPermission($user, 'hr.employees.view_identity');
    }

    public function viewBank(User $user, HrEmployee $employee): bool
    {
        if ($employee->legal_entity_id !== app(TenantContext::class)->getId()) {
            return false;
        }

        return $this->checkPermission($user, 'hr.employees.view_bank');
    }

    public function export(User $user): bool
    {
        return $this->checkPermission($user, 'hr.employees.export');
    }

    public function linkUser(User $user, HrEmployee $employee): bool
    {
        if ($employee->legal_entity_id !== app(TenantContext::class)->getId()) {
            return false;
        }

        return $this->checkPermission($user, 'hr.employees.link_user');
    }
}
