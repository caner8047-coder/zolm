<?php

namespace App\Modules\Hr\Leave\Livewire;

use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Leave\Actions\ApproveLeaveRequestAction;
use App\Modules\Hr\Leave\Actions\RejectLeaveRequestAction;
use App\Modules\Hr\Leave\Enums\LeaveApprovalStatus;
use App\Modules\Hr\Leave\Models\HrLeaveApprovalStep;
use Livewire\Component;
use Livewire\WithPagination;

class LeaveApprovalInbox extends Component
{
    use WithPagination;
    public ?int $rejectingId = null;
    public string $rejectComment = '';

    public function approve(int $id, ApproveLeaveRequestAction $action): void
    {
        $request = $this->resolveRequest($id);
        $action->execute($request);
        session()->flash('success', 'İzin talebi onaylandı veya sonraki adıma iletildi.');
    }

    public function startReject(int $id): void { $this->rejectingId = $id; $this->rejectComment = ''; }
    public function reject(RejectLeaveRequestAction $action): void
    {
        $this->validate(['rejectComment' => 'required|string|max:1000']);
        $action->execute($this->resolveRequest($this->rejectingId), $this->rejectComment);
        $this->reset(['rejectingId', 'rejectComment']);
        session()->flash('success', 'İzin talebi reddedildi.');
    }

    private function resolveRequest(?int $id)
    {
        abort_unless($id, 404);
        return \App\Modules\Hr\Leave\Models\HrLeaveRequest::withoutGlobalScope('tenant')->where('legal_entity_id', app(TenantContext::class)->getId())->findOrFail($id);
    }

    public function render()
    {
        $tenantId = app(TenantContext::class)->getId();
        $steps = HrLeaveApprovalStep::withoutGlobalScope('tenant')
            ->where('legal_entity_id', $tenantId)
            ->where('status', LeaveApprovalStatus::Pending->value)
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('hr_leave_approval_steps as earlier_step')
                    ->whereColumn('earlier_step.leave_request_id', 'hr_leave_approval_steps.leave_request_id')
                    ->where('earlier_step.status', LeaveApprovalStatus::Pending->value)
                    ->whereColumn('earlier_step.step_order', '<', 'hr_leave_approval_steps.step_order');
            })
            ->where(function ($query) {
                $query->whereNull('approver_user_id')->orWhere('approver_user_id', auth()->id());
            })
            ->with('leaveRequest.employee', 'leaveRequest.leaveType')
            ->orderBy('created_at')
            ->paginate(15);
        return view('livewire.hr.leave.leave-approval-inbox', ['steps' => $steps])->layout('layouts.app');
    }
}
