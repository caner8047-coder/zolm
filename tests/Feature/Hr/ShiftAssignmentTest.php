<?php

namespace Tests\Feature\Hr;

use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Leave\Enums\LeaveRequestStatus;
use App\Modules\Hr\Leave\Models\HrLeaveRequest;
use App\Modules\Hr\Leave\Models\HrLeaveType;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use App\Modules\Hr\Shift\Actions\AssignShiftAction;
use App\Modules\Hr\Shift\Actions\CancelShiftAssignmentAction;
use App\Modules\Hr\Shift\Actions\PublishShiftWeekAction;
use App\Modules\Hr\Shift\Actions\SetShiftAvailabilityAction;
use App\Modules\Hr\Shift\Actions\BulkAssignShiftAction;
use App\Modules\Hr\Shift\Actions\CreateShiftChangeRequestAction;
use App\Modules\Hr\Shift\Actions\DecideShiftChangeRequestAction;
use App\Modules\Hr\Shift\Enums\ShiftAssignmentStatus;
use App\Modules\Hr\Shift\Enums\ShiftAvailabilityStatus;
use App\Modules\Hr\Shift\Models\HrShiftTemplate;
use App\Modules\Hr\Training\Actions\CreateTrainingCourseAction;
use App\Modules\Hr\Training\Actions\EnrollTrainingEmployeeAction;
use App\Modules\Hr\Training\Actions\RecordTrainingResultAction;
use App\Modules\Hr\Training\Actions\ScheduleTrainingSessionAction;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ShiftAssignmentTest extends TestCase
{
    use RefreshHrDatabase;

    private LegalEntity $tenant;
    private HrEmployee $employee;
    private HrShiftTemplate $template;

    protected function setUp(): void
    {
        parent::setUp(); (new \Database\Seeders\Hr\HrPermissionSeeder)->run();
        $roleId = DB::table('roles')->where('slug', 'hr_admin')->value('id'); $user = User::factory()->create(['role_id' => $roleId]); $this->actingAs($user);
        $this->tenant = LegalEntity::create(['user_id' => $user->id, 'name' => 'Test', 'tax_number' => '1111111111', 'is_active' => true]); app(TenantContext::class)->set($this->tenant);
        $this->employee = HrEmployee::withoutGlobalScope('tenant')->create(['legal_entity_id' => $this->tenant->id, 'employee_number' => 'E001', 'national_id_encrypted' => 'enc', 'national_id_hash' => 'h1', 'national_id_last_four' => '0001', 'first_name' => 'Test', 'last_name' => 'User', 'status' => 'active']);
        $this->template = HrShiftTemplate::create(['legal_entity_id' => $this->tenant->id, 'code' => 'SABAH', 'name' => 'Sabah', 'starts_at' => '08:00', 'ends_at' => '17:00', 'break_minutes' => 60]);
    }

    public function test_assignment_is_tenant_scoped_and_replaces_same_day_plan(): void
    {
        $first = app(AssignShiftAction::class)->execute($this->employee, $this->template, now()->addDay()->toDateString());
        $second = app(AssignShiftAction::class)->execute($this->employee, $this->template, now()->addDay()->toDateString(), 'Güncellendi');
        $this->assertSame($first->id, $second->id); $this->assertSame(1, $this->employee->fresh()->shiftAssignments()->count()); $this->assertSame('Güncellendi', $second->note);
    }

    public function test_approved_leave_blocks_shift_assignment(): void
    {
        $date = now()->addDay()->toDateString();
        $type = HrLeaveType::create(['legal_entity_id' => $this->tenant->id, 'code' => 'ANNUAL', 'name' => 'Yıllık İzin', 'unit' => 'day', 'is_paid' => true]);
        HrLeaveRequest::create(['legal_entity_id' => $this->tenant->id, 'employee_id' => $this->employee->id, 'leave_type_id' => $type->id, 'status' => LeaveRequestStatus::Approved, 'start_date' => $date, 'end_date' => $date, 'requested_amount' => 1, 'unit' => 'day']);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(AssignShiftAction::class)->execute($this->employee, $this->template, $date);
    }

    public function test_week_publish_only_changes_planned_assignments(): void
    {
        $date = now()->startOfWeek()->addDay();
        $assignment = app(AssignShiftAction::class)->execute($this->employee, $this->template, $date->toDateString());
        $count = app(PublishShiftWeekAction::class)->execute($date->copy()->startOfWeek(), $date->copy()->endOfWeek());
        $this->assertSame(1, $count);
        $this->assertSame(ShiftAssignmentStatus::Published, $assignment->fresh()->status);
        $this->assertNotNull($assignment->fresh()->published_at);
        $this->assertSame(0, app(PublishShiftWeekAction::class)->execute($date->copy()->startOfWeek(), $date->copy()->endOfWeek()));
    }

    public function test_assignment_can_be_cancelled_with_reason(): void
    {
        $assignment = app(AssignShiftAction::class)->execute($this->employee, $this->template, now()->addDay()->toDateString());
        $cancelled = app(CancelShiftAssignmentAction::class)->execute($assignment, 'Operasyon planı değişti');
        $this->assertSame(ShiftAssignmentStatus::Cancelled, $cancelled->status);
        $this->assertSame('Operasyon planı değişti', $cancelled->cancellation_reason);
        $this->assertNotNull($cancelled->cancelled_at);
    }

    public function test_unavailable_day_blocks_shift_assignment(): void
    {
        $date = now()->addDays(2)->toDateString();
        app(SetShiftAvailabilityAction::class)->execute($this->employee, $date, ShiftAvailabilityStatus::Unavailable, note: 'Okul');
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(AssignShiftAction::class)->execute($this->employee, $this->template, $date);
    }

    public function test_bulk_assignment_continues_when_one_employee_is_unavailable(): void
    {
        $date = now()->addDays(2)->toDateString();
        $other = HrEmployee::withoutGlobalScope('tenant')->create(['legal_entity_id' => $this->tenant->id, 'employee_number' => 'E002', 'national_id_encrypted' => 'enc', 'national_id_hash' => 'h2', 'national_id_last_four' => '0002', 'first_name' => 'Other', 'last_name' => 'User', 'status' => 'active']);
        app(SetShiftAvailabilityAction::class)->execute($this->employee, $date, ShiftAvailabilityStatus::Unavailable);
        $result = app(BulkAssignShiftAction::class)->execute([$this->employee->id, $other->id], $this->template, $date);
        $this->assertSame(1, $result['assigned']);
        $this->assertArrayHasKey($this->employee->id, $result['errors']);
        $this->assertSame(1, $other->shiftAssignments()->count());
    }

    public function test_approved_change_request_revises_assignment_as_draft(): void
    {
        $assignment = app(AssignShiftAction::class)->execute($this->employee, $this->template, now()->addDays(2)->toDateString());
        app(PublishShiftWeekAction::class)->execute(now()->startOfWeek(), now()->addWeek()->endOfWeek());
        $late = HrShiftTemplate::create(['legal_entity_id' => $this->tenant->id, 'code' => 'AKSAM', 'name' => 'Akşam', 'starts_at' => '16:00', 'ends_at' => '00:00', 'break_minutes' => 30, 'crosses_midnight' => true]);
        $desiredDate = now()->addDays(3)->toDateString();
        $request = app(CreateShiftChangeRequestAction::class)->execute($assignment->fresh(), $late, $desiredDate, 'Randevu');
        $decided = app(DecideShiftChangeRequestAction::class)->approve($request, 'Uygun');
        $this->assertSame('approved', $decided->status->value);
        $this->assertSame($late->id, $assignment->fresh()->shift_template_id);
        $this->assertSame($desiredDate, $assignment->fresh()->shift_date->toDateString());
        $this->assertSame(ShiftAssignmentStatus::Planned, $assignment->fresh()->status);
        $this->assertNull($assignment->fresh()->published_at);
    }

    public function test_required_training_certificate_blocks_and_then_allows_shift_assignment(): void
    {
        $course = app(CreateTrainingCourseAction::class)->execute(['code' => 'KRITIK', 'title' => 'Kritik Operasyon', 'duration_minutes' => 60, 'certificate_validity_months' => 12]);
        $this->template->update(['required_training_course_id' => $course->id]);
        $date = now()->addMonth()->toDateString();

        try {
            app(AssignShiftAction::class)->execute($this->employee, $this->template, $date);
            $this->fail('Sertifikasız vardiya ataması engellenmeliydi.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $session = app(ScheduleTrainingSessionAction::class)->execute($course, ['starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour()]);
        $enrollment = app(EnrollTrainingEmployeeAction::class)->execute($session, $this->employee);
        app(RecordTrainingResultAction::class)->execute($enrollment, 100);

        $assignment = app(AssignShiftAction::class)->execute($this->employee, $this->template->fresh(), $date);
        $this->assertSame($this->template->id, $assignment->shift_template_id);
    }
}
