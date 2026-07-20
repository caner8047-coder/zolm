<?php

namespace App\Modules\Hr\Personnel\Livewire;

use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\HrFileService;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Personnel\Actions\UpdateEmployeeAction;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Livewire\Component;
use Livewire\WithFileUploads;

class EmployeeEdit extends Component
{
    use WithFileUploads;

    public HrEmployee $employee;
    public $photo = null;

    public string $first_name = '';
    public string $last_name = '';
    public ?string $middle_name = null;
    public ?string $phone = null;
    public ?string $personal_email = null;
    public ?string $address = null;
    public ?string $city = null;
    public ?string $district = null;
    public ?string $postal_code = null;
    public ?string $emergency_contact_name = null;
    public ?string $emergency_contact_phone = null;
    public ?string $emergency_contact_relation = null;
    public ?string $gender = null;
    public ?string $date_of_birth = null;
    public ?string $marital_status = null;

    public function mount(int $id): void
    {
        $this->employee = HrEmployee::withoutGlobalScope('tenant')->findOrFail($id);

        // Form alanlarını doldur
        $this->first_name = $this->employee->first_name;
        $this->last_name = $this->employee->last_name;
        $this->middle_name = $this->employee->middle_name;
        $this->phone = $this->employee->phone;
        $this->personal_email = $this->employee->personal_email;
        $this->address = $this->employee->address;
        $this->city = $this->employee->city;
        $this->district = $this->employee->district;
        $this->postal_code = $this->employee->postal_code;
        $this->emergency_contact_name = $this->employee->emergency_contact_name;
        $this->emergency_contact_phone = $this->employee->emergency_contact_phone;
        $this->emergency_contact_relation = $this->employee->emergency_contact_relation;
        $this->gender = $this->employee->gender;
        $this->date_of_birth = $this->employee->date_of_birth?->format('Y-m-d');
        $this->marital_status = $this->employee->marital_status;
    }

    public function render()
    {
        return view('livewire.hr.personnel.employee-edit', [
            'employee' => $this->employee,
        ])->layout('layouts.app');
    }

    public function save(): void
    {
        $this->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'phone' => 'nullable|string|max:20',
            'personal_email' => 'nullable|email|max:255',
        ]);

        $data = [
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'middle_name' => $this->middle_name,
            'phone' => $this->phone,
            'personal_email' => $this->personal_email,
            'address' => $this->address,
            'city' => $this->city,
            'district' => $this->district,
            'postal_code' => $this->postal_code,
            'emergency_contact_name' => $this->emergency_contact_name,
            'emergency_contact_phone' => $this->emergency_contact_phone,
            'emergency_contact_relation' => $this->emergency_contact_relation,
            'gender' => $this->gender,
            'date_of_birth' => $this->date_of_birth,
            'marital_status' => $this->marital_status,
        ];

        $action = app(UpdateEmployeeAction::class);
        $action->execute($this->employee, $data);

        // Fotoğraf güncelleme
        if ($this->photo) {
            $fileService = app(HrFileService::class);
            $hrFile = $fileService->upload($this->photo, 'photos', $this->employee->id, \App\Modules\Hr\Personnel\Models\HrEmployee::class);
            $this->employee->update(['photo_file_id' => $hrFile->id]);
            app(HrAuditService::class)->log('employee_photo_updated', $this->employee);
        }

        session()->flash('success', 'Çalışan bilgileri güncellendi.');

        $this->redirect(route('hr.personnel.show', $this->employee->id));
    }
}
