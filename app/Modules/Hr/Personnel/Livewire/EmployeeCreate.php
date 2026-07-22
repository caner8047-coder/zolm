<?php

namespace App\Modules\Hr\Personnel\Livewire;

use App\Modules\Hr\Core\Services\HrFileService;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Personnel\Actions\CreateEmployeeAction;
use App\Modules\Hr\Organization\Models\HrDepartment;
use App\Modules\Hr\Organization\Models\HrPosition;
use App\Modules\Hr\Organization\Models\HrBranch;
use App\Modules\Hr\Organization\Services\OrgStructureService;
use App\Modules\Hr\Personnel\Models\HrEmploymentRecord;
use Livewire\Component;
use Livewire\WithFileUploads;

class EmployeeCreate extends Component
{
    use WithFileUploads;

    public string $first_name = '';
    public string $last_name = '';
    public ?string $middle_name = null;
    public ?string $national_id = null;
    public ?string $gender = null;
    public ?string $date_of_birth = null;
    public ?string $marital_status = null;
    public $photo = null;
    public ?string $phone = null;
    public ?string $personal_email = null;
    public ?string $address = null;
    public ?string $city = null;
    public ?string $emergency_contact_name = null;
    public ?string $emergency_contact_phone = null;
    public ?string $emergency_contact_relation = null;

    // Çalışma bilgileri
    public ?int $branch_id = null;
    public ?int $department_id = null;
    public ?int $position_id = null;
    public ?int $manager_employee_id = null;
    public string $employment_type = 'full_time';
    public string $start_date = '';

    public function mount(OrgStructureService $organization): void
    {
        $defaults = $organization->ensureMinimumStructure();
        $this->branch_id = $defaults['branch']->id;
        $this->department_id = $defaults['department']->id;
        $this->position_id = $defaults['position']->id;
    }

    public function render()
    {
        $tenantId = app(TenantContext::class)->getId();

        return view('livewire.hr.personnel.employee-create', [
            'branches' => HrBranch::withoutGlobalScope('tenant')
                ->where('legal_entity_id', $tenantId)
                ->active()
                ->ordered()
                ->get(),
            'departments' => HrDepartment::withoutGlobalScope('tenant')
                ->where('legal_entity_id', $tenantId)
                ->active()
                ->ordered()
                ->get(),
            'positions' => HrPosition::withoutGlobalScope('tenant')
                ->where('legal_entity_id', $tenantId)
                ->active()
                ->ordered()
                ->get(),
        ])->layout('layouts.app');
    }

    public function save(): void
    {
        $this->validate(
            [
                'first_name' => 'required|string|max:100',
                'last_name' => 'required|string|max:100',
                'national_id' => 'required|digits:11',
                'phone' => 'nullable|string|max:20',
                'personal_email' => 'nullable|email|max:255',
                'start_date' => 'required|date',
                'employment_type' => 'required|in:full_time,part_time,contract,intern,temporary',
            ],
            [
                'national_id.required' => 'TC kimlik numarası zorunludur.',
                'national_id.digits' => 'TC kimlik numarası 11 rakamdan oluşmalıdır.',
            ]
        );

        $employeeData = [
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'middle_name' => $this->middle_name,
            'national_id' => $this->national_id,
            'gender' => $this->gender,
            'date_of_birth' => $this->date_of_birth,
            'marital_status' => $this->marital_status,
            'phone' => $this->phone,
            'personal_email' => $this->personal_email,
            'address' => $this->address,
            'city' => $this->city,
            'emergency_contact_name' => $this->emergency_contact_name,
            'emergency_contact_phone' => $this->emergency_contact_phone,
            'emergency_contact_relation' => $this->emergency_contact_relation,
        ];

        $employmentData = [
            'branch_id' => $this->branch_id,
            'department_id' => $this->department_id,
            'position_id' => $this->position_id,
            'manager_employee_id' => $this->manager_employee_id,
            'employment_type' => $this->employment_type,
            'start_date' => $this->start_date,
        ];

        $action = app(CreateEmployeeAction::class);
        $employee = $action->findExistingByNationalId((string) $this->national_id);
        $recoveredExisting = $employee !== null;

        if ($employee) {
            HrEmploymentRecord::withoutGlobalScope('tenant')
                ->where('legal_entity_id', app(TenantContext::class)->getId())
                ->where('employee_id', $employee->id)
                ->where('status', 'active')
                ->latest('id')
                ->first()
                ?->update($employmentData + ['updated_by' => auth()->id()]);
        } else {
            $employee = $action->execute($employeeData, $employmentData);
        }

        // Fotoğraf yükleme
        if ($this->photo) {
            $fileService = app(HrFileService::class);
            $hrFile = $fileService->upload($this->photo, 'photos', $employee->id, \App\Modules\Hr\Personnel\Models\HrEmployee::class);
            $employee->update(['photo_file_id' => $hrFile->id]);
        }

        session()->flash(
            'success',
            $recoveredExisting
                ? 'Daha önce başlatılan çalışan kaydı tamamlandı: ' . $employee->employee_number
                : 'Çalışan başarıyla oluşturuldu: ' . $employee->employee_number
        );

        $this->redirect(route('hr.personnel.show', $employee->id));
    }
}
