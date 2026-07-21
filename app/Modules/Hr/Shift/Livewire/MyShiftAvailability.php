<?php

namespace App\Modules\Hr\Shift\Livewire;

use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use App\Modules\Hr\Shift\Actions\SetShiftAvailabilityAction;
use App\Modules\Hr\Shift\Enums\ShiftAvailabilityStatus;
use App\Modules\Hr\Shift\Models\HrShiftAvailability;
use Livewire\Component;

class MyShiftAvailability extends Component
{
    public string $availabilityDate = '';
    public string $status = 'available';
    public ?string $preferredStart = null;
    public ?string $preferredEnd = null;
    public string $note = '';

    public function mount(): void { $this->availabilityDate = now()->addDay()->toDateString(); }

    public function save(SetShiftAvailabilityAction $action): void
    {
        $this->validate(['availabilityDate' => 'required|date|after_or_equal:today', 'status' => 'required|in:available,preferred,unavailable', 'preferredStart' => 'nullable|date_format:H:i', 'preferredEnd' => 'nullable|date_format:H:i|after:preferredStart', 'note' => 'nullable|string|max:1000']);
        $employee = HrEmployee::withoutGlobalScope('tenant')->where('legal_entity_id', app(TenantContext::class)->getId())->where('user_id', auth()->id())->firstOrFail();
        $action->execute($employee, $this->availabilityDate, ShiftAvailabilityStatus::from($this->status), $this->preferredStart, $this->preferredEnd, $this->note ?: null);
        session()->flash('success', 'Müsaitlik bilginiz kaydedildi.'); $this->reset(['preferredStart', 'preferredEnd', 'note']);
    }

    public function render()
    {
        $tenantId = app(TenantContext::class)->getId();
        $employee = HrEmployee::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->where('user_id', auth()->id())->firstOrFail();
        return view('livewire.hr.shift.my-shift-availability', ['records' => HrShiftAvailability::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->where('employee_id', $employee->id)->whereDate('availability_date', '>=', today())->orderBy('availability_date')->get()])->layout('layouts.app');
    }
}
