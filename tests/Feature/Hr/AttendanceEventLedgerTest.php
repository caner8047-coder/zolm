<?php

namespace Tests\Feature\Hr;

use App\Models\LegalEntity;
use App\Models\HrLicense;
use App\Models\User;
use App\Modules\Hr\Attendance\Actions\RecordAttendanceEventAction;
use App\Modules\Hr\Attendance\Enums\AttendanceEventType;
use App\Modules\Hr\Attendance\Models\HrAttendanceAnomaly;
use App\Modules\Hr\Attendance\Models\HrAttendanceDevice;
use App\Modules\Hr\Attendance\Models\HrAttendanceEvent;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use App\Modules\Hr\Shift\Actions\AssignShiftAction;
use App\Modules\Hr\Shift\Actions\PublishShiftWeekAction;
use App\Modules\Hr\Shift\Models\HrShiftTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AttendanceEventLedgerTest extends TestCase
{
    use RefreshHrDatabase;

    private LegalEntity $tenant;
    private User $user;
    private HrEmployee $employee;

    protected function setUp(): void
    {
        parent::setUp();
        (new \Database\Seeders\Hr\HrPermissionSeeder)->run();
        $roleId = DB::table('roles')->where('slug', 'hr_admin')->value('id');
        $this->user = User::factory()->create(['role_id' => $roleId]);
        $this->actingAs($this->user);
        $this->tenant = LegalEntity::create(['user_id' => $this->user->id, 'name' => 'Test', 'tax_number' => '1111111111', 'is_active' => true]);
        app(TenantContext::class)->set($this->tenant);
        $this->employee = HrEmployee::withoutGlobalScope('tenant')->create([
            'legal_entity_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'employee_number' => 'E001',
            'national_id_encrypted' => 'enc',
            'national_id_hash' => 'attendance-h1',
            'national_id_last_four' => '0001',
            'first_name' => 'Test',
            'last_name' => 'User',
            'status' => 'active',
        ]);
    }

    public function test_same_source_payload_is_idempotent(): void
    {
        $action = app(RecordAttendanceEventAction::class);
        $time = now()->seconds(0);
        $first = $action->execute($this->employee, AttendanceEventType::CheckIn, $time, 'api', 'device-event-1', metadata: ['gate' => 2, 'mode' => 'card']);
        $second = $action->execute($this->employee, AttendanceEventType::CheckIn, $time, 'api', 'device-event-1', metadata: ['mode' => 'card', 'gate' => 2]);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, HrAttendanceEvent::withoutGlobalScope('tenant')->count());
    }

    public function test_changed_payload_with_same_source_key_is_rejected(): void
    {
        $action = app(RecordAttendanceEventAction::class);
        $action->execute($this->employee, AttendanceEventType::CheckIn, now(), 'api', 'immutable-key');

        $this->expectException(HttpException::class);
        $action->execute($this->employee, AttendanceEventType::CheckOut, now(), 'api', 'immutable-key');
    }

    public function test_device_and_employee_must_belong_to_current_tenant(): void
    {
        $other = LegalEntity::create(['user_id' => $this->user->id, 'name' => 'Other', 'tax_number' => '2222222222', 'is_active' => true]);
        $device = HrAttendanceDevice::withoutGlobalScope('tenant')->create(['legal_entity_id' => $other->id, 'code' => 'GATE', 'name' => 'Other Gate', 'type' => 'turnstile']);

        $this->expectException(HttpException::class);
        app(RecordAttendanceEventAction::class)->execute($this->employee, AttendanceEventType::CheckIn, now(), 'turnstile', 'other-device-1', $device);
    }

    public function test_published_shift_produces_late_and_early_anomalies(): void
    {
        $date = now()->subWeek()->startOfWeek()->addDay();
        $template = HrShiftTemplate::create(['legal_entity_id' => $this->tenant->id, 'code' => 'GUN', 'name' => 'Gündüz', 'starts_at' => '08:00', 'ends_at' => '17:00', 'break_minutes' => 60]);
        app(AssignShiftAction::class)->execute($this->employee, $template, $date->toDateString());
        app(PublishShiftWeekAction::class)->execute($date->copy()->startOfWeek(), $date->copy()->endOfWeek());

        $action = app(RecordAttendanceEventAction::class);
        $action->execute($this->employee, AttendanceEventType::CheckIn, $date->copy()->setTime(8, 21), 'manual', 'late-in', manualReason: 'Geçmiş cihaz kaydı');
        $action->execute($this->employee, AttendanceEventType::CheckOut, $date->copy()->setTime(16, 31), 'manual', 'early-out', manualReason: 'Geçmiş cihaz kaydı');

        $openTypes = HrAttendanceAnomaly::withoutGlobalScope('tenant')->where('status', 'open')->pluck('type')->all();
        $this->assertContains('late_arrival', $openTypes);
        $this->assertContains('early_departure', $openTypes);
        $this->assertSame('auto_resolved', HrAttendanceAnomaly::withoutGlobalScope('tenant')->where('type', 'missing_check_out')->value('status'));
    }

    public function test_authenticated_device_can_ingest_event_through_api(): void
    {
        HrLicense::create(['legal_entity_id' => $this->tenant->id, 'module_key' => 'pdks', 'is_active' => true]);
        $device = HrAttendanceDevice::create(['legal_entity_id' => $this->tenant->id, 'code' => 'TURN-01', 'name' => 'Ana Turnike', 'type' => 'turnstile', 'secret_hash' => Hash::make('device-secret')]);

        $response = $this->withToken('device-secret')->postJson('/api/hr/v1/attendance/events', [
            'tenant_id' => $this->tenant->id,
            'device_code' => 'turn-01',
            'employee_number' => $this->employee->employee_number,
            'event_type' => 'check_in',
            'occurred_at' => now()->toIso8601String(),
            'source_key' => 'turnstile-event-1',
        ]);

        $response->assertOk()->assertJsonPath('data.event_type', 'check_in');
        $this->assertDatabaseHas('hr_attendance_events', ['attendance_device_id' => $device->id, 'source' => 'turnstile', 'source_key' => 'turnstile-event-1']);
        $this->assertNotNull($device->fresh()->last_seen_at);
    }

    public function test_device_api_rejects_invalid_secret(): void
    {
        HrLicense::create(['legal_entity_id' => $this->tenant->id, 'module_key' => 'pdks', 'is_active' => true]);
        HrAttendanceDevice::create(['legal_entity_id' => $this->tenant->id, 'code' => 'TURN-02', 'name' => 'Yan Turnike', 'type' => 'turnstile', 'secret_hash' => Hash::make('right-secret')]);

        $this->withToken('wrong-secret')->postJson('/api/hr/v1/attendance/events', [
            'tenant_id' => $this->tenant->id, 'device_code' => 'TURN-02', 'employee_number' => $this->employee->employee_number,
            'event_type' => 'check_in', 'occurred_at' => now()->toIso8601String(), 'source_key' => 'rejected-event',
        ])->assertUnauthorized();

        $this->assertDatabaseMissing('hr_attendance_events', ['source_key' => 'rejected-event']);
    }
}
