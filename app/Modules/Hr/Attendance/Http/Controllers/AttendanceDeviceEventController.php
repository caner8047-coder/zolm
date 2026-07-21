<?php

namespace App\Modules\Hr\Attendance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\LegalEntity;
use App\Modules\Hr\Attendance\Actions\RecordAttendanceEventAction;
use App\Modules\Hr\Attendance\Enums\AttendanceEventType;
use App\Modules\Hr\Attendance\Models\HrAttendanceDevice;
use App\Modules\Hr\Core\Services\HrLicenseService;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AttendanceDeviceEventController extends Controller
{
    public function store(Request $request, RecordAttendanceEventAction $action, HrLicenseService $licenses): JsonResponse
    {
        $data = $request->validate([
            'tenant_id' => 'required|integer',
            'device_code' => 'required|string|max:60',
            'employee_number' => 'required|string|max:80',
            'event_type' => 'required|in:check_in,check_out,break_start,break_end',
            'occurred_at' => 'required|date',
            'source_key' => 'required|string|max:160',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'metadata' => 'nullable|array',
        ]);

        $tenant = LegalEntity::whereKey($data['tenant_id'])->where('is_active', true)->firstOrFail();
        abort_unless($licenses->isModuleActive($tenant, 'pdks'), 403, 'PDKS modülü aktif değil.');
        $device = HrAttendanceDevice::withoutGlobalScope('tenant')
            ->where('legal_entity_id', $tenant->id)
            ->where('code', strtoupper(trim($data['device_code'])))
            ->where('is_active', true)
            ->firstOrFail();
        $secret = $request->bearerToken() ?: $request->header('X-HR-Device-Secret');
        abort_unless($secret && $device->secret_hash && Hash::check($secret, $device->secret_hash), 401, 'Cihaz kimlik doğrulaması başarısız.');

        $employee = HrEmployee::withoutGlobalScope('tenant')
            ->where('legal_entity_id', $tenant->id)
            ->where('employee_number', $data['employee_number'])
            ->active()
            ->firstOrFail();
        app(TenantContext::class)->set($tenant);

        $event = $action->execute(
            employee: $employee,
            eventType: AttendanceEventType::from($data['event_type']),
            occurredAt: $data['occurred_at'],
            source: $device->type,
            sourceKey: $data['source_key'],
            device: $device,
            latitude: isset($data['latitude']) ? (float) $data['latitude'] : null,
            longitude: isset($data['longitude']) ? (float) $data['longitude'] : null,
            metadata: $data['metadata'] ?? null,
            trustedDevice: true,
        );

        return response()->json([
            'data' => ['id' => $event->id, 'event_type' => $event->event_type->value, 'occurred_at' => $event->occurred_at->toIso8601String()],
        ]);
    }
}
