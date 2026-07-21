<?php

namespace App\Modules\Hr\Shift\Livewire;

use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Shift\Actions\DecideShiftChangeRequestAction;
use App\Modules\Hr\Shift\Models\HrShiftChangeRequest;
use Livewire\Component;

class ShiftChangeApprovalInbox extends Component
{
    public ?int $rejectingId = null;
    public string $decisionNote = '';

    public function approve(int $id, DecideShiftChangeRequestAction $action): void
    {
        $request = $this->findRequest($id); $action->approve($request, $this->decisionNote ?: null); $this->decisionNote = ''; session()->flash('success', 'Vardiya değişikliği onaylandı; plan taslağa alındı.');
    }
    public function startReject(int $id): void { $this->rejectingId = $id; $this->decisionNote = ''; }
    public function reject(DecideShiftChangeRequestAction $action): void
    {
        $this->validate(['decisionNote' => 'required|string|max:2000']); $action->reject($this->findRequest($this->rejectingId), $this->decisionNote); $this->reset(['rejectingId', 'decisionNote']); session()->flash('success', 'Vardiya değişikliği reddedildi.');
    }
    private function findRequest(int $id): HrShiftChangeRequest { return HrShiftChangeRequest::withoutGlobalScope('tenant')->where('legal_entity_id', app(TenantContext::class)->getId())->findOrFail($id); }
    public function render() { return view('livewire.hr.shift.shift-change-approval-inbox', ['requests' => HrShiftChangeRequest::withoutGlobalScope('tenant')->where('legal_entity_id', app(TenantContext::class)->getId())->where('status', 'pending')->with(['employee', 'assignment.template', 'desiredTemplate'])->oldest()->get()])->layout('layouts.app'); }
}
