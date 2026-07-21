<?php

namespace App\Modules\Hr\Support\Actions;

use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use App\Modules\Hr\Support\Models\HrSupportMessage;
use App\Modules\Hr\Support\Models\HrSupportTicket;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ManageSupportTicketAction
{
    public function __construct(private HrAuditService $audit) {}

    public function create(array $data): HrSupportTicket
    {
        abort_unless(auth()->user()?->hasHrPermission('hr.support.create'), 403);

        $validated = validator($data, [
            'category' => 'required|in:hr,payroll,leave,attendance,technical,other',
            'subject' => 'required|string|max:180',
            'description' => 'required|string|max:10000',
            'priority' => 'required|in:low,normal,high,urgent',
        ])->validate();
        $tenantId = app(TenantContext::class)->getId();
        $employee = HrEmployee::withoutGlobalScope('tenant')
            ->where('legal_entity_id', $tenantId)
            ->where('user_id', auth()->id())
            ->first();

        $ticket = HrSupportTicket::create([
            'legal_entity_id' => $tenantId,
            'ticket_number' => 'SUP-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),
            'requester_employee_id' => $employee?->id,
            'requester_user_id' => auth()->id(),
            'category' => $validated['category'],
            'subject' => trim($validated['subject']),
            'description_encrypted' => trim($validated['description']),
            'priority' => $validated['priority'],
            'status' => 'open',
        ]);

        $this->audit->log('support_ticket_created', $ticket, null, [
            'ticket_number' => $ticket->ticket_number,
            'category' => $ticket->category,
            'priority' => $ticket->priority,
            'description' => '[MASKED]',
        ]);

        return $ticket;
    }

    public function addMessage(HrSupportTicket $ticket, string $body, bool $internal = false): HrSupportMessage
    {
        $this->authorizeTicket($ticket);
        abort_if(in_array($ticket->status, ['closed'], true), 422, 'Kapalı talebe mesaj eklenemez.');
        abort_if(blank($body) || mb_strlen($body) > 10000, 422, 'Mesaj 1-10000 karakter arasında olmalıdır.');
        abort_if($internal && ! auth()->user()?->hasHrPermission('hr.support.manage'), 403);

        $message = HrSupportMessage::create([
            'legal_entity_id' => $ticket->legal_entity_id,
            'support_ticket_id' => $ticket->id,
            'author_user_id' => auth()->id(),
            'body_encrypted' => trim($body),
            'is_internal' => $internal,
        ]);

        $this->audit->log('support_ticket_message_added', $message, null, [
            'ticket_number' => $ticket->ticket_number,
            'internal' => $internal,
            'body' => '[MASKED]',
        ]);

        return $message;
    }

    public function assignToSelf(HrSupportTicket $ticket): HrSupportTicket
    {
        $this->authorizeManager($ticket);
        abort_if($ticket->status === 'closed', 422, 'Kapalı talep atanamaz.');
        $ticket->update(['assigned_to' => auth()->id(), 'status' => 'in_progress']);
        $this->audit->log('support_ticket_assigned', $ticket, null, ['assigned_to' => auth()->id()]);

        return $ticket->fresh();
    }

    public function changeStatus(HrSupportTicket $ticket, string $status): HrSupportTicket
    {
        $this->authorizeManager($ticket);
        $transitions = [
            'open' => ['in_progress', 'resolved', 'closed'],
            'in_progress' => ['open', 'resolved', 'closed'],
            'resolved' => ['open', 'closed'],
            'closed' => ['open'],
        ];
        abort_unless(in_array($status, $transitions[$ticket->status] ?? [], true), 422, 'Destek talebi durum geçişi geçersiz.');

        $oldStatus = $ticket->status;
        $ticket->update([
            'status' => $status,
            'resolved_at' => $status === 'resolved' ? now() : ($status === 'open' ? null : $ticket->resolved_at),
            'closed_at' => $status === 'closed' ? now() : ($status === 'open' ? null : $ticket->closed_at),
        ]);
        $this->audit->log('support_ticket_status_changed', $ticket, ['status' => $oldStatus], ['status' => $status]);

        return $ticket->fresh();
    }

    public function visibleMessages(HrSupportTicket $ticket)
    {
        $this->authorizeTicket($ticket);

        return $ticket->messages()
            ->with('author')
            ->when(! auth()->user()?->hasHrPermission('hr.support.manage'), fn ($query) => $query->where('is_internal', false))
            ->oldest()
            ->get();
    }

    private function authorizeTicket(HrSupportTicket $ticket): void
    {
        abort_unless($ticket->legal_entity_id === app(TenantContext::class)->getId(), 404);
        abort_unless(
            auth()->user()?->hasHrPermission('hr.support.manage')
            || ($ticket->requester_user_id === auth()->id() && auth()->user()?->hasHrPermission('hr.support.view')),
            403,
        );
    }

    private function authorizeManager(HrSupportTicket $ticket): void
    {
        abort_unless(auth()->user()?->hasHrPermission('hr.support.manage'), 403);
        abort_unless($ticket->legal_entity_id === app(TenantContext::class)->getId(), 404);
    }
}
