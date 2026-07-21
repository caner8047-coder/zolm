<?php

namespace App\Modules\Hr\Attendance\Actions;

use App\Modules\Hr\Attendance\Enums\AttendanceEventType;
use App\Modules\Hr\Attendance\Models\HrAttendanceDevice;
use App\Modules\Hr\Attendance\Models\HrAttendanceEvent;
use App\Modules\Hr\Attendance\Services\AttendanceAnomalyService;
use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RecordAttendanceEventAction
{
    private const SOURCES = ['web', 'qr', 'pin', 'nfc', 'turnstile', 'api', 'manual'];

    public function __construct(
        private HrAuditService $audit,
        private AttendanceAnomalyService $anomalies,
    ) {}

    public function execute(
        HrEmployee $employee,
        AttendanceEventType $eventType,
        Carbon|string $occurredAt,
        string $source,
        string $sourceKey,
        ?HrAttendanceDevice $device = null,
        ?float $latitude = null,
        ?float $longitude = null,
        ?array $metadata = null,
        ?string $manualReason = null,
        bool $trustedDevice = false,
    ): HrAttendanceEvent {
        $tenantId = app(TenantContext::class)->getId();
        $source = strtolower(trim($source));
        $sourceKey = trim($sourceKey);
        $isManual = $source === 'manual';

        abort_unless(in_array($source, self::SOURCES, true), 422, 'PDKS olay kaynağı geçersiz.');
        abort_if($sourceKey === '' || mb_strlen($sourceKey) > 160, 422, 'Kaynak anahtarı zorunludur ve 160 karakteri aşamaz.');
        abort_unless($employee->legal_entity_id === $tenantId, 422, 'Çalışan bu işletmeye ait değil.');
        abort_if($device && ($device->legal_entity_id !== $tenantId || !$device->is_active), 422, 'PDKS cihazı geçersiz veya pasif.');

        $isOwnWebEvent = $source === 'web' && $employee->user_id === auth()->id();
        $permission = $isManual ? 'hr.attendance.manual_entry' : 'hr.attendance.manage';
        abort_unless($trustedDevice || $isOwnWebEvent || auth()->user()?->hasHrPermission($permission), 403);
        abort_if($trustedDevice && !$device, 422, 'Güvenilir cihaz kaydı için cihaz bilgisi zorunludur.');
        abort_if($isManual && blank($manualReason), 422, 'Manuel kayıt gerekçesi zorunludur.');

        $time = Carbon::parse($occurredAt)->setTimezone(config('app.timezone'));
        $normalizedMetadata = $metadata ? $this->sortRecursively($metadata) : null;
        $payload = [
            'tenant_id' => $tenantId,
            'employee_id' => $employee->id,
            'device_id' => $device?->id,
            'event_type' => $eventType->value,
            'occurred_at' => $time->format('Y-m-d H:i:s.uP'),
            'source' => $source,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'metadata' => $normalizedMetadata,
            'manual_reason' => $manualReason,
        ];
        $payloadHash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));

        [$event, $created] = DB::transaction(function () use ($tenantId, $employee, $device, $eventType, $time, $source, $sourceKey, $payloadHash, $latitude, $longitude, $normalizedMetadata, $isManual, $manualReason) {
            $existing = HrAttendanceEvent::withoutGlobalScope('tenant')
                ->where('legal_entity_id', $tenantId)
                ->where('source', $source)
                ->where('source_key', $sourceKey)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                abort_unless(hash_equals($existing->payload_hash, $payloadHash), 409, 'Aynı kaynak anahtarı farklı bir PDKS olayı için kullanılamaz.');
                return [$existing, false];
            }

            $event = HrAttendanceEvent::create([
                'legal_entity_id' => $tenantId,
                'employee_id' => $employee->id,
                'attendance_device_id' => $device?->id,
                'event_type' => $eventType,
                'occurred_at' => $time,
                'source' => $source,
                'source_key' => $sourceKey,
                'payload_hash' => $payloadHash,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'metadata' => $normalizedMetadata,
                'is_manual' => $isManual,
                'manual_reason' => $manualReason,
                'created_by' => auth()->id(),
            ]);

            if ($device) {
                $device->forceFill(['last_seen_at' => now(), 'updated_by' => auth()->id()])->save();
            }

            return [$event, true];
        });

        if ($created) {
            $this->audit->log('attendance_event_recorded', $event, null, [
                'employee_id' => $employee->id,
                'event_type' => $eventType->value,
                'source' => $source,
                'is_manual' => $isManual,
            ]);
            $this->anomalies->evaluateDay($employee, $time->toDateString());
            $this->anomalies->evaluateDay($employee, $time->copy()->subDay()->toDateString());
        }

        return $event->fresh(['employee', 'device']);
    }

    private function sortRecursively(array $value): array
    {
        foreach ($value as &$item) {
            if (is_array($item)) {
                $item = $this->sortRecursively($item);
            }
        }

        if (!array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }
}
