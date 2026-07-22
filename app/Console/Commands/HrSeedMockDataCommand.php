<?php

namespace App\Console\Commands;

use App\Models\HrFile;
use App\Models\HrHoliday;
use App\Models\HrLicense;
use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Hr\Advance\Enums\AdvanceStatus;
use App\Modules\Hr\Advance\Models\HrAdvance;
use App\Modules\Hr\Analytics\Actions\BuildHrAnalyticsSnapshotAction;
use App\Modules\Hr\Asset\Enums\AssetAssignmentStatus;
use App\Modules\Hr\Asset\Enums\AssetStatus;
use App\Modules\Hr\Asset\Models\HrAsset;
use App\Modules\Hr\Asset\Models\HrAssetAssignment;
use App\Modules\Hr\Asset\Models\HrAssetCategory;
use App\Modules\Hr\Attendance\Enums\AttendanceEventType;
use App\Modules\Hr\Attendance\Models\HrAttendanceAnomaly;
use App\Modules\Hr\Attendance\Models\HrAttendanceDevice;
use App\Modules\Hr\Attendance\Models\HrAttendanceEvent;
use App\Modules\Hr\Compensation\Models\HrBenefit;
use App\Modules\Hr\Compensation\Models\HrEmployeeBenefit;
use App\Modules\Hr\Compensation\Models\HrSalaryBand;
use App\Modules\Hr\Compensation\Models\HrSalaryRecord;
use App\Modules\Hr\Core\Services\HrIntegrationOutboxService;
use App\Modules\Hr\Document\Enums\DocumentCategory;
use App\Modules\Hr\Document\Enums\DocumentSensitivity;
use App\Modules\Hr\Document\Enums\DocumentStatus;
use App\Modules\Hr\Document\Enums\VerificationStatus;
use App\Modules\Hr\Document\Models\HrDocumentRequest;
use App\Modules\Hr\Document\Models\HrDocumentRequirement;
use App\Modules\Hr\Document\Models\HrDocumentType;
use App\Modules\Hr\Document\Models\HrEmployeeDocument;
use App\Modules\Hr\Document\Models\HrEmployeeDocumentVersion;
use App\Modules\Hr\Engagement\Models\HrRecognition;
use App\Modules\Hr\Engagement\Models\HrSurvey;
use App\Modules\Hr\Engagement\Models\HrSurveyResponse;
use App\Modules\Hr\Expense\Enums\ExpenseStatus;
use App\Modules\Hr\Expense\Models\HrExpense;
use App\Modules\Hr\Expense\Models\HrExpenseCategory;
use App\Modules\Hr\Leave\Enums\LeaveApprovalStatus;
use App\Modules\Hr\Leave\Enums\LeavePolicyScope;
use App\Modules\Hr\Leave\Enums\LeaveRequestStatus;
use App\Modules\Hr\Leave\Enums\LeaveUnit;
use App\Modules\Hr\Leave\Models\HrLeaveApprovalStep;
use App\Modules\Hr\Leave\Models\HrLeaveBalance;
use App\Modules\Hr\Leave\Models\HrLeavePolicy;
use App\Modules\Hr\Leave\Models\HrLeaveRequest;
use App\Modules\Hr\Leave\Models\HrLeaveType;
use App\Modules\Hr\Lifecycle\Models\HrOffboardingChecklist;
use App\Modules\Hr\Lifecycle\Models\HrOnboardingChecklist;
use App\Modules\Hr\Lifecycle\Models\HrOnboardingTask;
use App\Modules\Hr\Organization\Models\HrBranch;
use App\Modules\Hr\Organization\Models\HrCostCenter;
use App\Modules\Hr\Organization\Models\HrDepartment;
use App\Modules\Hr\Organization\Models\HrPosition;
use App\Modules\Hr\Organization\Models\HrSgkWorkplace;
use App\Modules\Hr\Organization\Models\HrTeam;
use App\Modules\Hr\Organization\Models\HrUnit;
use App\Modules\Hr\Overtime\Enums\OvertimeRequestStatus;
use App\Modules\Hr\Overtime\Models\HrOvertimeRequest;
use App\Modules\Hr\Overtime\Models\HrOvertimeType;
use App\Modules\Hr\Payroll\Actions\CalculatePayrollPeriodAction;
use App\Modules\Hr\Payroll\Actions\PreparePayrollPeriodAction;
use App\Modules\Hr\Payroll\Models\HrPayrollAdjustment;
use App\Modules\Hr\Payroll\Models\HrPayrollPeriod;
use App\Modules\Hr\Payroll\Models\HrPayrollRule;
use App\Modules\Hr\Payroll\Models\HrPayrollTaxOpening;
use App\Modules\Hr\Payroll\Services\PayrollRuleConfiguration;
use App\Modules\Hr\Performance\Enums\PerformanceCycleStatus;
use App\Modules\Hr\Performance\Enums\PerformanceEvaluationStatus;
use App\Modules\Hr\Performance\Enums\ReviewerType;
use App\Modules\Hr\Performance\Models\HrCompetency;
use App\Modules\Hr\Performance\Models\HrEmployeeCompetency;
use App\Modules\Hr\Performance\Models\HrPerformanceCycle;
use App\Modules\Hr\Performance\Models\HrPerformanceEvaluation;
use App\Modules\Hr\Performance\Models\HrPerformanceGoal;
use App\Modules\Hr\Personnel\Enums\EmployeeStatus;
use App\Modules\Hr\Personnel\Enums\EmploymentType;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use App\Modules\Hr\Personnel\Models\HrEmploymentRecord;
use App\Modules\Hr\Recruitment\Models\HrApplication;
use App\Modules\Hr\Recruitment\Models\HrCandidate;
use App\Modules\Hr\Recruitment\Models\HrJobOffer;
use App\Modules\Hr\Recruitment\Models\HrJobPosting;
use App\Modules\Hr\Safety\Models\HrHealthRecord;
use App\Modules\Hr\Safety\Models\HrSafetyAction;
use App\Modules\Hr\Safety\Models\HrSafetyIncident;
use App\Modules\Hr\Shift\Enums\ShiftAssignmentStatus;
use App\Modules\Hr\Shift\Models\HrShiftAssignment;
use App\Modules\Hr\Shift\Models\HrShiftTemplate;
use App\Modules\Hr\Support\Models\HrSupportMessage;
use App\Modules\Hr\Support\Models\HrSupportTicket;
use App\Modules\Hr\Timesheet\Actions\CalculateTimesheetPeriodAction;
use App\Modules\Hr\Timesheet\Actions\CloseTimesheetPeriodAction;
use App\Modules\Hr\Timesheet\Enums\TimesheetPeriodStatus;
use App\Modules\Hr\Timesheet\Enums\TimesheetStatus;
use App\Modules\Hr\Timesheet\Models\HrTimesheet;
use App\Modules\Hr\Timesheet\Models\HrTimesheetPeriod;
use App\Modules\Hr\Training\Models\HrCertificate;
use App\Modules\Hr\Training\Models\HrTrainingCourse;
use App\Modules\Hr\Training\Models\HrTrainingEnrollment;
use App\Modules\Hr\Training\Models\HrTrainingSession;
use Database\Seeders\Hr\HrPermissionSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class HrSeedMockDataCommand extends Command
{
    protected $signature = 'hr:seed-mock-data
                            {--legal-entity-id= : Target Legal Entity ID (defaults to Admin user\'s legal entity)}
                            {--fresh : Rebuild deterministic mock scenario records without deleting non-mock HR data}
                            {--password= : Password for newly created mock users; HR_MOCK_PASSWORD is also supported}
                            {--force : Allow execution outside local/testing environments}';

    protected $description = 'Seed realistic mock data (14 employees, active workflows across all 17 sub-modules) for testing and admin preview';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing']) && ! $this->option('force')) {
            $this->error('Mock İK verisi yalnız local/testing ortamında çalıştırılabilir. Bilinçli kullanım için --force gerekir.');
            return Command::FAILURE;
        }

        $mockUserPassword = trim((string) ($this->option('password') ?: env('HR_MOCK_PASSWORD', '')));
        if ($mockUserPassword === '' && app()->environment(['local', 'testing'])) {
            $mockUserPassword = 'ZolmMock!2026';
        }
        if (mb_strlen($mockUserPassword) < 12) {
            $this->error('Mock kullanıcı parolası en az 12 karakter olmalıdır. Local/test dışında --password veya HR_MOCK_PASSWORD zorunludur.');
            return Command::FAILURE;
        }

        $tenantId = $this->option('legal-entity-id');
        $admin = User::withoutGlobalScopes()->where('email', 'admin@zolm.test')->first()
            ?? User::withoutGlobalScopes()->where('role', 'admin')->first();

        if (! $admin) {
            $this->error('Admin user not found.');
            return Command::FAILURE;
        }

        if (!$tenantId) {
            $legalEntity = LegalEntity::where('user_id', $admin->id)->first();

            if (!$legalEntity) {
                $legalEntity = LegalEntity::create([
                    'user_id' => $admin->id,
                    'name' => 'ZOLM İK Teknoloji A.Ş.',
                    'tax_number' => '9876543210',
                    'tax_office' => 'Kadıköy',
                    'company_type' => 'A.Ş.',
                    'email' => 'ik@zolm.test',
                    'phone' => '+90 216 555 0000',
                    'is_active' => true,
                ]);
            }

            $tenantId = $legalEntity->id;
        } else {
            $tenantId = (int) $tenantId;
        }

        $this->info("Seeding HR mock data for LegalEntity ID: {$tenantId}");

        $legalEntity = LegalEntity::findOrFail($tenantId);
        app(\App\Modules\Hr\Core\Services\TenantContext::class)->set($legalEntity);
        $previousUser = Auth::user();
        Auth::login($admin);

        try {
            $this->seedPermissions($admin);
            $this->cleanupLegacyMockRows($tenantId, (bool) $this->option('fresh'));

            $this->seedLicenses($tenantId);
            $org = $this->seedOrganization($tenantId);
            $employees = $this->seedEmployees($tenantId, $org, $admin, $mockUserPassword);
            $this->seedCompensation($tenantId, $employees);
            $this->seedDocuments($tenantId, $employees, $admin);
            $this->seedLeaves($tenantId, $employees);
            $this->seedAttendance($tenantId, $employees);
            $this->seedShifts($tenantId, $employees);
            $this->seedTimesheetAndOvertime($tenantId, $employees, $admin);
            $this->seedPayroll($tenantId, $employees, $admin);
            $this->seedExpensesAndAdvances($tenantId, $employees, $admin);
            $this->seedAssets($tenantId, $employees);
            $this->seedPerformance($tenantId, $employees);
            $this->seedTraining($tenantId, $employees);
            $this->seedEngagement($tenantId, $employees);
            $this->seedSafety($tenantId, $employees);
            $this->seedRecruitmentAndLifecycle($tenantId, $employees);
            $this->seedAnalyticsAndSupport($tenantId, $employees);

            $employeeCount = count($employees);
            $this->info("✓ İK mock senaryosu {$employeeCount} aktif çalışan için üretim akışlarıyla uyumlu olarak hazırlandı.");
            return Command::SUCCESS;
        } finally {
            $previousUser ? Auth::login($previousUser) : Auth::logout();
        }
    }

    private function seedPermissions(User $admin): void
    {
        app(HrPermissionSeeder::class)->run();

        $adminRoleId = DB::table('roles')->where('slug', 'hr_admin')->value('id');
        if ($adminRoleId) {
            DB::table('model_has_roles')->insertOrIgnore([
                'role_id' => $adminRoleId,
                'model_id' => $admin->id,
                'model_type' => User::class,
            ]);
        }
    }

    private function cleanupLegacyMockRows(int $tenantId, bool $rebuildDeterministicScenario): void
    {
        try {
            HrAttendanceEvent::withoutGlobalScope('tenant')
                ->where('legal_entity_id', $tenantId)
                ->where('source_key', 'like', 'EVT-KEY-%')
                ->delete();

            if ($rebuildDeterministicScenario) {
                HrAttendanceEvent::withoutGlobalScope('tenant')
                    ->where('legal_entity_id', $tenantId)
                    ->where('source_key', 'like', 'MOCK-PDKS-%')
                    ->delete();
            }
        } catch (\Throwable $e) {}

        try {
            HrPayrollPeriod::withoutGlobalScope('tenant')
                ->where('legal_entity_id', $tenantId)
                ->where('status', 'calculated')
                ->whereNull('source_hash')
                ->delete();
        } catch (\Throwable $e) {}

        HrPayrollRule::withoutGlobalScope('tenant')
            ->where('legal_entity_id', $tenantId)
            ->where('code', 'RULE-2026-TR')
            ->delete();

        try {
            $legacyTimesheet = HrTimesheetPeriod::withoutGlobalScope('tenant')
                ->where('legal_entity_id', $tenantId)
                ->where('name', now()->translatedFormat('F Y').' Puantaj Dönemi')
                ->where('status', TimesheetPeriodStatus::Draft->value)
                ->whereNull('calculated_at')
                ->first();
            if ($legacyTimesheet && $legacyTimesheet->timesheets()->exists()
                && ! $legacyTimesheet->timesheets()->whereDate('work_date', '!=', now()->toDateString())->exists()) {
                $legacyTimesheet->delete();
            }
        } catch (\Throwable $e) {}

        try {
            DB::table('hr_analytics_snapshots')
                ->where('legal_entity_id', $tenantId)
                ->where('source_hash', hash('sha256', 'ANALYSIS-2026-07'))
                ->delete();
        } catch (\Throwable $e) {}
    }

    private function seedLicenses(int $tenantId): void
    {
        $modules = [
            'personel' => 'Personel Yönetimi',
            'izin' => 'İzin Yönetimi',
            'vardiya' => 'Vardiya Planlama',
            'pdks' => 'PDKS ve Devam Takibi',
            'puantaj' => 'Puantaj Yönetimi',
            'ucret' => 'Ücret ve Yan Haklar',
            'bordro' => 'Bordro Yönetimi',
            'masraf' => 'Masraf Yönetimi',
            'avans' => 'Avans Yönetimi',
            'zimmet' => 'Zimmet Yönetimi',
            'performans' => 'Performans Değerlendirme',
            'egitim' => 'Eğitim ve Gelişim',
            'baglilik' => 'Çalışan Bağlılığı',
            'aday_takip' => 'Aday Takip ve İşe Alım',
            'isg' => 'İş Sağlığı ve Güvenliği',
            'analitik' => 'İK Analitiği',
            'destek' => 'Çalışan Destek',
        ];

        foreach ($modules as $key => $name) {
            HrLicense::updateOrCreate(
                ['legal_entity_id' => $tenantId, 'module_key' => $key],
                [
                    'is_active' => true,
                    'max_employees' => 100,
                    'starts_at' => Carbon::now()->subYear(),
                    'expires_at' => Carbon::now()->addYears(5),
                ]
            );
        }
    }

    private function seedOrganization(int $tenantId): array
    {
        $sgkWorkplace = HrSgkWorkplace::withoutGlobalScope('tenant')->updateOrCreate(
            ['legal_entity_id' => $tenantId, 'code' => 'SGK-MERKEZ'],
            [
                'name' => 'ZOLM Merkez SGK İş Yeri',
                'sgk_workplace_no' => '2.1234.01.01.1234567.034.99-12',
                'address' => 'Kozyatağı, Kadıköy',
                'city' => 'İstanbul',
                'is_active' => true,
            ]
        );
        $branch = HrBranch::withoutGlobalScope('tenant')->updateOrCreate(
            ['legal_entity_id' => $tenantId, 'code' => 'MERKEZ'],
            [
                'sgk_workplace_id' => $sgkWorkplace->id,
                'name' => 'Merkez',
                'address' => 'Kozyatağı, Kadıköy',
                'city' => 'İstanbul',
                'phone' => '+90 216 555 0000',
                'is_active' => true,
            ]
        );
        $rootCostCenter = HrCostCenter::withoutGlobalScope('tenant')->updateOrCreate(
            ['legal_entity_id' => $tenantId, 'code' => 'CC-ZOLM'],
            ['name' => 'ZOLM Genel', 'is_active' => true]
        );

        $departmentsData = [
            'TECH' => 'Teknoloji & Yazılım',
            'HR' => 'İnsan Kaynakları',
            'SALES' => 'Satış & Pazarlama',
            'OPS' => 'Operasyon & Lojistik',
            'FIN' => 'Finans & Muhasebe',
        ];

        $departments = [];
        $costCenters = [];
        foreach ($departmentsData as $code => $name) {
            $costCenters[$code] = HrCostCenter::withoutGlobalScope('tenant')->updateOrCreate(
                ['legal_entity_id' => $tenantId, 'code' => 'CC-'.$code],
                ['parent_id' => $rootCostCenter->id, 'name' => $name.' Masraf Merkezi', 'is_active' => true]
            );
            $departments[$code] = HrDepartment::withoutGlobalScope('tenant')->updateOrCreate(
                ['legal_entity_id' => $tenantId, 'code' => $code],
                ['name' => $name, 'branch_id' => $branch->id, 'cost_center_id' => $costCenters[$code]->id, 'is_active' => true]
            );
        }

        $unitsData = [
            ['code' => 'U-DEV', 'name' => 'Yazılım Geliştirme', 'dept' => 'TECH'],
            ['code' => 'U-HR', 'name' => 'İK Operasyon & Yetenek', 'dept' => 'HR'],
            ['code' => 'U-SALES', 'name' => 'Kurumsal Satış', 'dept' => 'SALES'],
            ['code' => 'U-LOG', 'name' => 'Depo & Sevkiyat Operasyon', 'dept' => 'OPS'],
            ['code' => 'U-ACC', 'name' => 'Mali İşler & Muhasebe', 'dept' => 'FIN'],
        ];

        $units = [];
        foreach ($unitsData as $u) {
            $units[$u['code']] = HrUnit::updateOrCreate(
                ['department_id' => $departments[$u['dept']]->id, 'code' => $u['code']],
                ['name' => $u['name'], 'is_active' => true]
            );
        }

        $teamsData = [
            ['code' => 'T-BACKEND', 'name' => 'Backend Ekibi', 'unit' => 'U-DEV'],
            ['code' => 'T-FRONTEND', 'name' => 'Frontend Ekibi', 'unit' => 'U-DEV'],
            ['code' => 'T-RECRUIT', 'name' => 'Yetenek İşe Alım Ekibi', 'unit' => 'U-HR'],
            ['code' => 'T-FIELD', 'name' => 'Saha Satış Ekibi', 'unit' => 'U-SALES'],
            ['code' => 'T-WAREHOUSE', 'name' => 'Depo Operasyon Ekibi', 'unit' => 'U-LOG'],
            ['code' => 'T-FINANCE', 'name' => 'Genel Muhasebe Ekibi', 'unit' => 'U-ACC'],
        ];

        $teams = [];
        foreach ($teamsData as $t) {
            $teams[$t['code']] = HrTeam::updateOrCreate(
                ['unit_id' => $units[$t['unit']]->id, 'name' => $t['name']],
                ['is_active' => true]
            );
        }

        $positionsData = [
            ['code' => 'POS-HR-DIR', 'title' => 'İnsan Kaynakları Direktörü', 'dept' => 'HR'],
            ['code' => 'POS-TECH-ARCH', 'title' => 'Yazılım Mimar', 'dept' => 'TECH'],
            ['code' => 'POS-SR-BACKEND', 'title' => 'Senior Backend Geliştirici', 'dept' => 'TECH'],
            ['code' => 'POS-FRONTEND', 'title' => 'Frontend Geliştirici', 'dept' => 'TECH'],
            ['code' => 'POS-JR-DEV', 'title' => 'Junior Yazılım Geliştirici', 'dept' => 'TECH'],
            ['code' => 'POS-HR-SPEC', 'title' => 'İK Uzmanı', 'dept' => 'HR'],
            ['code' => 'POS-SALES-MGR', 'title' => 'Satış Müdürü', 'dept' => 'SALES'],
            ['code' => 'POS-SALES-SPEC', 'title' => 'Saha Satış Uzmanı', 'dept' => 'SALES'],
            ['code' => 'POS-MKTG-SPEC', 'title' => 'Dijital Pazarlama Uzmanı', 'dept' => 'SALES'],
            ['code' => 'POS-OPS-MGR', 'title' => 'Depo Operasyon Müdürü', 'dept' => 'OPS'],
            ['code' => 'POS-LOG-SPEC', 'title' => 'Lojistik Elemanı', 'dept' => 'OPS'],
            ['code' => 'POS-SR-ACC', 'title' => 'Kıdemli Muhasebeci', 'dept' => 'FIN'],
            ['code' => 'POS-CUST-SUPP', 'title' => 'Müşteri Temsilcisi', 'dept' => 'FIN'],
            ['code' => 'POS-SAFETY-SPEC', 'title' => 'İSG Uzmanı', 'dept' => 'HR'],
        ];

        $positions = [];
        foreach ($positionsData as $p) {
            $positions[$p['code']] = HrPosition::withoutGlobalScope('tenant')->updateOrCreate(
                ['legal_entity_id' => $tenantId, 'code' => $p['code']],
                ['title' => $p['title'], 'department_id' => $departments[$p['dept']]->id, 'is_active' => true]
            );
        }

        return compact('sgkWorkplace', 'branch', 'rootCostCenter', 'costCenters', 'departments', 'units', 'teams', 'positions');
    }

    private function seedEmployees(int $tenantId, array $org, User $adminUser, string $mockUserPassword): array
    {
        $employeesData = [
            [
                'number' => 'EMP10001',
                'first_name' => 'Ahmet',
                'last_name' => 'Yılmaz',
                'email' => 'ahmet.yilmaz@zolm.test',
                'phone' => '+90 532 100 0001',
                'pos' => 'POS-HR-DIR',
                'dept' => 'HR',
                'unit' => 'U-HR',
                'team' => 'T-RECRUIT',
                'user_id' => $adminUser?->id,
                'manager_idx' => null,
                'hire_years_ago' => 4,
            ],
            [
                'number' => 'EMP10002',
                'first_name' => 'Mehmet',
                'last_name' => 'Can',
                'email' => 'mehmet.can@zolm.test',
                'phone' => '+90 532 100 0002',
                'pos' => 'POS-TECH-ARCH',
                'dept' => 'TECH',
                'unit' => 'U-DEV',
                'team' => 'T-BACKEND',
                'manager_idx' => 0,
                'hire_years_ago' => 3,
            ],
            [
                'number' => 'EMP10003',
                'first_name' => 'Zeynep',
                'last_name' => 'Kaya',
                'email' => 'zeynep.kaya@zolm.test',
                'phone' => '+90 532 100 0003',
                'pos' => 'POS-SR-BACKEND',
                'dept' => 'TECH',
                'unit' => 'U-DEV',
                'team' => 'T-BACKEND',
                'manager_idx' => 1,
                'hire_years_ago' => 2,
            ],
            [
                'number' => 'EMP10004',
                'first_name' => 'Burak',
                'last_name' => 'Demir',
                'email' => 'burak.demir@zolm.test',
                'phone' => '+90 532 100 0004',
                'pos' => 'POS-FRONTEND',
                'dept' => 'TECH',
                'unit' => 'U-DEV',
                'team' => 'T-FRONTEND',
                'manager_idx' => 1,
                'hire_years_ago' => 2,
            ],
            [
                'number' => 'EMP10005',
                'first_name' => 'Ayşe',
                'last_name' => 'Çelik',
                'email' => 'ayse.celik@zolm.test',
                'phone' => '+90 532 100 0005',
                'pos' => 'POS-HR-SPEC',
                'dept' => 'HR',
                'unit' => 'U-HR',
                'team' => 'T-RECRUIT',
                'manager_idx' => 0,
                'hire_years_ago' => 2,
            ],
            [
                'number' => 'EMP10006',
                'first_name' => 'Caner',
                'last_name' => 'Arslan',
                'email' => 'caner.arslan@zolm.test',
                'phone' => '+90 532 100 0006',
                'pos' => 'POS-SALES-MGR',
                'dept' => 'SALES',
                'unit' => 'U-SALES',
                'team' => 'T-FIELD',
                'manager_idx' => 0,
                'hire_years_ago' => 3,
            ],
            [
                'number' => 'EMP10007',
                'first_name' => 'Elif',
                'last_name' => 'Öztürk',
                'email' => 'elif.ozturk@zolm.test',
                'phone' => '+90 532 100 0007',
                'pos' => 'POS-SALES-SPEC',
                'dept' => 'SALES',
                'unit' => 'U-SALES',
                'team' => 'T-FIELD',
                'manager_idx' => 5,
                'hire_years_ago' => 1,
            ],
            [
                'number' => 'EMP10008',
                'first_name' => 'Mustafa',
                'last_name' => 'Yıldız',
                'email' => 'mustafa.yildiz@zolm.test',
                'phone' => '+90 532 100 0008',
                'pos' => 'POS-MKTG-SPEC',
                'dept' => 'SALES',
                'unit' => 'U-SALES',
                'team' => 'T-FIELD',
                'manager_idx' => 5,
                'hire_years_ago' => 1,
            ],
            [
                'number' => 'EMP10009',
                'first_name' => 'Deniz',
                'last_name' => 'Kılıç',
                'email' => 'deniz.kilic@zolm.test',
                'phone' => '+90 532 100 0009',
                'pos' => 'POS-OPS-MGR',
                'dept' => 'OPS',
                'unit' => 'U-LOG',
                'team' => 'T-WAREHOUSE',
                'manager_idx' => 0,
                'hire_years_ago' => 3,
            ],
            [
                'number' => 'EMP10010',
                'first_name' => 'Hakan',
                'last_name' => 'Tekin',
                'email' => 'hakan.tekin@zolm.test',
                'phone' => '+90 532 100 0010',
                'pos' => 'POS-LOG-SPEC',
                'dept' => 'OPS',
                'unit' => 'U-LOG',
                'team' => 'T-WAREHOUSE',
                'manager_idx' => 8,
                'hire_years_ago' => 2,
            ],
            [
                'number' => 'EMP10011',
                'first_name' => 'Selin',
                'last_name' => 'Şahin',
                'email' => 'selin.sahin@zolm.test',
                'phone' => '+90 532 100 0011',
                'pos' => 'POS-SR-ACC',
                'dept' => 'FIN',
                'unit' => 'U-ACC',
                'team' => 'T-FINANCE',
                'manager_idx' => 0,
                'hire_years_ago' => 2,
            ],
            [
                'number' => 'EMP10012',
                'first_name' => 'Gamze',
                'last_name' => 'Aydın',
                'email' => 'gamze.aydin@zolm.test',
                'phone' => '+90 532 100 0012',
                'pos' => 'POS-CUST-SUPP',
                'dept' => 'FIN',
                'unit' => 'U-ACC',
                'team' => 'T-FINANCE',
                'manager_idx' => 10,
                'hire_years_ago' => 1,
            ],
            [
                'number' => 'EMP10013',
                'first_name' => 'Murat',
                'last_name' => 'Erdoğan',
                'email' => 'murat.erdogan@zolm.test',
                'phone' => '+90 532 100 0013',
                'pos' => 'POS-SAFETY-SPEC',
                'dept' => 'HR',
                'unit' => 'U-HR',
                'team' => 'T-RECRUIT',
                'manager_idx' => 0,
                'hire_years_ago' => 2,
            ],
            [
                'number' => 'EMP10014',
                'first_name' => 'Duygu',
                'last_name' => 'Polat',
                'email' => 'duygu.polat@zolm.test',
                'phone' => '+90 532 100 0014',
                'pos' => 'POS-JR-DEV',
                'dept' => 'TECH',
                'unit' => 'U-DEV',
                'team' => 'T-BACKEND',
                'manager_idx' => 1,
                'hire_years_ago' => 0,
            ],
        ];

        $employees = [];
        foreach ($employeesData as $idx => $data) {
            $nationalId = '100000000' . sprintf('%02d', $idx + 1);
            $user = $idx === 0 ? $adminUser : User::firstOrNew(['email' => $data['email']]);
            if ($idx !== 0) {
                $user->name = $data['first_name'].' '.$data['last_name'];
                $user->role = 'operator';
                $user->is_active = true;
                if (! $user->exists) {
                    $user->password = Hash::make($mockUserPassword);
                }
                $user->save();

                $roleSlug = in_array($idx, [1, 5, 8, 10], true) ? 'hr_manager' : 'hr_employee';
                $roleId = DB::table('roles')->where('slug', $roleSlug)->value('id');
                if ($roleId) {
                    $user->syncHrRoles([$roleId]);
                }
            }

            $legacyNumber = 'EMP'.sprintf('%03d', $idx + 1);
            $emp = HrEmployee::withoutGlobalScope('tenant')
                ->where('legal_entity_id', $tenantId)
                ->whereIn('employee_number', [$data['number'], $legacyNumber])
                ->first() ?? new HrEmployee(['legal_entity_id' => $tenantId]);
            $emp->fill([
                'employee_number' => $data['number'],
                'user_id' => $user->id,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'personal_email' => $data['email'],
                'phone' => $data['phone'],
                'national_id_encrypted' => $nationalId,
                'national_id_hash' => hash('sha256', $nationalId.config('app.key')),
                'national_id_last_four' => substr($nationalId, -4),
                'gender' => ($idx % 2 === 0) ? 'male' : 'female',
                'date_of_birth' => Carbon::now()->subYears(25 + ($idx % 15))->toDateString(),
                'marital_status' => ($idx % 2 === 0) ? 'married' : 'single',
                'blood_type' => 'A+',
                'city' => 'İstanbul',
                'district' => 'Kadıköy',
                'status' => EmployeeStatus::Active,
            ]);
            $emp->save();

            $managerId = ($data['manager_idx'] !== null && isset($employees[$data['manager_idx']]))
                ? $employees[$data['manager_idx']]->id
                : null;

            $startDate = Carbon::now()->subYears($data['hire_years_ago'])->addMonths($idx % 6)->toDateString();

            HrEmploymentRecord::withoutGlobalScope('tenant')->updateOrCreate(
                ['legal_entity_id' => $tenantId, 'employee_id' => $emp->id, 'status' => 'active'],
                [
                    'sgk_workplace_id' => $org['sgkWorkplace']->id,
                    'branch_id' => $org['branch']->id,
                    'department_id' => $org['departments'][$data['dept']]->id,
                    'unit_id' => $org['units'][$data['unit']]->id,
                    'team_id' => $org['teams'][$data['team']]->id,
                    'position_id' => $org['positions'][$data['pos']]->id,
                    'manager_employee_id' => $managerId,
                    'cost_center_id' => $org['costCenters'][$data['dept']]->id,
                    'employment_type' => EmploymentType::FullTime,
                    'work_model' => ($idx % 3 === 0) ? 'hybrid' : 'onsite',
                    'contract_type' => 'indefinite',
                    'start_date' => $startDate,
                    'weekly_work_hours' => 45.0,
                    'status' => 'active',
                ]
            );

            $employees[$idx] = $emp;
        }

        $mockEmployeeIds = array_map(fn (HrEmployee $employee) => $employee->id, $employees);
        $existingEmployees = HrEmployee::withoutGlobalScope('tenant')
            ->where('legal_entity_id', $tenantId)
            ->where('status', EmployeeStatus::Active->value)
            ->whereNotIn('id', $mockEmployeeIds)
            ->get();

        foreach ($existingEmployees as $employee) {
            if (! $employee->user_id) {
                $email = $employee->personal_email ?: "employee.{$employee->id}@zolm.test";
                $user = User::firstOrNew(['email' => $email]);
                $user->name = $employee->full_name;
                $user->role = 'operator';
                $user->is_active = true;
                if (! $user->exists) {
                    $user->password = Hash::make($mockUserPassword);
                }
                $user->save();
                $roleId = DB::table('roles')->where('slug', 'hr_employee')->value('id');
                if ($roleId) {
                    $user->syncHrRoles([$roleId]);
                }
                $employee->update(['user_id' => $user->id]);
            }

            $employment = HrEmploymentRecord::withoutGlobalScope('tenant')
                ->firstOrNew(['legal_entity_id' => $tenantId, 'employee_id' => $employee->id, 'status' => 'active']);
            $departmentCode = optional($employment->department)->code;
            $employment->fill([
                'sgk_workplace_id' => $employment->sgk_workplace_id ?: $org['sgkWorkplace']->id,
                'branch_id' => $employment->branch_id ?: $org['branch']->id,
                'department_id' => $employment->department_id ?: $org['departments']['TECH']->id,
                'cost_center_id' => $employment->cost_center_id ?: ($org['costCenters'][$departmentCode] ?? $org['rootCostCenter'])->id,
                'employment_type' => $employment->employment_type ?: EmploymentType::FullTime,
                'work_model' => $employment->work_model ?: 'hybrid',
                'contract_type' => $employment->contract_type ?: 'indefinite',
                'start_date' => $employment->start_date ?: now()->subYear()->toDateString(),
                'weekly_work_hours' => $employment->weekly_work_hours ?: 45.0,
            ]);
            $employment->save();
            $employees[] = $employee->fresh();
        }

        $org['departments']['HR']->update(['manager_employee_id' => $employees[0]->id]);
        $org['departments']['TECH']->update(['manager_employee_id' => $employees[1]->id]);
        $org['departments']['SALES']->update(['manager_employee_id' => $employees[5]->id]);
        $org['departments']['OPS']->update(['manager_employee_id' => $employees[8]->id]);
        $org['departments']['FIN']->update(['manager_employee_id' => $employees[10]->id]);

        return $employees;
    }

    private function seedCompensation(int $tenantId, array $employees): void
    {
        $bandsData = [
            ['code' => 'BAND-A', 'name' => 'Yönetici & Mimar Bandı', 'min' => '110000', 'mid' => '135000', 'max' => '160000'],
            ['code' => 'BAND-B', 'name' => 'Kıdemli Uzman Bandı', 'min' => '65000', 'mid' => '80000', 'max' => '95000'],
            ['code' => 'BAND-C', 'name' => 'Uzman Bandı', 'min' => '40000', 'mid' => '50000', 'max' => '60000'],
            ['code' => 'BAND-D', 'name' => 'Operasyon & Junior Bandı', 'min' => '28000', 'mid' => '33000', 'max' => '38000'],
        ];

        $bands = [];
        foreach ($bandsData as $b) {
            $bands[$b['code']] = HrSalaryBand::withoutGlobalScope('tenant')->updateOrCreate(
                ['legal_entity_id' => $tenantId, 'code' => $b['code']],
                [
                    'name' => $b['name'],
                    'minimum_salary_encrypted' => $b['min'],
                    'midpoint_salary_encrypted' => $b['mid'],
                    'maximum_salary_encrypted' => $b['max'],
                    'currency' => 'TRY',
                    'effective_from' => Carbon::now()->startOfYear()->toDateString(),
                    'is_active' => true,
                ]
            );
        }

        $salaries = [
            0 => ['gross' => '145000', 'band' => 'BAND-A'],
            1 => ['gross' => '135000', 'band' => 'BAND-A'],
            2 => ['gross' => '85000', 'band' => 'BAND-B'],
            3 => ['gross' => '72000', 'band' => 'BAND-B'],
            4 => ['gross' => '52000', 'band' => 'BAND-C'],
            5 => ['gross' => '115000', 'band' => 'BAND-A'],
            6 => ['gross' => '58000', 'band' => 'BAND-C'],
            7 => ['gross' => '48000', 'band' => 'BAND-C'],
            8 => ['gross' => '90000', 'band' => 'BAND-B'],
            9 => ['gross' => '35000', 'band' => 'BAND-D'],
            10 => ['gross' => '78000', 'band' => 'BAND-B'],
            11 => ['gross' => '42000', 'band' => 'BAND-C'],
            12 => ['gross' => '55000', 'band' => 'BAND-C'],
            13 => ['gross' => '32000', 'band' => 'BAND-D'],
        ];

        foreach ($employees as $idx => $employee) {
            $salary = $salaries[$idx] ?? ['gross' => '45000', 'band' => 'BAND-C'];
            HrSalaryRecord::withoutGlobalScope('tenant')->updateOrCreate(
                ['legal_entity_id' => $tenantId, 'employee_id' => $employee->id, 'version' => 1],
                [
                    'salary_band_id' => $bands[$salary['band']]->id,
                    'gross_salary_encrypted' => $salary['gross'],
                    'currency' => 'TRY',
                    'effective_from' => Carbon::now()->startOfYear()->toDateString(),
                    'status' => 'approved',
                    'change_reason' => 'Yıllık Dönemsel Ücret Düzenlemesi',
                ]
            );
        }

        $benefitsData = [
            ['code' => 'BEN-MEAL', 'name' => 'Yemek Kartı (Ticket Restoran)', 'type' => 'meal', 'cost' => '6500'],
            ['code' => 'BEN-HEALTH', 'name' => 'Özel Sağlık Sigortası (Tamamlayıcı)', 'type' => 'health_insurance', 'cost' => '3800'],
            ['code' => 'BEN-TRANS', 'name' => 'Ulaşım & Yol Desteği', 'type' => 'transport', 'cost' => '2500'],
            ['code' => 'BEN-NET', 'name' => 'Evden Çalışma İnternet Paketi', 'type' => 'allowance', 'cost' => '1200'],
        ];

        $benefits = [];
        foreach ($benefitsData as $ben) {
            $benefits[$ben['code']] = HrBenefit::withoutGlobalScope('tenant')->updateOrCreate(
                ['legal_entity_id' => $tenantId, 'code' => $ben['code']],
                [
                    'name' => $ben['name'],
                    'type' => $ben['type'],
                    'employer_cost_encrypted' => $ben['cost'],
                    'currency' => 'TRY',
                    'is_active' => true,
                ]
            );
        }

        foreach ($employees as $idx => $emp) {
            HrEmployeeBenefit::withoutGlobalScope('tenant')->updateOrCreate(
                ['legal_entity_id' => $tenantId, 'employee_id' => $emp->id, 'benefit_id' => $benefits['BEN-MEAL']->id],
                ['starts_on' => Carbon::now()->startOfYear()->toDateString(), 'status' => 'active']
            );

            if ($idx < 8) {
                HrEmployeeBenefit::withoutGlobalScope('tenant')->updateOrCreate(
                    ['legal_entity_id' => $tenantId, 'employee_id' => $emp->id, 'benefit_id' => $benefits['BEN-HEALTH']->id],
                    ['starts_on' => Carbon::now()->startOfYear()->toDateString(), 'status' => 'active']
                );
            }
        }
    }

    private function seedDocuments(int $tenantId, array $employees, User $admin): void
    {
        $definitions = [
            'IDENTITY' => [
                'name' => 'Kimlik Belgesi',
                'category' => DocumentCategory::Identity,
                'sensitivity' => DocumentSensitivity::HighlySensitive,
                'requires_expiry_date' => true,
                'requires_issue_date' => true,
                'requires_document_number' => true,
                'default_validity_months' => 120,
            ],
            'CONTRACT' => [
                'name' => 'İş Sözleşmesi',
                'category' => DocumentCategory::Contract,
                'sensitivity' => DocumentSensitivity::Confidential,
                'requires_issue_date' => true,
            ],
            'KVKK' => [
                'name' => 'KVKK Aydınlatma ve Açık Rıza',
                'category' => DocumentCategory::Kvkk,
                'sensitivity' => DocumentSensitivity::Confidential,
                'requires_issue_date' => true,
            ],
            'ISG-TRAINING' => [
                'name' => 'İSG Eğitim Belgesi',
                'category' => DocumentCategory::OccupationalSafety,
                'sensitivity' => DocumentSensitivity::Standard,
                'requires_expiry_date' => true,
                'requires_issue_date' => true,
                'default_validity_months' => 12,
            ],
            'HEALTH-REPORT' => [
                'name' => 'İşe Giriş / Periyodik Sağlık Raporu',
                'category' => DocumentCategory::Health,
                'sensitivity' => DocumentSensitivity::HighlySensitive,
                'requires_expiry_date' => true,
                'requires_issue_date' => true,
                'default_validity_months' => 12,
            ],
        ];

        $types = [];
        foreach ($definitions as $code => $definition) {
            $types[$code] = HrDocumentType::withoutGlobalScope('tenant')->updateOrCreate(
                ['legal_entity_id' => $tenantId, 'code' => $code],
                array_merge([
                    'description' => 'Mock ortamda özlük ve mevzuat uyum senaryolarını doğrulamak için kullanılır.',
                    'requires_expiry_date' => false,
                    'requires_issue_date' => false,
                    'requires_document_number' => false,
                    'allowed_mime_types' => ['application/pdf'],
                    'max_file_size_kb' => 2048,
                    'default_validity_months' => null,
                    'is_mandatory' => true,
                    'employee_can_upload' => true,
                    'is_active' => true,
                ], $definition)
            );

            HrDocumentRequirement::withoutGlobalScope('tenant')->updateOrCreate(
                [
                    'legal_entity_id' => $tenantId,
                    'document_type_id' => $types[$code]->id,
                    'branch_id' => null,
                    'department_id' => null,
                    'position_id' => null,
                    'employment_type' => null,
                ],
                [
                    'is_required' => true,
                    'required_on_hire' => true,
                    'due_days_after_hire' => 7,
                    'reminder_days_before_expiry' => 30,
                    'effective_from' => now()->startOfYear()->toDateString(),
                    'effective_to' => null,
                    'created_by' => $admin->id,
                    'updated_by' => $admin->id,
                ]
            );
        }

        foreach ($employees as $index => $employee) {
            foreach (['IDENTITY', 'CONTRACT', 'KVKK'] as $code) {
                $file = $this->createMockPdfFile($tenantId, $admin, "document-{$employee->id}-{$code}", "{$code} örnek belgesi — {$employee->full_name}");
                $number = "{$code}-{$employee->employee_number}";
                $document = HrEmployeeDocument::withoutGlobalScope('tenant')->withTrashed()->updateOrCreate(
                    [
                        'legal_entity_id' => $tenantId,
                        'employee_id' => $employee->id,
                        'document_type_id' => $types[$code]->id,
                    ],
                    [
                        'current_file_id' => $file->id,
                        'document_number_encrypted' => $number,
                        'document_number_hash' => hash('sha256', $number.config('app.key')),
                        'document_number_last_four' => substr($number, -4),
                        'issue_date' => $employee->activeEmployment?->start_date ?? now()->subYear(),
                        'expiry_date' => $code === 'IDENTITY' ? now()->addYears(5)->toDateString() : null,
                        'status' => DocumentStatus::Active,
                        'verification_status' => VerificationStatus::Verified,
                        'verified_by' => $admin->id,
                        'verified_at' => now(),
                        'notes' => 'Mock veri — resmî belge değildir.',
                        'version_number' => 1,
                        'created_by' => $admin->id,
                        'updated_by' => $admin->id,
                        'deleted_at' => null,
                    ]
                );
                $file->update(['subject_type' => HrEmployeeDocument::class, 'subject_id' => $document->id]);
                HrEmployeeDocumentVersion::updateOrCreate(
                    ['employee_document_id' => $document->id, 'version_number' => 1],
                    ['file_id' => $file->id, 'uploaded_by' => $admin->id, 'change_reason' => 'İlk mock belge sürümü']
                );
            }

            if ($index < 3) {
                $code = 'ISG-TRAINING';
                $file = $this->createMockPdfFile($tenantId, $admin, "document-{$employee->id}-{$code}", "İSG eğitim belgesi — {$employee->full_name}");
                $document = HrEmployeeDocument::withoutGlobalScope('tenant')->withTrashed()->updateOrCreate(
                    ['legal_entity_id' => $tenantId, 'employee_id' => $employee->id, 'document_type_id' => $types[$code]->id],
                    [
                        'current_file_id' => $file->id,
                        'issue_date' => now()->subMonths(2)->toDateString(),
                        'expiry_date' => now()->addMonths(10)->toDateString(),
                        'status' => DocumentStatus::Active,
                        'verification_status' => VerificationStatus::Verified,
                        'verified_by' => $admin->id,
                        'verified_at' => now(),
                        'notes' => 'Mock veri — resmî belge değildir.',
                        'version_number' => 1,
                        'created_by' => $admin->id,
                        'updated_by' => $admin->id,
                        'deleted_at' => null,
                    ]
                );
                $file->update(['subject_type' => HrEmployeeDocument::class, 'subject_id' => $document->id]);
                HrEmployeeDocumentVersion::updateOrCreate(
                    ['employee_document_id' => $document->id, 'version_number' => 1],
                    ['file_id' => $file->id, 'uploaded_by' => $admin->id, 'change_reason' => 'İlk mock belge sürümü']
                );
            } else {
                HrDocumentRequest::withoutGlobalScope('tenant')->updateOrCreate(
                    [
                        'legal_entity_id' => $tenantId,
                        'employee_id' => $employee->id,
                        'document_type_id' => $types['ISG-TRAINING']->id,
                        'status' => 'pending',
                    ],
                    [
                        'requested_by' => $admin->id,
                        'due_date' => now()->addDays(7)->toDateString(),
                        'message' => 'İSG eğitim belgenizi çalışan portalından yükleyin.',
                    ]
                );
            }

            if (in_array($index, [8, 9, 12], true)) {
                $code = 'HEALTH-REPORT';
                $file = $this->createMockPdfFile($tenantId, $admin, "document-{$employee->id}-{$code}", "Periyodik sağlık raporu — {$employee->full_name}");
                $document = HrEmployeeDocument::withoutGlobalScope('tenant')->withTrashed()->updateOrCreate(
                    ['legal_entity_id' => $tenantId, 'employee_id' => $employee->id, 'document_type_id' => $types[$code]->id],
                    [
                        'current_file_id' => $file->id,
                        'issue_date' => now()->subMonths(2)->toDateString(),
                        'expiry_date' => now()->addMonths(10)->toDateString(),
                        'status' => DocumentStatus::Active,
                        'verification_status' => VerificationStatus::Verified,
                        'verified_by' => $admin->id,
                        'verified_at' => now(),
                        'notes' => 'Mock veri — resmî sağlık kaydı değildir.',
                        'version_number' => 1,
                        'created_by' => $admin->id,
                        'updated_by' => $admin->id,
                        'deleted_at' => null,
                    ]
                );
                $file->update(['subject_type' => HrEmployeeDocument::class, 'subject_id' => $document->id]);
                HrEmployeeDocumentVersion::updateOrCreate(
                    ['employee_document_id' => $document->id, 'version_number' => 1],
                    ['file_id' => $file->id, 'uploaded_by' => $admin->id, 'change_reason' => 'İlk mock belge sürümü']
                );
            } else {
                HrDocumentRequest::withoutGlobalScope('tenant')->updateOrCreate(
                    [
                        'legal_entity_id' => $tenantId,
                        'employee_id' => $employee->id,
                        'document_type_id' => $types['HEALTH-REPORT']->id,
                        'status' => 'pending',
                    ],
                    [
                        'requested_by' => $admin->id,
                        'due_date' => now()->addDays(14)->toDateString(),
                        'message' => 'Geçerli işe giriş veya periyodik sağlık raporunuzu yükleyin.',
                    ]
                );
            }
        }
    }

    private function seedLeaves(int $tenantId, array $employees): void
    {
        $typesData = [
            ['code' => 'ANNUAL', 'name' => 'Yıllık İzin', 'paid' => true],
            ['code' => 'SICK', 'name' => 'Hastalık İzni', 'paid' => true],
            ['code' => 'EXCUSED', 'name' => 'Mazeret İzni', 'paid' => true],
            ['code' => 'MATERNITY', 'name' => 'Babalık / Annelik İzni', 'paid' => true],
        ];

        $types = [];
        foreach ($typesData as $t) {
            $types[$t['code']] = HrLeaveType::withoutGlobalScope('tenant')->updateOrCreate(
                ['legal_entity_id' => $tenantId, 'code' => $t['code']],
                [
                    'name' => $t['name'],
                    'unit' => LeaveUnit::Day,
                    'is_paid' => $t['paid'],
                    'requires_document' => $t['code'] === 'SICK',
                    'is_active' => true,
                ]
            );
        }

        $entitlements = ['ANNUAL' => 14.00, 'SICK' => 10.00, 'EXCUSED' => 5.00, 'MATERNITY' => 5.00];
        $policies = [];
        foreach ($types as $code => $type) {
            $policies[$code] = HrLeavePolicy::withoutGlobalScope('tenant')->updateOrCreate(
                ['legal_entity_id' => $tenantId, 'leave_type_id' => $type->id, 'scope' => LeavePolicyScope::Company],
                [
                    'annual_entitlement' => $entitlements[$code],
                    'max_carryover' => $code === 'ANNUAL' ? 5.00 : 0.00,
                    'allows_negative_balance' => false,
                    'requires_hr_approval' => true,
                    'effective_from' => '2026-01-01',
                    'is_active' => true,
                ]
            );
        }

        foreach ($employees as $emp) {
            HrLeaveBalance::withoutGlobalScope('tenant')->updateOrCreate(
                ['legal_entity_id' => $tenantId, 'employee_id' => $emp->id, 'leave_type_id' => $types['ANNUAL']->id, 'period_year' => 2026],
                [
                    'entitled_amount' => 14.00,
                    'carried_amount' => 2.00,
                    'used_amount' => 3.00,
                    'adjustment_amount' => 0.00,
                    'remaining_amount' => 13.00,
                ]
            );
        }

        // PENDING LEAVE REQUESTS (So "İZİN ONAYI: 2" shows on Dashboard!)
        $managerRequest = $this->upsertLeaveRequest(
            $tenantId,
            $employees[2]->id,
            Carbon::now()->addDays(5),
            [
                'leave_type_id' => $types['ANNUAL']->id,
                'policy_id' => $policies['ANNUAL']->id,
                'status' => LeaveRequestStatus::PendingManager,
                'end_date' => Carbon::now()->addDays(7)->toDateString(),
                'requested_amount' => 3,
                'unit' => LeaveUnit::Day,
                'reason' => 'Yaz tatili ve dinlenme talebi',
            ]
        );

        $hrRequest = $this->upsertLeaveRequest(
            $tenantId,
            $employees[6]->id,
            Carbon::now()->addDays(3),
            [
                'leave_type_id' => $types['EXCUSED']->id,
                'policy_id' => $policies['EXCUSED']->id,
                'status' => LeaveRequestStatus::PendingHr,
                'end_date' => Carbon::now()->addDays(3)->toDateString(),
                'requested_amount' => 1,
                'unit' => LeaveUnit::Day,
                'reason' => 'Resmi kurum işlemleri nedeniyle mazeret izni',
            ]
        );

        $manager = $employees[2]->fresh('activeEmployment.manager.user')->activeEmployment?->manager;
        HrLeaveApprovalStep::withoutGlobalScope('tenant')->firstOrCreate(
            ['legal_entity_id' => $tenantId, 'leave_request_id' => $managerRequest->id, 'step_order' => 1],
            [
                'approver_type' => 'manager',
                'approver_employee_id' => $manager?->id,
                'approver_user_id' => $manager?->user_id,
                'status' => LeaveApprovalStatus::Pending,
            ]
        );
        HrLeaveApprovalStep::withoutGlobalScope('tenant')->firstOrCreate(
            ['legal_entity_id' => $tenantId, 'leave_request_id' => $managerRequest->id, 'step_order' => 2],
            ['approver_type' => 'hr', 'status' => LeaveApprovalStatus::Pending]
        );
        HrLeaveApprovalStep::withoutGlobalScope('tenant')->firstOrCreate(
            ['legal_entity_id' => $tenantId, 'leave_request_id' => $hrRequest->id, 'step_order' => 1],
            ['approver_type' => 'hr', 'status' => LeaveApprovalStatus::Pending]
        );

        // APPROVED PAST LEAVES
        for ($i = 0; $i < 4; $i++) {
            $this->upsertLeaveRequest(
                $tenantId,
                $employees[$i]->id,
                Carbon::now()->subDays(20 + $i * 5),
                [
                    'leave_type_id' => $types['ANNUAL']->id,
                    'policy_id' => $policies['ANNUAL']->id,
                    'status' => LeaveRequestStatus::Approved,
                    'end_date' => Carbon::now()->subDays(18 + $i * 5)->toDateString(),
                    'requested_amount' => 3,
                    'unit' => LeaveUnit::Day,
                    'reason' => 'Önceki dönem yıllık izin',
                ]
            );
        }
    }

    private function seedAttendance(int $tenantId, array $employees): void
    {
        $dev1 = HrAttendanceDevice::withoutGlobalScope('tenant')->updateOrCreate(
            ['legal_entity_id' => $tenantId, 'code' => 'DEV-MAIN-01'],
            ['name' => 'Ana HQ Giriş Turnikesi', 'type' => 'turnstile', 'location' => 'A Blok Zemin', 'secret_hash' => hash('sha256', 'mock-main-device'), 'is_active' => true, 'last_seen_at' => now()]
        );

        $dev2 = HrAttendanceDevice::withoutGlobalScope('tenant')->updateOrCreate(
            ['legal_entity_id' => $tenantId, 'code' => 'DEV-WAREHOUSE-02'],
            ['name' => 'Lojistik Depo Turnikesi', 'type' => 'turnstile', 'location' => 'B Depo Giriş', 'secret_hash' => hash('sha256', 'mock-warehouse-device'), 'is_active' => true, 'last_seen_at' => now()]
        );

        foreach (CarbonPeriod::create($this->referencePeriodStart(), $this->referencePeriodEnd()) as $date) {
            if ($date->isWeekend()) {
                continue;
            }

            $this->seedAttendanceEventsForDate($tenantId, $employees, $dev1, $dev2, $date);
        }

        $previewDate = now()->subDay()->startOfDay();
        while ($previewDate->isWeekend()) {
            $previewDate->subDay();
        }
        $this->seedAttendanceEventsForDate($tenantId, $employees, $dev1, $dev2, $previewDate);

        // PDKS ANOMALIES (So "PDKS RİSKİ: 3" shows on Dashboard!)
        $this->upsertAttendanceAnomaly(
            $tenantId,
            $employees[9]->id,
            Carbon::now()->subDay(),
            'late_arrival',
            'warning',
            ['delay_minutes' => 48, 'expected' => '08:30', 'actual' => '09:18']
        );

        $this->upsertAttendanceAnomaly(
            $tenantId,
            $employees[3]->id,
            Carbon::now()->subDays(2),
            'missing_checkout',
            'critical',
            ['missing' => 'checkout_stamp', 'checkin' => '08:29']
        );

        $this->upsertAttendanceAnomaly(
            $tenantId,
            $employees[11]->id,
            Carbon::now()->subDays(3),
            'early_departure',
            'warning',
            ['early_minutes' => 75, 'expected' => '17:30', 'actual' => '16:15']
        );
    }

    private function seedShifts(int $tenantId, array $employees): void
    {
        $shDay = HrShiftTemplate::withoutGlobalScope('tenant')->updateOrCreate(
            ['legal_entity_id' => $tenantId, 'code' => 'SH-DAY'],
            ['name' => 'Gündüz Standart Vardiya', 'starts_at' => '08:30:00', 'ends_at' => '17:30:00', 'break_minutes' => 60, 'is_active' => true]
        );

        $shLog = HrShiftTemplate::withoutGlobalScope('tenant')->updateOrCreate(
            ['legal_entity_id' => $tenantId, 'code' => 'SH-EARLY-LOG'],
            ['name' => 'Depo Erken Vardiya', 'starts_at' => '07:30:00', 'ends_at' => '16:30:00', 'break_minutes' => 60, 'is_active' => true]
        );

        foreach (CarbonPeriod::create($this->referencePeriodStart(), $this->referencePeriodEnd()) as $date) {
            if ($date->isWeekend()) {
                continue;
            }
            foreach ($employees as $idx => $employee) {
                $shift = in_array($idx, [8, 9], true) ? $shLog : $shDay;
                $this->upsertShiftAssignment($tenantId, $employee->id, $date, $shift->id);
            }
        }

        if (! now()->isWeekend()) {
            foreach ($employees as $idx => $employee) {
                $shift = in_array($idx, [8, 9], true) ? $shLog : $shDay;
                $this->upsertShiftAssignment($tenantId, $employee->id, now(), $shift->id);
            }
        }
    }

    private function seedTimesheetAndOvertime(int $tenantId, array $employees, User $admin): void
    {
        $periodStart = $this->referencePeriodStart();
        $periodEnd = $this->referencePeriodEnd();
        $period = HrTimesheetPeriod::withoutGlobalScope('tenant')
            ->where('legal_entity_id', $tenantId)
            ->whereDate('starts_on', $periodStart->toDateString())
            ->whereDate('ends_on', $periodEnd->toDateString())
            ->first() ?? new HrTimesheetPeriod([
                'legal_entity_id' => $tenantId,
                'starts_on' => $periodStart->toDateString(),
                'ends_on' => $periodEnd->toDateString(),
            ]);
        $period->name = $periodStart->translatedFormat('F Y').' Puantaj Dönemi';
        $period->save();

        $otType = HrOvertimeType::withoutGlobalScope('tenant')->updateOrCreate(
            ['legal_entity_id' => $tenantId, 'code' => 'OT-WEEKDAY'],
            ['name' => 'Hafta İçi Mesai (%50)', 'multiplier' => 1.50, 'is_active' => true]
        );

        $overtimeDate = $periodEnd->copy()->subDays(5);
        $overtime = HrOvertimeRequest::withoutGlobalScope('tenant')
            ->where('legal_entity_id', $tenantId)
            ->where('employee_id', $employees[2]->id)
            ->whereDate('work_date', $overtimeDate->toDateString())
            ->first() ?? new HrOvertimeRequest([
                'legal_entity_id' => $tenantId,
                'employee_id' => $employees[2]->id,
                'work_date' => $overtimeDate->toDateString(),
            ]);
        $overtime->fill([
            'overtime_type_id' => $otType->id,
            'starts_at' => '18:00:00',
            'ends_at' => '22:00:00',
            'requested_minutes' => 240,
            'approved_minutes' => 240,
            'reason' => 'Sprint canlıya çıkış öncesi acil hotfix',
            'status' => OvertimeRequestStatus::Approved,
        ]);
        $overtime->save();

        if ($period->status !== TimesheetPeriodStatus::Closed) {
            if ($period->status !== TimesheetPeriodStatus::Draft) {
                $period->update(['status' => TimesheetPeriodStatus::Draft, 'closed_at' => null, 'closed_by' => null]);
            }

            app(CalculateTimesheetPeriodAction::class)->execute($period->fresh());
            HrTimesheet::withoutGlobalScope('tenant')
                ->where('legal_entity_id', $tenantId)
                ->where('timesheet_period_id', $period->id)
                ->where('status', TimesheetStatus::Draft->value)
                ->update([
                    'status' => TimesheetStatus::Confirmed->value,
                    'confirmed_by' => $admin->id,
                    'confirmed_at' => now(),
                ]);
            app(CloseTimesheetPeriodAction::class)->execute($period->fresh());
        }
    }

    private function seedPayroll(int $tenantId, array $employees, User $admin): void
    {
        $configuration = [
            'standard_monthly_minutes' => collect(CarbonPeriod::create($this->referencePeriodStart(), $this->referencePeriodEnd()))
                ->reject(fn (Carbon $date) => $date->isWeekend())
                ->count() * 480,
            'overtime_multiplier_basis_points' => 15000,
            'employee_social_security_basis_points' => 1400,
            'employee_unemployment_basis_points' => 100,
            'employer_social_security_basis_points' => 1550,
            'employer_unemployment_basis_points' => 200,
            'stamp_tax_basis_points' => 76,
            'income_tax_exemption_cents' => 0,
            'stamp_tax_exemption_cents' => 0,
            'social_security_ceiling_cents' => 19504125,
            'income_tax_brackets' => [
                ['upper_limit_cents' => 15800000, 'rate_basis_points' => 1500],
                ['upper_limit_cents' => 33000000, 'rate_basis_points' => 2000],
                ['upper_limit_cents' => 120000000, 'rate_basis_points' => 2700],
                ['upper_limit_cents' => 430000000, 'rate_basis_points' => 3500],
                ['upper_limit_cents' => null, 'rate_basis_points' => 4000],
            ],
        ];
        $configuration = app(PayrollRuleConfiguration::class)->validate($configuration);
        $financeApproverId = $employees[10]->user_id ?? $admin->id;

        HrPayrollRule::withoutGlobalScope('tenant')->updateOrCreate(
            ['legal_entity_id' => $tenantId, 'code' => PayrollRuleConfiguration::CODE, 'version' => 1],
            [
                'name' => '2026 QA Bordro Simülasyon Paketi — Resmî Beyan Değildir',
                'configuration' => $configuration,
                'configuration_hash' => app(PayrollRuleConfiguration::class)->hash($configuration),
                'effective_from' => $this->referencePeriodStart()->startOfYear()->toDateString(),
                'is_active' => true,
                'status' => 'approved',
                'created_by' => $admin->id,
                'approved_by' => $financeApproverId,
                'approved_at' => now(),
            ]
        );

        $timesheetPeriod = HrTimesheetPeriod::withoutGlobalScope('tenant')
            ->where('legal_entity_id', $tenantId)
            ->whereDate('starts_on', $this->referencePeriodStart())
            ->whereDate('ends_on', $this->referencePeriodEnd())
            ->where('status', TimesheetPeriodStatus::Closed->value)
            ->firstOrFail();

        foreach ($employees as $employee) {
            $salary = HrSalaryRecord::withoutGlobalScope('tenant')
                ->where('legal_entity_id', $tenantId)
                ->where('employee_id', $employee->id)
                ->whereIn('status', ['approved', 'superseded'])
                ->latest('effective_from')
                ->latest('version')
                ->firstOrFail();
            $openingTaxBaseCents = (int) round($salary->grossSalary() * 100 * 0.85 * max(0, $this->referencePeriodStart()->month - 1));
            HrPayrollTaxOpening::withoutGlobalScope('tenant')->updateOrCreate(
                ['legal_entity_id' => $tenantId, 'employee_id' => $employee->id, 'tax_year' => $this->referencePeriodStart()->year],
                [
                    'opening_tax_base_encrypted' => (string) $openingTaxBaseCents,
                    'source_reference' => 'MOCK-QA-CUMULATIVE-BASE-'.$this->referencePeriodStart()->format('Y-m'),
                    'created_by' => $admin->id,
                ]
            );
        }

        $payrollPeriod = app(PreparePayrollPeriodAction::class)->execute($timesheetPeriod);
        if ($payrollPeriod->status !== 'prepared') {
            return;
        }

        foreach ($employees as $employee) {
            $benefitCostCents = (int) round(HrEmployeeBenefit::withoutGlobalScope('tenant')
                ->where('legal_entity_id', $tenantId)
                ->where('employee_id', $employee->id)
                ->where('status', 'active')
                ->with('benefit')
                ->get()
                ->sum(fn (HrEmployeeBenefit $row) => (float) ($row->benefit?->employer_cost_encrypted ?? 0)) * 100);
            if ($benefitCostCents <= 0) {
                continue;
            }
            HrPayrollAdjustment::withoutGlobalScope('tenant')->updateOrCreate(
                ['payroll_period_id' => $payrollPeriod->id, 'employee_id' => $employee->id, 'code' => 'EMPLOYER-BENEFITS'],
                [
                    'legal_entity_id' => $tenantId,
                    'name' => 'İşveren Yan Hak Maliyeti',
                    'type' => 'employer_benefit',
                    'amount_encrypted' => (string) $benefitCostCents,
                    'status' => 'approved',
                    'reason' => 'Aktif yemek ve sağlık yan haklarının dönemsel işveren maliyeti.',
                    'created_by' => $admin->id,
                    'approved_by' => $financeApproverId,
                    'approved_at' => now(),
                ]
            );
        }

        app(CalculatePayrollPeriodAction::class)->execute($payrollPeriod->fresh('records'));
    }

    private function seedExpensesAndAdvances(int $tenantId, array $employees, User $admin): void
    {
        $catTravel = HrExpenseCategory::withoutGlobalScope('tenant')->updateOrCreate(
            ['legal_entity_id' => $tenantId, 'code' => 'EXP-TRAVEL'],
            ['name' => 'Seyahat & Konaklama', 'requires_receipt' => true, 'default_vat_rate' => 20, 'approval_limit' => 50000, 'is_active' => true]
        );

        $catFood = HrExpenseCategory::withoutGlobalScope('tenant')->updateOrCreate(
            ['legal_entity_id' => $tenantId, 'code' => 'EXP-FOOD'],
            ['name' => 'Müşteri Temsil & Yemek', 'requires_receipt' => true, 'default_vat_rate' => 10, 'approval_limit' => 10000, 'is_active' => true]
        );

        $catFuel = HrExpenseCategory::withoutGlobalScope('tenant')->updateOrCreate(
            ['legal_entity_id' => $tenantId, 'code' => 'EXP-FUEL'],
            ['name' => 'Ulaşım & Taksi', 'requires_receipt' => true, 'default_vat_rate' => 20, 'approval_limit' => 5000, 'is_active' => true]
        );

        $foodReceipt = $this->createMockPdfFile($tenantId, $admin, 'expense-receipt-food', 'BigChefs mock e-fatura örneği');
        $fuelReceipt = $this->createMockPdfFile($tenantId, $admin, 'expense-receipt-taxi', 'BiTaksi mock e-arşiv fişi örneği');
        $travelReceipt = $this->createMockPdfFile($tenantId, $admin, 'expense-receipt-travel', 'Mock otel e-fatura örneği');

        // PENDING EXPENSES
        $foodExpense = HrExpense::withoutGlobalScope('tenant')->updateOrCreate(
            ['legal_entity_id' => $tenantId, 'source_key' => '00000000-0000-4000-8000-000000000001'],
            [
                'employee_id' => $employees[5]->id,
                'expense_date' => Carbon::now()->subDays(2)->toDateString(),
                'expense_category_id' => $catFood->id,
                'receipt_file_id' => $foodReceipt->id,
                'currency' => 'TRY',
                'net_amount' => 2909.09,
                'vat_rate' => 10.0,
                'vat_amount' => 290.91,
                'gross_amount' => 3200.00,
                'status' => ExpenseStatus::PendingManager,
                'merchant_name' => 'BigChefs Kadıköy',
                'document_number' => 'BC-2026-8891',
                'description' => 'Müşteri Kurumsal Sözleşme Yemeği',
                'project_reference' => 'PRJ-CRM-2026',
                'customer_reference' => 'MUSTERI-1001',
                'payload_hash' => hash('sha256', 'EXP-001'),
                'requested_by' => $employees[5]->user_id,
            ]
        );
        $foodReceipt->update(['subject_type' => HrExpense::class, 'subject_id' => $foodExpense->id]);

        $fuelExpense = HrExpense::withoutGlobalScope('tenant')->updateOrCreate(
            ['legal_entity_id' => $tenantId, 'source_key' => '00000000-0000-4000-8000-000000000002'],
            [
                'employee_id' => $employees[6]->id,
                'expense_date' => Carbon::now()->subDays(3)->toDateString(),
                'expense_category_id' => $catFuel->id,
                'receipt_file_id' => $fuelReceipt->id,
                'currency' => 'TRY',
                'net_amount' => 708.33,
                'vat_rate' => 20.0,
                'vat_amount' => 141.67,
                'gross_amount' => 850.00,
                'status' => ExpenseStatus::PendingHr,
                'merchant_name' => 'BiTaksi',
                'document_number' => 'BT-441209',
                'description' => 'Saha Müşteri Ziyareti Taksi Fişi',
                'project_reference' => 'PRJ-SALES-2026',
                'customer_reference' => 'MUSTERI-1002',
                'payload_hash' => hash('sha256', 'EXP-002'),
                'requested_by' => $employees[6]->user_id,
            ]
        );
        $fuelReceipt->update(['subject_type' => HrExpense::class, 'subject_id' => $fuelExpense->id]);

        $financeApproverId = $employees[10]->user_id ?? $admin->id;
        $travelExpense = HrExpense::withoutGlobalScope('tenant')->updateOrCreate(
            ['legal_entity_id' => $tenantId, 'employee_id' => $employees[1]->id, 'source_key' => '00000000-0000-4000-8000-000000000003'],
            [
                'expense_category_id' => $catTravel->id,
                'receipt_file_id' => $travelReceipt->id,
                'expense_date' => now()->subDays(20)->toDateString(),
                'currency' => 'TRY',
                'net_amount' => 12000.00,
                'vat_rate' => 20.0,
                'vat_amount' => 2400.00,
                'gross_amount' => 14400.00,
                'status' => ExpenseStatus::Paid,
                'merchant_name' => 'ZOLM Mock Hotel',
                'document_number' => 'HOTEL-2026-001',
                'description' => 'Müşteri proje başlangıç toplantısı konaklama gideri',
                'project_reference' => 'PRJ-ERP-2026',
                'customer_reference' => 'MUSTERI-1003',
                'payload_hash' => hash('sha256', 'EXP-003'),
                'requested_by' => $employees[1]->user_id,
                'decided_by' => $financeApproverId,
                'decided_at' => now()->subDays(18),
                'decision_note' => 'Mock yönetici ve finans onayı tamamlandı.',
                'paid_by' => $financeApproverId,
                'paid_at' => now()->subDays(15),
                'payment_reference' => 'BANK-MOCK-2026-0001',
            ]
        );
        $travelReceipt->update(['subject_type' => HrExpense::class, 'subject_id' => $travelExpense->id]);
        $financeReference = 'hr-expense-approved-'.$travelExpense->id;
        app(HrIntegrationOutboxService::class)->enqueue('finance', 'expense_approved', $travelExpense, $financeReference, [
            'expense_id' => $travelExpense->id,
            'employee_id' => $travelExpense->employee_id,
            'gross_amount' => (string) $travelExpense->gross_amount,
            'vat_amount' => (string) $travelExpense->vat_amount,
            'currency' => $travelExpense->currency,
            'expense_date' => $travelExpense->expense_date->toDateString(),
            'project_reference' => $travelExpense->project_reference,
            'order_reference' => $travelExpense->order_reference,
            'customer_reference' => $travelExpense->customer_reference,
        ]);
        $travelExpense->update(['finance_reference' => $financeReference]);

        // APPROVED ADVANCE
        HrAdvance::withoutGlobalScope('tenant')->updateOrCreate(
            ['legal_entity_id' => $tenantId, 'source_key' => '00000000-0000-4000-8000-000000000101'],
            [
                'employee_id' => $employees[2]->id,
                'requested_payment_date' => Carbon::now()->addDays(2)->toDateString(),
                'requested_amount' => 15000.00,
                'currency' => 'TRY',
                'installment_count' => 3,
                'reason' => 'Teknik Eğitim & Sertifikasyon Ücreti Avansı',
                'status' => AdvanceStatus::PendingManager,
                'payload_hash' => hash('sha256', 'ADV-001'),
                'requested_by' => $employees[2]->user_id,
            ]
        );
    }

    private function seedAssets(int $tenantId, array $employees): void
    {
        $catLap = HrAssetCategory::withoutGlobalScope('tenant')->updateOrCreate(
            ['legal_entity_id' => $tenantId, 'code' => 'CAT-LAPTOP'],
            ['name' => 'Dizüstü Bilgisayar', 'is_active' => true]
        );

        $catMob = HrAssetCategory::withoutGlobalScope('tenant')->updateOrCreate(
            ['legal_entity_id' => $tenantId, 'code' => 'CAT-MOBILE'],
            ['name' => 'Akıllı Telefon', 'is_active' => true]
        );

        $catVeh = HrAssetCategory::withoutGlobalScope('tenant')->updateOrCreate(
            ['legal_entity_id' => $tenantId, 'code' => 'CAT-VEHICLE'],
            ['name' => 'Şirket Aracı', 'is_active' => true]
        );

        $asset1 = HrAsset::withoutGlobalScope('tenant')->updateOrCreate(
            ['legal_entity_id' => $tenantId, 'asset_code' => 'ZLM-LAP-001'],
            [
                'asset_category_id' => $catLap->id,
                'name' => 'MacBook Pro 16" M3 Max 36GB',
                'brand' => 'Apple',
                'model' => 'MacBook Pro 16',
                'serial_number' => 'C02G1882MD6K',
                'purchased_at' => Carbon::now()->subYear()->toDateString(),
                'purchase_value' => 125000.00,
                'currency' => 'TRY',
                'status' => AssetStatus::Assigned,
            ]
        );

        $asset2 = HrAsset::withoutGlobalScope('tenant')->updateOrCreate(
            ['legal_entity_id' => $tenantId, 'asset_code' => 'ZLM-VEH-001'],
            [
                'asset_category_id' => $catVeh->id,
                'name' => 'Toyota Corolla 1.5 Hybrid Passion X-Pack',
                'brand' => 'Toyota',
                'model' => 'Corolla 2024',
                'serial_number' => '34 ZLM 100',
                'purchased_at' => Carbon::now()->subYear()->toDateString(),
                'purchase_value' => 1450000.00,
                'currency' => 'TRY',
                'status' => AssetStatus::Assigned,
            ]
        );

        HrAssetAssignment::withoutGlobalScope('tenant')->updateOrCreate(
            ['legal_entity_id' => $tenantId, 'asset_id' => $asset1->id, 'employee_id' => $employees[1]->id],
            [
                'assigned_at' => Carbon::now()->subYear()->toDateString(),
                'status' => AssetAssignmentStatus::Assigned,
                'assignment_note' => 'Yazılım Mimarisi çalışmaları için yüksek performanslı bilgisayar zimmeti.',
            ]
        );

        HrAssetAssignment::withoutGlobalScope('tenant')->updateOrCreate(
            ['legal_entity_id' => $tenantId, 'asset_id' => $asset2->id, 'employee_id' => $employees[5]->id],
            [
                'assigned_at' => Carbon::now()->subYear()->toDateString(),
                'status' => AssetAssignmentStatus::Assigned,
                'assignment_note' => 'Saha Satış Direktörlüğü şirket aracı zimmeti.',
            ]
        );
    }

    private function seedPerformance(int $tenantId, array $employees): void
    {
        $cycle = HrPerformanceCycle::withoutGlobalScope('tenant')->updateOrCreate(
            ['legal_entity_id' => $tenantId, 'name' => '2026 Q3 Performans Değerlendirme Dönemi'],
            [
                'starts_on' => Carbon::now()->startOfQuarter()->toDateString(),
                'ends_on' => Carbon::now()->endOfQuarter()->toDateString(),
                'evaluation_starts_on' => Carbon::now()->subDays(10)->toDateString(),
                'evaluation_ends_on' => Carbon::now()->addDays(20)->toDateString(),
                'status' => PerformanceCycleStatus::Active,
                'anonymity_threshold' => 2,
                'auto_reminders' => true,
                'reminder_days_before' => [7, 3, 1],
            ]
        );

        HrPerformanceGoal::withoutGlobalScope('tenant')->updateOrCreate(
            ['legal_entity_id' => $tenantId, 'cycle_id' => $cycle->id, 'employee_id' => $employees[1]->id, 'title' => 'Mikroservis Dönüşümü ve Ölçeklenebilirlik'],
            [
                'type' => 'okr',
                'measurement_type' => 'numeric',
                'description' => 'Çekirdek motorların mikroservis mimarisine geçiş oranını %80 seviyesine çıkarmak.',
                'metric_unit' => '%',
                'baseline_value' => 20.0,
                'target_value' => 80.0,
                'current_value' => 65.0,
                'weight' => 40.0,
                'status' => 'active',
            ]
        );

        HrPerformanceGoal::withoutGlobalScope('tenant')->updateOrCreate(
            ['legal_entity_id' => $tenantId, 'cycle_id' => $cycle->id, 'employee_id' => $employees[5]->id, 'title' => 'Kurumsal Satış Cirosu Büyümesi'],
            [
                'type' => 'kpi',
                'measurement_type' => 'numeric',
                'description' => 'Q3 kurumsal satış cirosunu v1.5 hedeflerine ulaştırmak.',
                'metric_unit' => 'TRY',
                'baseline_value' => 5000000.0,
                'target_value' => 8500000.0,
                'current_value' => 7200000.0,
                'weight' => 50.0,
                'status' => 'active',
            ]
        );

        $template = \App\Modules\Hr\Performance\Models\HrPerformanceTemplate::withoutGlobalScope('tenant')->updateOrCreate(
            ['legal_entity_id' => $tenantId, 'name' => 'Standart Değerlendirme Formu'],
            [
                'version' => 1,
                'sections' => [[
                    'title' => 'Hedef ve Yetkinlikler',
                    'questions' => [
                        ['id' => 'goal_delivery', 'label' => 'Hedeflere ulaşma', 'type' => 'rating', 'required' => true, 'weight' => 40],
                        ['id' => 'collaboration', 'label' => 'İş birliği', 'type' => 'rating', 'required' => true, 'weight' => 30],
                        ['id' => 'leadership', 'label' => 'Sorumluluk ve liderlik', 'type' => 'rating', 'required' => true, 'weight' => 30],
                        ['id' => 'feedback', 'label' => 'Gelişim geri bildirimi', 'type' => 'text', 'required' => false, 'weight' => 0],
                    ],
                ]],
                'is_active' => true,
            ]
        );

        $evaluationRows = [
            [$employees[2], $employees[1], ReviewerType::Manager, 40, false, 88, ['goal_delivery' => 5, 'collaboration' => 4, 'leadership' => 4, 'feedback' => 'Teknik liderlik sorumluluğunu daha geniş ekiplere yayabilir.']],
            [$employees[2], $employees[3], ReviewerType::Peer, 20, true, 80, ['goal_delivery' => 4, 'collaboration' => 4, 'leadership' => 4, 'feedback' => 'Bilgi paylaşımı ve ekip desteği güçlü.']],
            [$employees[2], $employees[2], ReviewerType::Self, 20, false, 84, ['goal_delivery' => 4, 'collaboration' => 4, 'leadership' => 5, 'feedback' => 'Mimari kararların dokümantasyonunu geliştireceğim.']],
            [$employees[5], $employees[0], ReviewerType::Manager, 40, false, 92, ['goal_delivery' => 5, 'collaboration' => 4, 'leadership' => 5, 'feedback' => 'Satış tahmin doğruluğu ve ekip koordinasyonu çok güçlü.']],
        ];
        foreach ($evaluationRows as [$evaluated, $reviewer, $type, $weight, $anonymous, $score, $answers]) {
            HrPerformanceEvaluation::withoutGlobalScope('tenant')->updateOrCreate(
                ['legal_entity_id' => $tenantId, 'cycle_id' => $cycle->id, 'employee_id' => $evaluated->id, 'reviewer_employee_id' => $reviewer->id, 'reviewer_type' => $type->value],
                ['template_id' => $template->id, 'reviewer_weight' => $weight, 'is_anonymous' => $anonymous, 'answers' => $answers, 'overall_score' => $score, 'status' => PerformanceEvaluationStatus::Submitted, 'submitted_at' => Carbon::now()->subDays(2)]
            );
        }
        app(\App\Modules\Hr\Performance\Services\PerformanceResultService::class)->recalculate($tenantId, $cycle->id, $employees[2]->id);
        app(\App\Modules\Hr\Performance\Services\PerformanceResultService::class)->recalculate($tenantId, $cycle->id, $employees[5]->id);

        foreach ([['LEADERSHIP', 'Liderlik'], ['COMMUNICATION', 'İletişim'], ['CHANGE', 'Değişime uyum']] as [$code, $name]) {
            $competency = HrCompetency::withoutGlobalScope('tenant')->updateOrCreate(
                ['legal_entity_id' => $tenantId, 'code' => $code],
                ['name' => $name, 'is_active' => true]
            );
            HrEmployeeCompetency::withoutGlobalScope('tenant')->updateOrCreate(
                ['legal_entity_id' => $tenantId, 'employee_id' => $employees[2]->id, 'competency_id' => $competency->id, 'cycle_id' => $cycle->id],
                ['current_level' => $code === 'LEADERSHIP' ? 3 : 4, 'target_level' => 5, 'evidence' => 'Q3 gözlem ve proje teslim kayıtları']
            );
        }
    }

    private function seedTraining(int $tenantId, array $employees): void
    {
        $course1 = HrTrainingCourse::withoutGlobalScope('tenant')->updateOrCreate(
            ['legal_entity_id' => $tenantId, 'code' => 'TRN-ISG-01'],
            [
                'title' => 'İş Sağlığı ve Güvenliği Temel Eğitimi 2026',
                'category' => 'isg',
                'description' => 'Yasal zorunlu genel iş sağlığı, güvenlik ve acil durum eğitimi.',
                'duration_minutes' => 240,
                'passing_score' => 70.0,
                'certificate_validity_months' => 12,
                'is_mandatory' => true,
                'is_active' => true,
            ]
        );

        $course2 = HrTrainingCourse::withoutGlobalScope('tenant')->updateOrCreate(
            ['legal_entity_id' => $tenantId, 'code' => 'TRN-DEV-02'],
            [
                'title' => 'İleri Seviye Laravel Mimarisi & Clean Code',
                'category' => 'technical',
                'description' => 'Domain Driven Design ve Modüler Laravel Mimarisi Eğitimi.',
                'duration_minutes' => 480,
                'passing_score' => 80.0,
                'certificate_validity_months' => 24,
                'is_mandatory' => false,
                'is_active' => true,
            ]
        );

        $session1 = HrTrainingSession::withoutGlobalScope('tenant')->updateOrCreate(
            ['legal_entity_id' => $tenantId, 'course_id' => $course1->id],
            [
                'delivery_type' => 'online',
                'instructor' => 'Dr. Murat Erdoğan (İSG Başuzmanı)',
                'starts_at' => Carbon::now()->subDays(10),
                'ends_at' => Carbon::now()->subDays(10)->addHours(4),
                'capacity' => 30,
                'status' => 'completed',
            ]
        );

        $session2 = HrTrainingSession::withoutGlobalScope('tenant')->updateOrCreate(
            ['legal_entity_id' => $tenantId, 'course_id' => $course2->id],
            [
                'delivery_type' => 'classroom',
                'instructor' => 'Mehmet Can (Yazılım Mimar)',
                'location' => 'ZOLM Toplantı Salonu A',
                'starts_at' => Carbon::now()->addDays(5),
                'ends_at' => Carbon::now()->addDays(5)->addHours(8),
                'capacity' => 15,
                'status' => 'scheduled',
            ]
        );

        // Enrollment & Certificates
        foreach ([1, 2, 3] as $idx) {
            $enrollment = HrTrainingEnrollment::withoutGlobalScope('tenant')->updateOrCreate(
                ['legal_entity_id' => $tenantId, 'session_id' => $session1->id, 'employee_id' => $employees[$idx]->id],
                [
                    'status' => 'completed',
                    'progress_percent' => 100,
                    'exam_score' => 90.0 + $idx * 2,
                    'passed' => true,
                    'completed_at' => Carbon::now()->subDays(10),
                ]
            );

            HrCertificate::withoutGlobalScope('tenant')->updateOrCreate(
                ['legal_entity_id' => $tenantId, 'enrollment_id' => $enrollment->id],
                [
                    'employee_id' => $employees[$idx]->id,
                    'course_id' => $course1->id,
                    'certificate_number' => 'CERT-ISG-2026-00' . $idx,
                    'issued_on' => Carbon::now()->subDays(10)->toDateString(),
                    'expires_on' => Carbon::now()->subDays(10)->addMonths(12)->toDateString(),
                    'status' => 'valid',
                ]
            );
        }
    }

    private function seedEngagement(int $tenantId, array $employees): void
    {
        $survey = HrSurvey::withoutGlobalScope('tenant')->updateOrCreate(
            ['legal_entity_id' => $tenantId, 'title' => '2026 Yıl Ortası Çalışan Memnuniyeti Anketi (eNPS)'],
            [
                'description' => 'Şirket kültürümüzü ve çalışma ortamımızı geliştirmek için görüşleriniz bizim için çok kıymetli.',
                'questions' => [
                    ['id' => 'q1', 'type' => 'enps', 'label' => 'ZOLM\'u bir çalışma yeri olarak arkadaşlarınıza önerme olasılığınız nedir?', 'required' => true],
                    ['id' => 'q2', 'type' => 'scale', 'label' => 'Şirket içi iletişim ve şeffaflık seviyesinden memnun musunuz?', 'required' => true],
                ],
                'starts_on' => Carbon::now()->subDays(15)->toDateString(),
                'ends_on' => Carbon::now()->addDays(15)->toDateString(),
                'status' => 'active',
                'is_anonymous' => true,
                'minimum_report_count' => 3,
            ]
        );

        $scores = [10, 9, 10, 9, 10, 9, 8, 7];
        for ($i = 0; $i < 8; $i++) {
            $enpsScore = $scores[$i];
            $hash = hash_hmac('sha256', $survey->id . ':' . $employees[$i]->id, (string) config('app.key'));

            HrSurveyResponse::withoutGlobalScope('tenant')->updateOrCreate(
                ['legal_entity_id' => $tenantId, 'survey_id' => $survey->id, 'respondent_hash' => $hash],
                [
                    'answers' => ['q1' => $enpsScore, 'q2' => 5],
                    'enps_score' => $enpsScore,
                    'submitted_at' => Carbon::now()->subDays($i + 1),
                ]
            );
        }

        // Public Recognitions
        HrRecognition::withoutGlobalScope('tenant')->updateOrCreate(
            ['legal_entity_id' => $tenantId, 'sender_employee_id' => $employees[1]->id, 'recipient_employee_id' => $employees[2]->id, 'category' => 'teamwork'],
            ['message' => 'Kritik veritabanı performans optimizasyonunu canlıya sorunsuz aldığın ve müthiş bir özveri gösterdiğin için tebrikler! 🚀', 'is_public' => true, 'recognized_at' => Carbon::now()->subDays(2)]
        );

        HrRecognition::withoutGlobalScope('tenant')->updateOrCreate(
            ['legal_entity_id' => $tenantId, 'sender_employee_id' => $employees[0]->id, 'recipient_employee_id' => $employees[10]->id, 'category' => 'leadership'],
            ['message' => 'Mali dönem kapanışındaki titiz ve profesyonel çalışmanız tüm ekibe ilham verdi. Teşekkürler!', 'is_public' => true, 'recognized_at' => Carbon::now()->subDays(5)]
        );
    }

    private function seedSafety(int $tenantId, array $employees): void
    {
        $incident = HrSafetyIncident::withoutGlobalScope('tenant')->updateOrCreate(
            ['legal_entity_id' => $tenantId, 'incident_number' => 'INC-2026-001'],
            [
                'reporter_employee_id' => $employees[9]->id,
                'affected_employee_id' => $employees[9]->id,
                'incident_type' => 'near_miss',
                'severity' => 'minor',
                'occurred_at' => Carbon::now()->subDays(4),
                'location' => 'Ana Depo B Blok 3. Raf',
                'description_encrypted' => 'Depoda üst raftan ambalaj kutusunun düşmesi ancak herhangi bir çalışana isabet etmemesi.',
                'immediate_action_encrypted' => 'Raf altı alan geçici olarak şeritle kapatıldı.',
                'lost_time' => false,
                'status' => 'under_investigation',
                'source_hash' => hash('sha256', 'INC-2026-001'),
            ]
        );

        HrSafetyAction::withoutGlobalScope('tenant')->updateOrCreate(
            ['legal_entity_id' => $tenantId, 'safety_incident_id' => $incident->id],
            [
                'title' => 'Depo Yüksek Raflarına Emniyet Filesi ve Koruyucu Izgara Takılması',
                'owner_user_id' => null,
                'due_on' => Carbon::now()->addDays(5)->toDateString(),
                'status' => 'in_progress',
            ]
        );

        HrHealthRecord::withoutGlobalScope('tenant')->updateOrCreate(
            ['legal_entity_id' => $tenantId, 'employee_id' => $employees[9]->id, 'record_type' => 'periodic'],
            [
                'recorded_on' => Carbon::now()->subMonths(2)->toDateString(),
                'expires_on' => Carbon::now()->addMonths(10)->toDateString(),
                'provider_encrypted' => 'Medicana İSG Ortak Sağlık Birimi',
                'result_encrypted' => 'fit',
                'details_encrypted' => 'Akciğer Grafisi ve Odyometri testleri normatif sınırlarda.',
            ]
        );
    }

    private function seedRecruitmentAndLifecycle(int $tenantId, array $employees): void
    {
        // OPEN POSITIONS (So "AÇIK KADRO: 3" shows on Dashboard!)
        $posting1 = HrJobPosting::withoutGlobalScope('tenant')->updateOrCreate(
            ['legal_entity_id' => $tenantId, 'code' => 'JOB-2026-01'],
            [
                'title' => 'Senior Full Stack Developer (Laravel & Livewire)',
                'department_id' => $employees[1]->activeEmployment->department_id,
                'position_id' => $employees[1]->activeEmployment->position_id,
                'headcount' => 2,
                'description' => 'Büyüyen yazılım ekibimizde yüksek ölçekli e-ticaret ve ERP projelerinde görev alacak kıdemli geliştirici arıyoruz.',
                'requirements' => ['PHP 8.2+', 'Laravel', 'Livewire 3', 'MySQL', 'Redis'],
                'status' => 'published',
                'published_on' => Carbon::now()->subDays(10)->toDateString(),
                'closes_on' => Carbon::now()->addDays(20)->toDateString(),
            ]
        );

        $posting2 = HrJobPosting::withoutGlobalScope('tenant')->updateOrCreate(
            ['legal_entity_id' => $tenantId, 'code' => 'JOB-2026-02'],
            [
                'title' => 'Kurumsal Satış & Müşteri İlişkileri Uzmanı',
                'department_id' => $employees[5]->activeEmployment->department_id,
                'position_id' => $employees[5]->activeEmployment->position_id,
                'headcount' => 1,
                'description' => 'B2B SaaS çözümlerimizin kurumsal müşteri portföyünü yönetecek deneyimli takım arkadaşı.',
                'requirements' => ['B2B Satış Deneyimi', 'CRM Kullanımı', 'Sunum Becerisi'],
                'status' => 'published',
                'published_on' => Carbon::now()->subDays(5)->toDateString(),
                'closes_on' => Carbon::now()->addDays(25)->toDateString(),
            ]
        );

        // Candidates & Applications
        $candidate1 = HrCandidate::withoutGlobalScope('tenant')->updateOrCreate(
            ['legal_entity_id' => $tenantId, 'email' => 'tolga.ozer@example.test'],
            [
                'first_name' => 'Tolga',
                'last_name' => 'Özer',
                'phone' => '+90 555 999 8877',
                'source' => 'LinkedIn',
                'status' => 'active',
            ]
        );

        $app1 = HrApplication::withoutGlobalScope('tenant')->updateOrCreate(
            ['legal_entity_id' => $tenantId, 'job_posting_id' => $posting1->id, 'candidate_id' => $candidate1->id],
            [
                'current_stage' => 'interview',
                'status' => 'in_progress',
                'human_score' => 4.5,
                'summary' => '10 yıl PHP/Laravel tecrübesi var, teknik mülakatı başarıyla geçti.',
            ]
        );

        $offer = HrJobOffer::withoutGlobalScope('tenant')->updateOrCreate(
            ['legal_entity_id' => $tenantId, 'application_id' => $app1->id],
            [
                'gross_salary' => 88000.00,
                'currency' => 'TRY',
                'proposed_start_date' => Carbon::now()->addDays(15)->toDateString(),
                'expires_on' => Carbon::now()->addDays(5)->toDateString(),
                'status' => 'approved',
                'approval_note' => 'Yönetim kurulu tarafından onaylanan teklif paketi.',
            ]
        );

        // ONBOARDING CHECKLIST FOR DUYGU POLAT (Junior Hire)
        $onboarding = HrOnboardingChecklist::withoutGlobalScope('tenant')->updateOrCreate(
            ['legal_entity_id' => $tenantId, 'employee_id' => $employees[13]->id],
            [
                'title' => 'Junior Yazılım Geliştirici Oryantasyon Süreci',
                'starts_on' => Carbon::now()->subDays(5)->toDateString(),
                'target_completion_on' => Carbon::now()->addDays(10)->toDateString(),
                'status' => 'in_progress',
            ]
        );

        $onboardingTasks = [
            ['key' => 'access', 'title' => 'E-posta, Slack ve GitHub Hesap Erişimlerinin Açılması', 'done' => true],
            ['key' => 'asset', 'title' => 'Dizüstü Bilgisayar ve Donanım Zimmet Teslimi', 'done' => true],
            ['key' => 'welcome', 'title' => 'Şirket Oryantasyon Sunumu ve İK Evrak Tamamlama', 'done' => true],
            ['key' => 'buddy', 'title' => 'Buddy (Mehmet Can) İle İlk Kod Review ve Proje Tanıtımı', 'done' => false],
            ['key' => 'compliance', 'title' => 'KVKK, Bilgi Güvenliği ve İSG Politikalarının Onayı', 'done' => true],
            ['key' => 'checkin-30', 'title' => '30. Gün İK ve Yönetici Uyum Görüşmesi', 'done' => false],
        ];

        foreach ($onboardingTasks as $t) {
            HrOnboardingTask::withoutGlobalScope('tenant')->updateOrCreate(
                ['legal_entity_id' => $tenantId, 'checklist_id' => $onboarding->id, 'template_key' => $t['key']],
                [
                    'title' => $t['title'],
                    'due_on' => Carbon::now()->addDays(3)->toDateString(),
                    'is_required' => true,
                    'status' => $t['done'] ? 'completed' : 'pending',
                    'completed_at' => $t['done'] ? Carbon::now()->subDay() : null,
                ]
            );
        }
    }

    private function seedAnalyticsAndSupport(int $tenantId, array $employees): void
    {
        app(BuildHrAnalyticsSnapshotAction::class)->execute(
            now()->startOfYear()->toDateString(),
            now()->toDateString()
        );

        $ticket = HrSupportTicket::withoutGlobalScope('tenant')->updateOrCreate(
            ['legal_entity_id' => $tenantId, 'ticket_number' => 'TICK-2026-001'],
            [
                'requester_employee_id' => $employees[3]->id,
                'category' => 'hardware',
                'subject' => 'Ek Monitör Talebi (27 İnç 4K)',
                'description_encrypted' => 'Frontend tasarımları ve kod incelemeleri için çift ekran ihtiyacım bulunmaktadır.',
                'priority' => 'medium',
                'status' => 'open',
            ]
        );

        $message = HrSupportMessage::withoutGlobalScope('tenant')->firstOrNew([
            'legal_entity_id' => $tenantId,
            'support_ticket_id' => $ticket->id,
            'author_user_id' => null,
        ]);
        $message->body_encrypted = 'Frontend tasarımları ve kod incelemeleri için çift ekran ihtiyacım bulunmaktadır.';
        $message->save();

        // Upcoming Holidays for Dashboard calendar widget
        $holidays = [
            ['name' => 'Zafer Bayramı', 'date' => '2026-08-30'],
            ['name' => 'Cumhuriyet Bayramı', 'date' => '2026-10-29'],
            ['name' => 'Yılbaşı', 'date' => '2027-01-01'],
        ];

        foreach ($holidays as $h) {
            $date = Carbon::parse($h['date']);
            $holiday = HrHoliday::withoutGlobalScope('tenant')
                ->where('legal_entity_id', $tenantId)
                ->whereDate('date', $date->toDateString())
                ->first() ?? new HrHoliday([
                    'legal_entity_id' => $tenantId,
                    'date' => $date->toDateString(),
                ]);
            $holiday->fill([
                'name' => $h['name'],
                'year' => $date->year,
                'type' => 'national',
                'is_recurring' => true,
            ]);
            $holiday->save();
        }
    }

    private function referencePeriodStart(): Carbon
    {
        return now()->subMonthNoOverflow()->startOfMonth();
    }

    private function referencePeriodEnd(): Carbon
    {
        return now()->subMonthNoOverflow()->endOfMonth();
    }

    private function upsertAttendanceAnomaly(
        int $tenantId,
        int $employeeId,
        Carbon $workDate,
        string $type,
        string $severity,
        array $details
    ): void {
        $anomaly = HrAttendanceAnomaly::withoutGlobalScope('tenant')
            ->where('legal_entity_id', $tenantId)
            ->where('employee_id', $employeeId)
            ->whereDate('work_date', $workDate->toDateString())
            ->where('type', $type)
            ->first() ?? new HrAttendanceAnomaly([
                'legal_entity_id' => $tenantId,
                'employee_id' => $employeeId,
                'work_date' => $workDate->toDateString(),
                'type' => $type,
            ]);

        $anomaly->fill([
            'severity' => $severity,
            'status' => 'open',
            'details' => $details,
        ]);
        $anomaly->save();
    }

    private function seedAttendanceEventsForDate(
        int $tenantId,
        array $employees,
        HrAttendanceDevice $mainDevice,
        HrAttendanceDevice $warehouseDevice,
        Carbon $date
    ): void {
        foreach ($employees as $idx => $employee) {
            $hasApprovedLeave = HrLeaveRequest::withoutGlobalScope('tenant')
                ->where('legal_entity_id', $tenantId)
                ->where('employee_id', $employee->id)
                ->where('status', LeaveRequestStatus::Approved->value)
                ->whereDate('start_date', '<=', $date->toDateString())
                ->whereDate('end_date', '>=', $date->toDateString())
                ->exists();

            if ($hasApprovedLeave) {
                // Mock senaryoda izinli çalışana PDKS hareketi üretmek,
                // puantaj kapanışını haklı olarak engelleyen bir çelişki oluşturur.
                HrAttendanceEvent::withoutGlobalScope('tenant')
                    ->where('legal_entity_id', $tenantId)
                    ->where('employee_id', $employee->id)
                    ->where('source', 'device')
                    ->where('source_key', 'like', sprintf('MOCK-PDKS-%d-%s-%%', $employee->id, $date->format('Ymd')))
                    ->delete();

                continue;
            }

            $isWarehouse = in_array($idx, [8, 9], true);
            $device = $isWarehouse ? $warehouseDevice : $mainDevice;
            $events = $isWarehouse
                ? [
                    AttendanceEventType::CheckIn->value => '07:30:00',
                    AttendanceEventType::BreakStart->value => '12:00:00',
                    AttendanceEventType::BreakEnd->value => '13:00:00',
                    AttendanceEventType::CheckOut->value => '16:30:00',
                ]
                : [
                    AttendanceEventType::CheckIn->value => '08:30:00',
                    AttendanceEventType::BreakStart->value => '12:30:00',
                    AttendanceEventType::BreakEnd->value => '13:30:00',
                    AttendanceEventType::CheckOut->value => '17:30:00',
                ];

            foreach ($events as $eventType => $time) {
                $sourceKey = sprintf('MOCK-PDKS-%d-%s-%s', $employee->id, $date->format('Ymd'), strtoupper($eventType));
                $occurredAt = Carbon::parse($date->toDateString().' '.$time);
                HrAttendanceEvent::withoutGlobalScope('tenant')->updateOrCreate(
                    ['legal_entity_id' => $tenantId, 'source' => 'device', 'source_key' => $sourceKey],
                    [
                        'employee_id' => $employee->id,
                        'attendance_device_id' => $device->id,
                        'occurred_at' => $occurredAt,
                        'event_type' => $eventType,
                        'payload_hash' => hash('sha256', $sourceKey.'|'.$occurredAt->toIso8601String()),
                        'metadata' => ['mock' => true, 'device_code' => $device->code],
                        'is_manual' => false,
                    ]
                );
            }
        }
    }

    private function upsertShiftAssignment(
        int $tenantId,
        int $employeeId,
        Carbon $shiftDate,
        int $shiftTemplateId
    ): void {
        $assignment = HrShiftAssignment::withoutGlobalScope('tenant')
            ->where('legal_entity_id', $tenantId)
            ->where('employee_id', $employeeId)
            ->whereDate('shift_date', $shiftDate->toDateString())
            ->first() ?? new HrShiftAssignment([
                'legal_entity_id' => $tenantId,
                'employee_id' => $employeeId,
                'shift_date' => $shiftDate->toDateString(),
            ]);

        $assignment->fill([
            'shift_template_id' => $shiftTemplateId,
            'status' => ShiftAssignmentStatus::Published,
        ]);
        $assignment->save();
    }

    private function upsertLeaveRequest(
        int $tenantId,
        int $employeeId,
        Carbon $startDate,
        array $attributes
    ): HrLeaveRequest {
        $request = HrLeaveRequest::withoutGlobalScope('tenant')
            ->where('legal_entity_id', $tenantId)
            ->where('employee_id', $employeeId)
            ->whereDate('start_date', $startDate->toDateString())
            ->first() ?? new HrLeaveRequest([
                'legal_entity_id' => $tenantId,
                'employee_id' => $employeeId,
                'start_date' => $startDate->toDateString(),
            ]);

        $request->fill($attributes);
        $request->save();

        return $request;
    }

    private function createMockPdfFile(int $tenantId, User $uploader, string $key, string $label): HrFile
    {
        $safeKey = Str::slug($key);
        $path = "hr/{$tenantId}/mock/{$safeKey}.pdf";
        $content = "%PDF-1.4\n% ZOLM MOCK DOCUMENT - NOT AN OFFICIAL RECORD\n% {$label}\n%%EOF\n";
        Storage::disk(config('hr.file.disk', 'private'))->put($path, $content);

        return HrFile::updateOrCreate(
            ['legal_entity_id' => $tenantId, 'disk_path' => $path],
            [
                'uploader_id' => $uploader->id,
                'category' => 'mock',
                'original_name' => $safeKey.'.pdf',
                'mime_type' => 'application/pdf',
                'size_bytes' => strlen($content),
                'checksum' => hash('sha256', $content),
                'is_verified' => true,
                'verified_by' => $uploader->id,
                'verified_at' => now(),
            ]
        );
    }
}
