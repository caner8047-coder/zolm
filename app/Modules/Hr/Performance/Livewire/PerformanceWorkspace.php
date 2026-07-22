<?php

namespace App\Modules\Hr\Performance\Livewire;

use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Organization\Models\HrDepartment;
use App\Modules\Hr\Organization\Models\HrTeam;
use App\Modules\Hr\Organization\Models\HrUnit;
use App\Modules\Hr\Performance\Actions\AssessCompetencyAction;
use App\Modules\Hr\Performance\Actions\BulkAssignPerformanceEvaluationsAction;
use App\Modules\Hr\Performance\Actions\CalibratePerformanceEvaluationAction;
use App\Modules\Hr\Performance\Actions\CreateEvaluationAssignmentAction;
use App\Modules\Hr\Performance\Actions\CreatePerformanceCycleAction;
use App\Modules\Hr\Performance\Actions\CreatePerformanceGoalAction;
use App\Modules\Hr\Performance\Actions\CreatePerformanceTemplateAction;
use App\Modules\Hr\Performance\Actions\SendPerformanceRemindersAction;
use App\Modules\Hr\Performance\Actions\SubmitPerformanceEvaluationAction;
use App\Modules\Hr\Performance\Actions\UpdatePerformanceGoalProgressAction;
use App\Modules\Hr\Performance\Enums\PerformanceCycleStatus;
use App\Modules\Hr\Performance\Enums\ReviewerType;
use App\Modules\Hr\Performance\Models\HrCompetency;
use App\Modules\Hr\Performance\Models\HrEmployeeCompetency;
use App\Modules\Hr\Performance\Models\HrPerformanceCycle;
use App\Modules\Hr\Performance\Models\HrPerformanceEvaluation;
use App\Modules\Hr\Performance\Models\HrPerformanceGoal;
use App\Modules\Hr\Performance\Models\HrPerformanceResult;
use App\Modules\Hr\Performance\Models\HrPerformanceTemplate;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Livewire\Component;
use Livewire\WithPagination;

class PerformanceWorkspace extends Component
{
    use WithPagination;

    public bool $selfService = false;
    public string $name = '';
    public string $startsOn = '';
    public string $endsOn = '';
    public string $evaluationStartsOn = '';
    public string $evaluationEndsOn = '';
    public int $anonymityThreshold = 3;
    public bool $autoReminders = true;
    public string $templateName = 'Standart 360';
    public array $templateBuilder = [];
    public ?int $cycleId = null;
    public ?int $goalEmployeeId = null;
    public string $goalType = 'kpi';
    public string $goalMeasurementType = 'numeric';
    public string $goalTitle = '';
    public string $goalDescription = '';
    public string $metricUnit = '%';
    public string $baselineValue = '0';
    public string $targetValue = '100';
    public string $targetText = '';
    public string $currentValue = '0';
    public string $goalWeight = '';
    public array $goalValues = [];
    public array $goalNotes = [];
    public array $goalEvidence = [];
    public ?int $assignmentEmployeeId = null;
    public ?int $reviewerEmployeeId = null;
    public ?int $templateId = null;
    public string $reviewerType = 'manager';
    public string $reviewerWeight = '100';
    public bool $reviewerAnonymous = false;
    public string $bulkScope = 'company';
    public ?int $bulkScopeId = null;
    public array $bulkReviewerTypes = ['self', 'manager'];
    public ?int $editingEvaluationId = null;
    public array $answers = [];
    public ?int $calibratingId = null;
    public string $calibratedScore = '';
    public string $calibrationNote = '';
    public string $competencyCode = '';
    public string $competencyName = '';
    public ?int $competencyEmployeeId = null;
    public ?int $competencyId = null;
    public int $currentLevel = 3;
    public int $targetLevel = 4;
    public string $competencyEvidence = '';
    public string $statusFilter = '';
    public string $cycleFilter = '';
    public string $reviewerTypeFilter = '';
    public string $sortField = 'created_at';
    public string $sortDirection = 'desc';
    public array $visibleColumns = ['employee', 'reviewer', 'cycle', 'score', 'status', 'actions'];

    public const COLUMNS = ['employee' => 'Çalışan', 'reviewer' => 'Değerlendirici', 'cycle' => 'Döngü', 'score' => 'Puan', 'status' => 'Durum', 'actions' => 'İşlem'];
    private const SORTABLE = ['created_at', 'overall_score', 'status'];

    public function mount(bool $selfService = false): void
    {
        $this->selfService = $selfService;
        $this->startsOn = today()->startOfYear()->toDateString();
        $this->endsOn = today()->endOfYear()->toDateString();
        $this->evaluationStartsOn = today()->toDateString();
        $this->evaluationEndsOn = today()->endOfYear()->toDateString();
        $this->templateBuilder = [[
            'title' => 'Genel Yetkinlikler',
            'questions' => [
                ['id' => 'collaboration', 'label' => 'İş birliği', 'type' => 'rating', 'required' => true, 'weight' => 25],
                ['id' => 'ownership', 'label' => 'Sorumluluk', 'type' => 'rating', 'required' => true, 'weight' => 25],
                ['id' => 'quality', 'label' => 'İş kalitesi', 'type' => 'rating', 'required' => true, 'weight' => 25],
                ['id' => 'development', 'label' => 'Gelişim', 'type' => 'rating', 'required' => true, 'weight' => 25],
                ['id' => 'feedback', 'label' => 'Gelişim için geri bildiriminiz', 'type' => 'text', 'required' => false, 'weight' => 0],
            ],
        ]];
    }

    public function createCycle(CreatePerformanceCycleAction $action): void
    {
        $this->validate(['name' => 'required|string|max:160', 'startsOn' => 'required|date', 'endsOn' => 'required|date', 'evaluationStartsOn' => 'required|date', 'evaluationEndsOn' => 'required|date', 'anonymityThreshold' => 'required|integer|min:2|max:20']);
        $cycle = $action->execute([
            'name' => $this->name, 'starts_on' => $this->startsOn, 'ends_on' => $this->endsOn,
            'evaluation_starts_on' => $this->evaluationStartsOn, 'evaluation_ends_on' => $this->evaluationEndsOn,
            'anonymity_threshold' => $this->anonymityThreshold, 'auto_reminders' => $this->autoReminders,
        ]);
        $this->cycleId = $cycle->id;
        $this->name = '';
        session()->flash('success', 'Performans döngüsü oluşturuldu.');
    }

    public function transitionCycle(int $id, string $status, CreatePerformanceCycleAction $action): void
    {
        $action->transition($this->cycle($id), PerformanceCycleStatus::from($status));
        session()->flash('success', 'Döngü aşaması güncellendi.');
    }

    public function addTemplateSection(): void
    {
        $this->templateBuilder[] = ['title' => 'Yeni bölüm', 'questions' => []];
    }

    public function removeTemplateSection(int $section): void
    {
        unset($this->templateBuilder[$section]);
        $this->templateBuilder = array_values($this->templateBuilder);
    }

    public function addTemplateQuestion(int $section): void
    {
        abort_unless(isset($this->templateBuilder[$section]), 404);
        $this->templateBuilder[$section]['questions'][] = [
            'id' => 'q_'.substr(md5((string) microtime(true)), 0, 8), 'label' => 'Yeni soru',
            'type' => 'text', 'required' => false, 'weight' => 0,
        ];
    }

    public function removeTemplateQuestion(int $section, int $question): void
    {
        unset($this->templateBuilder[$section]['questions'][$question]);
        $this->templateBuilder[$section]['questions'] = array_values($this->templateBuilder[$section]['questions']);
    }

    public function createTemplate(CreatePerformanceTemplateAction $action): void
    {
        $template = $action->execute($this->templateName, $this->templateBuilder);
        $this->templateId = $template->id;
        session()->flash('success', 'Değerlendirme şablonu sürümü oluşturuldu.');
    }

    public function createGoal(CreatePerformanceGoalAction $action): void
    {
        $this->validate(['cycleId' => 'required|integer', 'goalEmployeeId' => 'required|integer', 'goalTitle' => 'required|string|max:200', 'goalWeight' => 'required|numeric|min:0.01|max:100']);
        $action->execute($this->cycle($this->cycleId), $this->employee($this->goalEmployeeId), [
            'type' => $this->goalType, 'measurement_type' => $this->goalMeasurementType,
            'title' => $this->goalTitle, 'description' => $this->goalDescription,
            'metric_unit' => $this->metricUnit, 'baseline_value' => $this->baselineValue,
            'target_value' => $this->targetValue, 'target_text' => $this->targetText,
            'current_value' => $this->currentValue, 'weight' => $this->goalWeight,
        ]);
        $this->reset(['goalTitle', 'goalDescription', 'goalWeight', 'targetText']);
        session()->flash('success', 'Hedef eklendi.');
    }

    public function updateGoal(int $id, UpdatePerformanceGoalProgressAction $action): void
    {
        $value = $this->goalValues[$id] ?? null;
        abort_unless($value !== null && $value !== '', 422, 'Güncel değer zorunludur.');
        $goal = $this->goal($id);
        $action->execute($goal, $value, $this->goalNotes[$id] ?? '', $this->goalEvidence[$id] ?? null);
        unset($this->goalValues[$id], $this->goalNotes[$id], $this->goalEvidence[$id]);
        session()->flash('success', 'Hedef check-in kaydı oluşturuldu.');
    }

    public function assignEvaluation(CreateEvaluationAssignmentAction $action): void
    {
        $this->validate(['cycleId' => 'required|integer', 'templateId' => 'required|integer', 'assignmentEmployeeId' => 'required|integer', 'reviewerEmployeeId' => 'required|integer', 'reviewerType' => 'required|in:self,manager,peer,direct_report,hr', 'reviewerWeight' => 'required|numeric|min:0.01|max:100']);
        $action->execute($this->cycle($this->cycleId), $this->template($this->templateId), $this->employee($this->assignmentEmployeeId), $this->employee($this->reviewerEmployeeId), ReviewerType::from($this->reviewerType), (float) $this->reviewerWeight, $this->reviewerAnonymous);
        session()->flash('success', 'Değerlendirme görevi oluşturuldu.');
    }

    public function bulkAssign(BulkAssignPerformanceEvaluationsAction $action): void
    {
        $this->validate(['cycleId' => 'required|integer', 'templateId' => 'required|integer', 'bulkScope' => 'required|in:company,department,unit,team', 'bulkReviewerTypes' => 'required|array|min:1']);
        $count = $action->execute($this->cycle($this->cycleId), $this->template($this->templateId), $this->bulkScope, $this->bulkScopeId, $this->bulkReviewerTypes);
        session()->flash('success', "{$count} yeni değerlendirme görevi organizasyon ilişkilerinden oluşturuldu.");
    }

    public function sendReminders(int $cycleId, SendPerformanceRemindersAction $action): void
    {
        $count = $action->execute($this->cycle($cycleId), true);
        session()->flash('success', "{$count} değerlendiriciye hatırlatma gönderildi.");
    }

    public function openEvaluation(int $id): void
    {
        $evaluation = $this->evaluation($id)->load('template');
        abort_unless($evaluation->reviewer?->user_id === auth()->id(), 403);
        $this->editingEvaluationId = $id;
        $this->answers = $evaluation->answers ?? [];
    }

    public function submitEvaluation(SubmitPerformanceEvaluationAction $action): void
    {
        $action->execute($this->evaluation($this->editingEvaluationId), $this->answers);
        $this->reset(['editingEvaluationId', 'answers']);
        session()->flash('success', 'Değerlendirme değiştirilemez şekilde gönderildi.');
    }

    public function startCalibration(int $id): void
    {
        $evaluation = $this->evaluation($id);
        $this->calibratingId = $id;
        $this->calibratedScore = (string) $evaluation->overall_score;
        $this->calibrationNote = '';
    }

    public function calibrate(CalibratePerformanceEvaluationAction $action): void
    {
        $this->validate(['calibratedScore' => 'required|numeric|min:0|max:100', 'calibrationNote' => 'required|string|max:2000']);
        $action->execute($this->evaluation($this->calibratingId), (float) $this->calibratedScore, $this->calibrationNote);
        $this->reset(['calibratingId', 'calibratedScore', 'calibrationNote']);
        session()->flash('success', 'Kalibrasyon kaydedildi.');
    }

    public function createCompetency(HrAuditService $audit): void
    {
        abort_unless(auth()->user()?->hasHrPermission('hr.performance.manage_templates'), 403);
        $this->validate(['competencyCode' => 'required|string|max:60', 'competencyName' => 'required|string|max:160']);
        $tenant = app(TenantContext::class)->getId();
        abort_if(HrCompetency::withoutGlobalScope('tenant')->where('legal_entity_id', $tenant)->where('code', strtoupper(trim($this->competencyCode)))->exists(), 422, 'Yetkinlik kodu kullanılıyor.');
        $competency = HrCompetency::create(['legal_entity_id' => $tenant, 'code' => strtoupper(trim($this->competencyCode)), 'name' => trim($this->competencyName), 'is_active' => true]);
        $audit->log('performance_competency_created', $competency);
        $this->reset(['competencyCode', 'competencyName']);
        session()->flash('success', 'Yetkinlik tanımlandı.');
    }

    public function assessCompetency(AssessCompetencyAction $action): void
    {
        $this->validate(['competencyEmployeeId' => 'required|integer', 'competencyId' => 'required|integer', 'currentLevel' => 'required|integer|min:1|max:5', 'targetLevel' => 'required|integer|min:1|max:5']);
        $action->execute($this->employee($this->competencyEmployeeId), $this->competency($this->competencyId), $this->cycleId ? $this->cycle($this->cycleId) : null, $this->currentLevel, $this->targetLevel, $this->competencyEvidence ?: null);
        session()->flash('success', 'Yetkinlik değerlendirmesi kaydedildi.');
    }

    public function sortTable(string $field): void
    {
        abort_unless(in_array($field, self::SORTABLE, true), 422);
        if ($this->sortField === $field) $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        else { $this->sortField = $field; $this->sortDirection = 'asc'; }
    }

    public function toggleColumn(string $key): void
    {
        abort_unless(isset(self::COLUMNS[$key]), 422);
        if (in_array($key, ['employee', 'actions'], true)) return;
        $this->visibleColumns = in_array($key, $this->visibleColumns, true)
            ? array_values(array_diff($this->visibleColumns, [$key]))
            : array_values(array_intersect(array_keys(self::COLUMNS), [...$this->visibleColumns, $key]));
    }

    public function render()
    {
        $tenant = app(TenantContext::class)->getId();
        $employees = HrEmployee::withoutGlobalScope('tenant')->where('legal_entity_id', $tenant)->active()->with('activeEmployment')->orderBy('first_name')->get();
        $own = $this->selfService ? $this->ownEmployee() : null;
        $query = HrPerformanceEvaluation::withoutGlobalScope('tenant')->where('legal_entity_id', $tenant)->with(['employee', 'reviewer', 'cycle', 'template']);
        if ($own) $query->where('reviewer_employee_id', $own->id);
        if ($this->statusFilter !== '') $query->where('status', $this->statusFilter);
        if ($this->cycleFilter !== '') $query->where('cycle_id', $this->cycleFilter);
        if ($this->reviewerTypeFilter !== '') $query->where('reviewer_type', $this->reviewerTypeFilter);
        $cycles = HrPerformanceCycle::withoutGlobalScope('tenant')->where('legal_entity_id', $tenant)->withCount(['evaluations', 'evaluations as completed_evaluations_count' => fn ($q) => $q->whereIn('status', ['submitted', 'calibrated'])])->latest('starts_on')->get();
        $results = HrPerformanceResult::withoutGlobalScope('tenant')->where('legal_entity_id', $tenant)
            ->when($own, fn ($q) => $q->where('employee_id', $own->id)->whereHas('cycle', fn ($cycle) => $cycle->where('status', 'closed')))
            ->when(! $own && $this->cycleFilter !== '', fn ($q) => $q->where('cycle_id', $this->cycleFilter))
            ->with(['employee', 'cycle'])->orderByDesc('overall_score')->limit(100)->get();

        return view('livewire.hr.performance.performance-workspace', [
            'evaluations' => $query->orderBy($this->sortField, $this->sortDirection)->paginate(20),
            'cycles' => $cycles,
            'templates' => HrPerformanceTemplate::withoutGlobalScope('tenant')->where('legal_entity_id', $tenant)->where('is_active', true)->latest('version')->get(),
            'goals' => HrPerformanceGoal::withoutGlobalScope('tenant')->where('legal_entity_id', $tenant)->when($own, fn ($q) => $q->where('employee_id', $own->id))->with(['employee', 'cycle', 'checkIns' => fn ($q) => $q->latest()->limit(3)])->latest()->limit(30)->get(),
            'results' => $results,
            'competencies' => HrCompetency::withoutGlobalScope('tenant')->where('legal_entity_id', $tenant)->where('is_active', true)->orderBy('name')->get(),
            'competencyAssessments' => HrEmployeeCompetency::withoutGlobalScope('tenant')->where('legal_entity_id', $tenant)->when($own, fn ($q) => $q->where('employee_id', $own->id))->with(['employee', 'competency', 'cycle'])->latest()->limit(30)->get(),
            'employees' => $employees,
            'departments' => HrDepartment::withoutGlobalScope('tenant')->where('legal_entity_id', $tenant)->where('is_active', true)->orderBy('name')->get(),
            'units' => HrUnit::whereHas('department', fn ($q) => $q->where('legal_entity_id', $tenant))->where('is_active', true)->orderBy('name')->get(),
            'teams' => HrTeam::whereHas('unit.department', fn ($q) => $q->where('legal_entity_id', $tenant))->where('is_active', true)->orderBy('name')->get(),
            'columnLabels' => self::COLUMNS,
        ])->layout('layouts.app');
    }

    private function cycle(?int $id): HrPerformanceCycle { return HrPerformanceCycle::withoutGlobalScope('tenant')->where('legal_entity_id', app(TenantContext::class)->getId())->findOrFail($id); }
    private function template(?int $id): HrPerformanceTemplate { return HrPerformanceTemplate::withoutGlobalScope('tenant')->where('legal_entity_id', app(TenantContext::class)->getId())->findOrFail($id); }
    private function employee(?int $id): HrEmployee { return HrEmployee::withoutGlobalScope('tenant')->where('legal_entity_id', app(TenantContext::class)->getId())->findOrFail($id); }
    private function competency(?int $id): HrCompetency { return HrCompetency::withoutGlobalScope('tenant')->where('legal_entity_id', app(TenantContext::class)->getId())->findOrFail($id); }
    private function goal(int $id): HrPerformanceGoal { return HrPerformanceGoal::withoutGlobalScope('tenant')->where('legal_entity_id', app(TenantContext::class)->getId())->with('cycle')->findOrFail($id); }
    private function evaluation(?int $id): HrPerformanceEvaluation { abort_unless($id, 404); return HrPerformanceEvaluation::withoutGlobalScope('tenant')->where('legal_entity_id', app(TenantContext::class)->getId())->with(['reviewer', 'cycle'])->findOrFail($id); }
    private function ownEmployee(): HrEmployee { return HrEmployee::withoutGlobalScope('tenant')->where('legal_entity_id', app(TenantContext::class)->getId())->where('user_id', auth()->id())->firstOrFail(); }
}
