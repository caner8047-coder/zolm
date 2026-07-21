<?php

namespace App\Modules\Hr\Safety\Actions;

use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use App\Modules\Hr\Safety\Models\HrHealthRecord;
use App\Modules\Hr\Safety\Models\HrSafetyAction;
use App\Modules\Hr\Safety\Models\HrSafetyIncident;
use Illuminate\Support\Str;

class ManageSafetyAction
{
    public function __construct(private HrAuditService $audit) {}

    public function reportIncident(array $data): HrSafetyIncident
    {
        abort_unless(auth()->user()?->hasHrPermission('hr.isg.report_incident'), 403);

        $validated = validator($data, [
            'affected_employee_id' => 'nullable|integer',
            'incident_type' => 'required|in:near_miss,injury,occupational_disease,unsafe_condition,environmental,other',
            'severity' => 'required|in:low,medium,high,critical',
            'occurred_at' => 'required|date|before_or_equal:now',
            'location' => 'required|string|max:180',
            'description' => 'required|string|max:10000',
            'immediate_action' => 'nullable|string|max:10000',
            'lost_time' => 'boolean',
        ])->validate();
        $tenantId = app(TenantContext::class)->getId();
        $reporter = $this->employeeForUser($tenantId);
        $affectedEmployee = filled($validated['affected_employee_id'] ?? null)
            ? $this->tenantEmployee((int) $validated['affected_employee_id'], $tenantId)
            : null;
        $sourceHash = hash('sha256', json_encode([
            $tenantId,
            $reporter?->id,
            $affectedEmployee?->id,
            $validated['incident_type'],
            $validated['severity'],
            $validated['occurred_at'],
            trim($validated['location']),
            trim($validated['description']),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        $incident = HrSafetyIncident::create([
            'legal_entity_id' => $tenantId,
            'incident_number' => 'ISG-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),
            'reporter_employee_id' => $reporter?->id,
            'affected_employee_id' => $affectedEmployee?->id,
            'incident_type' => $validated['incident_type'],
            'severity' => $validated['severity'],
            'occurred_at' => $validated['occurred_at'],
            'location' => trim($validated['location']),
            'description_encrypted' => trim($validated['description']),
            'immediate_action_encrypted' => filled($validated['immediate_action'] ?? null) ? trim($validated['immediate_action']) : null,
            'lost_time' => (bool) ($validated['lost_time'] ?? false),
            'status' => 'reported',
            'source_hash' => $sourceHash,
        ]);

        $this->audit->log('safety_incident_reported', $incident, null, [
            'incident_number' => $incident->incident_number,
            'severity' => $incident->severity,
            'health_data' => '[HARIÇ TUTULDU]',
            'source_hash' => $sourceHash,
        ]);

        return $incident;
    }

    public function assignToSelf(HrSafetyIncident $incident): HrSafetyIncident
    {
        $this->authorizeManager($incident);
        abort_if($incident->status === 'closed', 422, 'Kapalı olay atanamaz.');
        $incident->update(['assigned_to' => auth()->id(), 'status' => 'investigating']);
        $this->audit->log('safety_incident_assigned', $incident, null, ['assigned_to' => auth()->id()]);

        return $incident->fresh();
    }

    public function addCorrectiveAction(HrSafetyIncident $incident, array $data): HrSafetyAction
    {
        $this->authorizeManager($incident);
        abort_if($incident->status === 'closed', 422, 'Kapalı olaya aksiyon eklenemez.');
        $validated = validator($data, [
            'title' => 'required|string|max:220',
            'due_on' => 'nullable|date',
        ])->validate();

        $action = HrSafetyAction::create([
            'legal_entity_id' => $incident->legal_entity_id,
            'safety_incident_id' => $incident->id,
            'title' => trim($validated['title']),
            'owner_user_id' => auth()->id(),
            'due_on' => $validated['due_on'] ?? null,
            'status' => 'pending',
        ]);
        $incident->update(['status' => 'action_required']);
        $this->audit->log('safety_corrective_action_created', $action, null, ['title' => $action->title]);

        return $action;
    }

    public function completeCorrectiveAction(HrSafetyAction $action, string $evidence): HrSafetyAction
    {
        abort_unless(auth()->user()?->hasHrPermission('hr.isg.manage'), 403);
        abort_unless($action->legal_entity_id === app(TenantContext::class)->getId(), 404);
        abort_unless($action->status === 'pending', 422, 'Aksiyon zaten tamamlanmış.');
        abort_if(blank($evidence) || mb_strlen($evidence) > 10000, 422, 'Tamamlama kanıtı zorunludur.');

        $action->update([
            'status' => 'completed',
            'completion_evidence_encrypted' => trim($evidence),
            'completed_by' => auth()->id(),
            'completed_at' => now(),
        ]);
        $this->audit->log('safety_corrective_action_completed', $action, null, ['evidence' => '[MASKED]']);

        return $action->fresh();
    }

    public function closeIncident(HrSafetyIncident $incident): HrSafetyIncident
    {
        $this->authorizeManager($incident);
        $incident->loadMissing('reporter');
        abort_if(
            in_array($incident->severity, ['high', 'critical'], true)
            && $incident->reporter?->user_id === auth()->id(),
            422,
            'Yüksek ve kritik olayı bildiren kişi aynı olayı kapatamaz.',
        );
        abort_if(
            in_array($incident->severity, ['high', 'critical'], true)
            && ! $incident->actions()->exists(),
            422,
            'Yüksek ve kritik olay için en az bir düzeltici aksiyon zorunludur.',
        );
        abort_if($incident->actions()->where('status', '!=', 'completed')->exists(), 422, 'Açık düzeltici aksiyonlar tamamlanmadan olay kapatılamaz.');

        $incident->update(['status' => 'closed', 'closed_by' => auth()->id(), 'closed_at' => now()]);
        $this->audit->log('safety_incident_closed', $incident, null, ['source_hash' => $incident->source_hash]);

        return $incident->fresh();
    }

    public function createHealthRecord(HrEmployee $employee, array $data): HrHealthRecord
    {
        abort_unless(
            auth()->user()?->hasHrPermission('hr.isg.manage')
            && auth()->user()?->hasHrPermission('hr.isg.view_health'),
            403,
        );
        $tenantId = app(TenantContext::class)->getId();
        abort_unless($employee->legal_entity_id === $tenantId, 404);
        $validated = validator($data, [
            'record_type' => 'required|in:periodic_exam,fitness,restriction,vaccination,other',
            'recorded_on' => 'required|date',
            'expires_on' => 'nullable|date|after_or_equal:recorded_on',
            'provider' => 'nullable|string|max:500',
            'result' => 'required|string|max:2000',
            'details' => 'nullable|string|max:10000',
        ])->validate();

        $record = HrHealthRecord::create([
            'legal_entity_id' => $tenantId,
            'employee_id' => $employee->id,
            'record_type' => $validated['record_type'],
            'recorded_on' => $validated['recorded_on'],
            'expires_on' => $validated['expires_on'] ?? null,
            'provider_encrypted' => filled($validated['provider'] ?? null) ? trim($validated['provider']) : null,
            'result_encrypted' => trim($validated['result']),
            'details_encrypted' => filled($validated['details'] ?? null) ? trim($validated['details']) : null,
            'created_by' => auth()->id(),
        ]);
        $this->audit->log('health_record_created', $record, null, ['health_data' => '[HARIÇ TUTULDU]']);

        return $record;
    }

    private function authorizeManager(HrSafetyIncident $incident): void
    {
        abort_unless(auth()->user()?->hasHrPermission('hr.isg.manage'), 403);
        abort_unless($incident->legal_entity_id === app(TenantContext::class)->getId(), 404);
    }

    private function employeeForUser(int $tenantId): ?HrEmployee
    {
        return HrEmployee::withoutGlobalScope('tenant')
            ->where('legal_entity_id', $tenantId)
            ->where('user_id', auth()->id())
            ->first();
    }

    private function tenantEmployee(int $employeeId, int $tenantId): HrEmployee
    {
        return HrEmployee::withoutGlobalScope('tenant')
            ->where('legal_entity_id', $tenantId)
            ->findOrFail($employeeId);
    }
}
