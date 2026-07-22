<?php

namespace Tests\Feature\Hr;

use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Performance\Actions\CreateEvaluationAssignmentAction;
use App\Modules\Hr\Performance\Actions\CreatePerformanceCycleAction;
use App\Modules\Hr\Performance\Actions\CreatePerformanceTemplateAction;
use App\Modules\Hr\Performance\Actions\ExportPerformanceReportAction;
use App\Modules\Hr\Performance\Actions\SendPerformanceRemindersAction;
use App\Modules\Hr\Performance\Actions\SubmitPerformanceEvaluationAction;
use App\Modules\Hr\Performance\Enums\PerformanceCycleStatus;
use App\Modules\Hr\Performance\Enums\ReviewerType;
use App\Modules\Hr\Performance\Models\HrPerformanceResult;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use App\Modules\Hr\Performance\Notifications\PerformanceEvaluationReminderNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class PerformanceEnhancementsTest extends TestCase
{
    use RefreshHrDatabase;

    public function test_mixed_questions_are_sanitized_and_weighted_360_result_is_calculated(): void
    {
        [$admin, $reviewerUser, $employee, $reviewer] = $this->fixture();
        [$cycle, $template] = $this->cycleAndTemplate();
        $self = app(CreateEvaluationAssignmentAction::class)->execute($cycle, $template, $employee, $employee, ReviewerType::Self, 20);
        $manager = app(CreateEvaluationAssignmentAction::class)->execute($cycle, $template, $employee, $reviewer, ReviewerType::Manager, 80);
        $this->startEvaluation($cycle);

        $this->actingAs($admin);
        app(SubmitPerformanceEvaluationAction::class)->execute($self, ['quality' => 5, 'delivery' => 80, 'comment' => 'Gelişim notu', 'rogue' => 'saklanmamalı']);
        $this->actingAs($reviewerUser);
        $submitted = app(SubmitPerformanceEvaluationAction::class)->execute($manager, ['quality' => 3, 'delivery' => 60, 'comment' => 'Yönetici notu']);

        $this->assertSame(3, $submitted->answers['quality']);
        $this->assertSame(60, $submitted->answers['delivery']);
        $this->assertSame('Yönetici notu', $submitted->answers['comment']);
        $this->assertArrayNotHasKey('rogue', $submitted->answers);
        $result = HrPerformanceResult::withoutGlobalScope('tenant')->where('cycle_id', $cycle->id)->where('employee_id', $employee->id)->firstOrFail();
        $this->assertSame('66.00', $result->overall_score);
        $this->assertSame('complete', $result->status);
        $this->assertSame(2, $result->completed_responses);
    }

    public function test_reminder_is_idempotent_for_the_day(): void
    {
        [$admin, $reviewerUser, $employee, $reviewer] = $this->fixture();
        [$cycle, $template] = $this->cycleAndTemplate();
        $evaluation = app(CreateEvaluationAssignmentAction::class)->execute($cycle, $template, $employee, $reviewer, ReviewerType::Manager);
        $this->startEvaluation($cycle);
        Notification::fake();
        $this->actingAs($admin);
        $this->assertSame(1, app(SendPerformanceRemindersAction::class)->execute($cycle->fresh()));
        $this->assertSame(0, app(SendPerformanceRemindersAction::class)->execute($cycle->fresh()));
        Notification::assertSentTo($reviewerUser, PerformanceEvaluationReminderNotification::class);
        $this->assertSame(1, $evaluation->fresh()->reminder_count);
    }

    public function test_cycle_excel_and_employee_pdf_reports_are_generated(): void
    {
        [$admin, $reviewerUser, $employee, $reviewer] = $this->fixture();
        [$cycle, $template] = $this->cycleAndTemplate();
        $evaluation = app(CreateEvaluationAssignmentAction::class)->execute($cycle, $template, $employee, $reviewer, ReviewerType::Manager);
        $this->startEvaluation($cycle);
        $this->actingAs($reviewerUser);
        app(SubmitPerformanceEvaluationAction::class)->execute($evaluation, ['quality' => 4, 'delivery' => 80, 'comment' => 'Rapor notu']);
        $this->actingAs($admin);
        app(CreatePerformanceCycleAction::class)->transition($cycle->fresh(), PerformanceCycleStatus::Calibration);

        $excelPath = storage_path('app/private/'.app(ExportPerformanceReportAction::class)->exportCycle($cycle->fresh()));
        $pdfPath = storage_path('app/private/'.app(ExportPerformanceReportAction::class)->exportEmployee($cycle->fresh(), $employee));
        $this->assertFileExists($excelPath);
        $this->assertFileExists($pdfPath);
        $workbook = IOFactory::load($excelPath);
        $this->assertSame('Performans Sonuçları', $workbook->getActiveSheet()->getTitle());
        $this->assertSame($employee->employee_number, $workbook->getActiveSheet()->getCell('A2')->getValue());
        $this->assertGreaterThan(1000, filesize($pdfPath));
        @unlink($excelPath);
        @unlink($pdfPath);
    }

    private function fixture(): array
    {
        (new \Database\Seeders\Hr\HrPermissionSeeder())->run();
        $role = DB::table('roles')->where('slug', 'hr_admin')->value('id');
        $admin = User::factory()->create(['role_id' => $role]);
        $reviewerUser = User::factory()->create(['role_id' => $role]);
        $this->actingAs($admin);
        $tenant = LegalEntity::create(['user_id' => $admin->id, 'name' => 'Performans Gelişmiş', 'tax_number' => '9191919191', 'is_active' => true]);
        app(TenantContext::class)->set($tenant);
        $employee = $this->employee($tenant->id, $admin->id, 'PEN01', 'Çalışan', 'pen-1');
        $reviewer = $this->employee($tenant->id, $reviewerUser->id, 'PEN02', 'Yönetici', 'pen-2');

        return [$admin, $reviewerUser, $employee, $reviewer];
    }

    private function cycleAndTemplate(): array
    {
        $cycle = app(CreatePerformanceCycleAction::class)->execute([
            'name' => 'Gelişmiş 360', 'starts_on' => today()->startOfYear()->toDateString(), 'ends_on' => today()->endOfYear()->toDateString(),
            'evaluation_starts_on' => today()->subDay()->toDateString(), 'evaluation_ends_on' => today()->addDay()->toDateString(),
        ]);
        $template = app(CreatePerformanceTemplateAction::class)->execute('Karma Form', [[
            'title' => 'Genel', 'questions' => [
                ['id' => 'quality', 'label' => 'Kalite', 'type' => 'rating', 'weight' => 50],
                ['id' => 'delivery', 'label' => 'Teslimat', 'type' => 'number', 'min' => 0, 'max' => 100, 'weight' => 50],
                ['id' => 'comment', 'label' => 'Yorum', 'type' => 'text', 'required' => false, 'weight' => 0],
            ],
        ]]);

        return [$cycle, $template];
    }

    private function startEvaluation($cycle): void
    {
        app(CreatePerformanceCycleAction::class)->transition($cycle, PerformanceCycleStatus::Active);
        app(CreatePerformanceCycleAction::class)->transition($cycle->fresh(), PerformanceCycleStatus::Evaluation);
    }

    private function employee(int $tenant, int $user, string $number, string $first, string $hash): HrEmployee
    {
        return HrEmployee::withoutGlobalScope('tenant')->create([
            'legal_entity_id' => $tenant, 'user_id' => $user, 'employee_number' => $number,
            'national_id_encrypted' => 'enc', 'national_id_hash' => $hash, 'national_id_last_four' => '0001',
            'first_name' => $first, 'last_name' => 'Test', 'status' => 'active',
        ]);
    }
}
