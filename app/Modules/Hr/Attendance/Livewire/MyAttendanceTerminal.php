<?php

namespace App\Modules\Hr\Attendance\Livewire;

use App\Modules\Hr\Attendance\Actions\RecordAttendanceEventAction;
use App\Modules\Hr\Attendance\Enums\AttendanceEventType;
use App\Modules\Hr\Attendance\Models\HrAttendanceEvent;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Livewire\Component;

class MyAttendanceTerminal extends Component
{
    public function punch(string $type, RecordAttendanceEventAction $action): void
    {
        abort_unless(in_array($type, array_column(AttendanceEventType::cases(), 'value'), true), 422);
        $employee = $this->employee();
        $action->execute($employee, AttendanceEventType::from($type), now(), 'web', 'web:'.auth()->id().':'.str()->uuid());
        session()->flash('success', AttendanceEventType::from($type)->label().' kaydı alındı.');
    }

    public function render()
    {
        $employee = $this->employee();
        return view('livewire.hr.attendance.my-attendance-terminal', [
            'employee' => $employee,
            'todayEvents' => HrAttendanceEvent::withoutGlobalScope('tenant')->where('legal_entity_id', $employee->legal_entity_id)->where('employee_id', $employee->id)->whereDate('occurred_at', today())->orderByDesc('occurred_at')->get(),
        ])->layout('layouts.app');
    }

    private function employee(): HrEmployee
    {
        return HrEmployee::withoutGlobalScope('tenant')->where('legal_entity_id', app(TenantContext::class)->getId())->where('user_id', auth()->id())->firstOrFail();
    }
}
