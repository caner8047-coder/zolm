<?php

namespace Tests\Feature\Hr;

use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Performance\Actions\AssessCompetencyAction;
use App\Modules\Hr\Performance\Actions\CalibratePerformanceEvaluationAction;
use App\Modules\Hr\Performance\Actions\CreateEvaluationAssignmentAction;
use App\Modules\Hr\Performance\Actions\CreatePerformanceCycleAction;
use App\Modules\Hr\Performance\Actions\CreatePerformanceGoalAction;
use App\Modules\Hr\Performance\Actions\CreatePerformanceTemplateAction;
use App\Modules\Hr\Performance\Actions\SubmitPerformanceEvaluationAction;
use App\Modules\Hr\Performance\Actions\UpdatePerformanceGoalProgressAction;
use App\Modules\Hr\Performance\Enums\PerformanceCycleStatus;
use App\Modules\Hr\Performance\Enums\PerformanceEvaluationStatus;
use App\Modules\Hr\Performance\Enums\ReviewerType;
use App\Modules\Hr\Performance\Models\HrCompetency;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class PerformanceWorkflowTest extends TestCase
{
    use RefreshHrDatabase;
    private User $user; private LegalEntity $tenant; private HrEmployee $employee; private HrEmployee $reviewer;

    protected function setUp():void
    {
        parent::setUp(); (new \Database\Seeders\Hr\HrPermissionSeeder)->run(); $role=DB::table('roles')->where('slug','hr_admin')->value('id'); $this->user=User::factory()->create(['role_id'=>$role]); $this->actingAs($this->user); $this->tenant=LegalEntity::create(['user_id'=>$this->user->id,'name'=>'Performans','tax_number'=>'3131313131','is_active'=>true]); app(TenantContext::class)->set($this->tenant);
        $this->employee=$this->employee('PER01','Çalışan','performance-e1',$this->user->id); $reviewerUser=User::factory()->create(['role_id'=>$role]); $this->reviewer=$this->employee('PER02','Yönetici','performance-e2',$reviewerUser->id);
    }

    public function test_360_score_is_server_calculated_and_calibration_preserves_original():void
    {
        $cycle=$this->cycle(); $template=app(CreatePerformanceTemplateAction::class)->execute('360',[['title'=>'Yetkinlik','questions'=>[['id'=>'quality','label'=>'Kalite','weight'=>60],['id'=>'team','label'=>'Takım','weight'=>40]]]]);
        $evaluation=app(CreateEvaluationAssignmentAction::class)->execute($cycle,$template,$this->employee,$this->reviewer,ReviewerType::Manager);
        $submitted=app(SubmitPerformanceEvaluationAction::class)->execute($evaluation,['quality'=>5,'team'=>3]);
        $this->assertSame(PerformanceEvaluationStatus::Submitted,$submitted->status); $this->assertSame('84.00',$submitted->overall_score);
        $calibrated=app(CalibratePerformanceEvaluationAction::class)->execute($submitted,80,'Ekipler arası ortak kalibrasyon kararı');
        $this->assertSame('84.00',$calibrated->overall_score); $this->assertSame('80.00',$calibrated->calibrated_score); $this->assertNotNull($calibrated->calibrated_at);
    }

    public function test_submitted_evaluation_is_immutable():void
    {
        $cycle=$this->cycle(); $template=app(CreatePerformanceTemplateAction::class)->execute('Kısa',[['title'=>'Genel','questions'=>[['id'=>'q1','label'=>'Soru','weight'=>100]]]]); $evaluation=app(CreateEvaluationAssignmentAction::class)->execute($cycle,$template,$this->employee,$this->reviewer,ReviewerType::Peer); app(SubmitPerformanceEvaluationAction::class)->execute($evaluation,['q1'=>4]);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class); app(SubmitPerformanceEvaluationAction::class)->execute($evaluation->fresh(),['q1'=>1]);
    }

    public function test_goal_weights_cannot_exceed_one_hundred():void
    {
        $cycle=$this->cycle(); app(CreatePerformanceGoalAction::class)->execute($cycle,$this->employee,['title'=>'Kalite','metric_unit'=>'%','target_value'=>100,'weight'=>70]);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class); app(CreatePerformanceGoalAction::class)->execute($cycle,$this->employee,['title'=>'Hız','metric_unit'=>'adet','target_value'=>10,'weight'=>31]);
    }

    public function test_competency_matrix_records_gap_and_evidence():void
    {
        $cycle=$this->cycle(); $competency=HrCompetency::create(['legal_entity_id'=>$this->tenant->id,'code'=>'LEAD','name'=>'Liderlik','is_active'=>true]); $row=app(AssessCompetencyAction::class)->execute($this->employee,$competency,$cycle,2,4,'Takım liderliği gözlemi');
        $this->assertSame(2,$row->current_level); $this->assertSame(4,$row->target_level); $this->assertSame('Takım liderliği gözlemi',$row->evidence);
    }

    public function test_goal_progress_update_is_audited():void
    {
        $cycle=$this->cycle(); $goal=app(CreatePerformanceGoalAction::class)->execute($cycle,$this->employee,['title'=>'Teslimat','metric_unit'=>'adet','target_value'=>20,'weight'=>100]);
        $updated=app(UpdatePerformanceGoalProgressAction::class)->execute($goal,12);
        $this->assertSame('12.00',$updated->current_value);
        $this->assertDatabaseHas('activity_logs',['user_id'=>$this->user->id,'action'=>'performance_goal_progress_updated']);
    }

    public function test_self_service_lists_only_evaluations_assigned_to_current_employee():void
    {
        $cycle=$this->cycle(); $template=app(CreatePerformanceTemplateAction::class)->execute('Gizlilik',[['title'=>'Genel','questions'=>[['id'=>'q1','label'=>'Soru','weight'=>100]]]]);
        $mine=app(CreateEvaluationAssignmentAction::class)->execute($cycle,$template,$this->reviewer,$this->employee,ReviewerType::Peer);
        app(CreateEvaluationAssignmentAction::class)->execute($cycle,$template,$this->employee,$this->reviewer,ReviewerType::Manager);

        Livewire::test(\App\Modules\Hr\Performance\Livewire\PerformanceWorkspace::class,['selfService'=>true])
            ->assertViewHas('evaluations',fn($rows)=>$rows->total()===1&&$rows->first()->is($mine));
    }

    private function cycle(){return app(CreatePerformanceCycleAction::class)->execute(['name'=>'2026','starts_on'=>'2026-01-01','ends_on'=>'2026-12-31','evaluation_starts_on'=>'2026-11-01','evaluation_ends_on'=>'2026-12-15']);}
    private function employee(string $number,string $first,string $hash,int $userId):HrEmployee{return HrEmployee::withoutGlobalScope('tenant')->create(['legal_entity_id'=>$this->tenant->id,'user_id'=>$userId,'employee_number'=>$number,'national_id_encrypted'=>'enc','national_id_hash'=>$hash,'national_id_last_four'=>'0001','first_name'=>$first,'last_name'=>'Test','status'=>'active']);}
}
