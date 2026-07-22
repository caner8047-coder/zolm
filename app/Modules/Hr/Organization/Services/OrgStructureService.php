<?php

namespace App\Modules\Hr\Organization\Services;

use App\Modules\Hr\Organization\Models\HrDepartment;
use App\Modules\Hr\Organization\Models\HrSgkWorkplace;
use App\Modules\Hr\Organization\Models\HrBranch;
use App\Modules\Hr\Organization\Models\HrUnit;
use App\Modules\Hr\Organization\Models\HrTeam;
use App\Modules\Hr\Organization\Models\HrPosition;
use App\Modules\Hr\Organization\Models\HrCostCenter;
use App\Modules\Hr\Core\Services\TenantContext;
use Illuminate\Support\Facades\DB;

class OrgStructureService
{
    public function ensureMinimumStructure(): array
    {
        return DB::transaction(function (): array {
            $tenantId = app(TenantContext::class)->getId();

            $branch = HrBranch::withoutGlobalScope('tenant')
                ->where('legal_entity_id', $tenantId)
                ->where('is_active', true)
                ->ordered()
                ->first();

            if (!$branch) {
                $branch = HrBranch::create([
                    'legal_entity_id' => $tenantId,
                    'name' => 'Merkez',
                    'code' => 'MERKEZ',
                    'is_active' => true,
                    'created_by' => auth()->id(),
                ]);
            }

            $department = HrDepartment::withoutGlobalScope('tenant')
                ->where('legal_entity_id', $tenantId)
                ->where('is_active', true)
                ->ordered()
                ->first();

            if (!$department) {
                $department = HrDepartment::create([
                    'legal_entity_id' => $tenantId,
                    'branch_id' => $branch->id,
                    'name' => 'Genel',
                    'code' => 'GENEL',
                    'is_active' => true,
                    'created_by' => auth()->id(),
                ]);
            }

            $position = HrPosition::withoutGlobalScope('tenant')
                ->where('legal_entity_id', $tenantId)
                ->where('is_active', true)
                ->ordered()
                ->first();

            if (!$position) {
                $position = HrPosition::create([
                    'legal_entity_id' => $tenantId,
                    'department_id' => $department->id,
                    'title' => 'Çalışan',
                    'code' => 'CALISAN',
                    'is_active' => true,
                    'created_by' => auth()->id(),
                ]);
            }

            return compact('branch', 'department', 'position');
        });
    }

    public function getOrganizationTree(): array
    {
        $tenantId = app(TenantContext::class)->getId();

        $departments = HrDepartment::withoutGlobalScope('tenant')
            ->where('legal_entity_id', $tenantId)
            ->whereNull('parent_id')
            ->with(['children.units.teams', 'positions'])
            ->ordered()
            ->get();

        return $departments->toArray();
    }

    public function isCodeUnique(string $modelClass, string $code, ?int $excludeId = null, ?int $parentId = null): bool
    {
        $query = $modelClass::withoutGlobalScope('tenant')
            ->where('legal_entity_id', app(TenantContext::class)->getId())
            ->where('code', $code);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return !$query->exists();
    }

    public function hasActiveEmployees(int $orgNodeId, string $nodeType): bool
    {
        // Organizasyon düğümünde aktif çalışan olup olmadığını kontrol et
        // Bu Faz 1A'da basit; genişletme Faz 1B'de yapılabilir
        return false;
    }

    public function isUsedInEmploymentRecord(int $orgNodeId, string $column): bool
    {
        return \App\Modules\Hr\Personnel\Models\HrEmploymentRecord::withoutGlobalScope('tenant')
            ->where($column, $orgNodeId)
            ->where('status', 'active')
            ->exists();
    }
}
