<?php

use App\Modules\Hr\Core\Http\Controllers\HrExportController;
use App\Modules\Hr\Core\Http\Controllers\HrFileController;
use App\Modules\Hr\Core\Http\Middleware\HrAuthorize;
use App\Modules\Hr\Core\Http\Middleware\ResolveHrTenant;
use App\Modules\Hr\Core\Livewire\HrDashboard;
use App\Modules\Hr\Core\Livewire\HrSettings;
use App\Modules\Hr\Document\Actions\ExportDocumentsAction;
use App\Modules\Hr\Document\Livewire\DocumentList;
use App\Modules\Hr\Document\Livewire\DocumentTypeForm;
use App\Modules\Hr\Document\Livewire\DocumentTypeList;
use App\Modules\Hr\Organization\Livewire\OrganizationSettings;
use App\Modules\Hr\Organization\Livewire\TeamForm;
use App\Modules\Hr\Organization\Livewire\TeamList;
use App\Modules\Hr\Organization\Livewire\UnitForm;
use App\Modules\Hr\Organization\Livewire\UnitList;
use App\Modules\Hr\Leave\Livewire\LeaveTypeForm;
use App\Modules\Hr\Leave\Livewire\LeaveTypeList;
use App\Modules\Hr\Leave\Livewire\LeavePolicyForm;
use App\Modules\Hr\Leave\Livewire\LeavePolicyList;
use App\Modules\Hr\Leave\Livewire\LeaveList;
use App\Modules\Hr\Leave\Livewire\LeaveRequestForm;
use App\Modules\Hr\Leave\Livewire\LeaveApprovalInbox;
use App\Modules\Hr\Leave\Livewire\LeaveBalanceManager;
use App\Modules\Hr\Leave\Livewire\MyLeaveList;
use App\Modules\Hr\Leave\Actions\ExportLeavesAction;
use App\Modules\Hr\Personnel\Livewire\EmployeeCreate;
use App\Modules\Hr\Personnel\Livewire\EmployeeDetail;
use App\Modules\Hr\Personnel\Livewire\EmployeeEdit;
use App\Modules\Hr\Personnel\Livewire\EmployeeList;
use App\Modules\Hr\Shift\Livewire\ShiftPlanner;
use App\Modules\Hr\Shift\Livewire\ShiftTemplateForm;
use App\Modules\Hr\Shift\Livewire\ShiftTemplateList;
use App\Modules\Hr\Shift\Livewire\MyShiftAvailability;
use App\Modules\Hr\Shift\Livewire\MyShiftChangeRequests;
use App\Modules\Hr\Shift\Livewire\ShiftChangeApprovalInbox;
use App\Modules\Hr\Attendance\Livewire\AttendanceAnomalyInbox;
use App\Modules\Hr\Attendance\Livewire\AttendanceDeviceManager;
use App\Modules\Hr\Attendance\Livewire\AttendanceEventList;
use App\Modules\Hr\Attendance\Livewire\MyAttendanceTerminal;
use App\Modules\Hr\Timesheet\Livewire\TimesheetDetail;
use App\Modules\Hr\Timesheet\Livewire\TimesheetPeriodList;
use App\Modules\Hr\Timesheet\Actions\ExportTimesheetAction;
use App\Modules\Hr\Overtime\Livewire\OvertimeTypeManager;
use App\Modules\Hr\Overtime\Livewire\OvertimeWorkspace;
use App\Modules\Hr\Payroll\Livewire\PayrollRuleManager;
use App\Modules\Hr\Payroll\Livewire\PayrollWorkspace;
use App\Modules\Hr\Expense\Livewire\ExpenseCategoryManager;
use App\Modules\Hr\Expense\Livewire\ExpenseWorkspace;
use App\Modules\Hr\Advance\Livewire\AdvanceWorkspace;
use App\Modules\Hr\Asset\Livewire\AssetWorkspace;
use App\Modules\Hr\Performance\Livewire\PerformanceWorkspace;
use App\Modules\Hr\Training\Livewire\TrainingWorkspace;
use App\Modules\Hr\Engagement\Livewire\EngagementWorkspace;
use App\Modules\Hr\Recruitment\Livewire\RecruitmentWorkspace;
use App\Modules\Hr\Lifecycle\Livewire\LifecycleWorkspace;
use App\Modules\Hr\Compensation\Livewire\CompensationWorkspace;
use App\Modules\Hr\Analytics\Livewire\HrAnalyticsDashboard;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', ResolveHrTenant::class])->prefix('hr')->name('hr.')->group(function () {

    // Ana sayfa
    Route::get('/', HrDashboard::class)->name('dashboard')
        ->middleware('hr.authorize:hr.dashboard.view');

    // Ayarlar
    Route::get('/settings', HrSettings::class)->name('settings')
        ->middleware('hr.authorize:hr.settings.manage');

    // Organizasyon ayarları
    Route::get('/settings/organization', OrganizationSettings::class)->name('settings.organization')
        ->middleware('hr.authorize:hr.org_structure.view');

    // Birim CRUD
    Route::get('/settings/units', UnitList::class)->name('settings.units')
        ->middleware('hr.authorize:hr.org_structure.view');
    Route::get('/settings/units/create', UnitForm::class)->name('settings.units.create')
        ->middleware('hr.authorize:hr.org_structure.manage');
    Route::get('/settings/units/{unit}/edit', UnitForm::class)->name('settings.units.edit')
        ->middleware('hr.authorize:hr.org_structure.manage');

    // Ekip CRUD
    Route::get('/settings/teams', TeamList::class)->name('settings.teams')
        ->middleware('hr.authorize:hr.org_structure.view');
    Route::get('/settings/teams/create', TeamForm::class)->name('settings.teams.create')
        ->middleware('hr.authorize:hr.org_structure.manage');
    Route::get('/settings/teams/{team}/edit', TeamForm::class)->name('settings.teams.edit')
        ->middleware('hr.authorize:hr.org_structure.manage');

    // Belge yönetimi — personel modül lisansı + yetki kontrolü
    Route::middleware('hr.module:personel')->group(function () {
        // Belge türleri
        Route::get('/settings/document-types', DocumentTypeList::class)->name('settings.document-types')
            ->middleware('hr.authorize:hr.documents.view');
        Route::get('/settings/document-types/create', DocumentTypeForm::class)->name('settings.document-types.create')
            ->middleware('hr.authorize:hr.documents.manage_types');
        Route::get('/settings/document-types/{documentType}/edit', DocumentTypeForm::class)->name('settings.document-types.edit')
            ->middleware('hr.authorize:hr.documents.manage_types');

        // Personel belgeleri
        Route::get('/documents', DocumentList::class)->name('documents')
            ->middleware('hr.authorize:hr.documents.view');

        // Belge export
        Route::get('/documents/export', function () {
            $action = app(ExportDocumentsAction::class);
            $path = $action->execute();
            $fullPath = storage_path("app/private/{$path}");
            return response()->download($fullPath, basename($path))->deleteFileAfterSend(true);
        })->name('documents.export')
            ->middleware('hr.authorize:hr.documents.export');
    });

    Route::middleware('hr.module:ucret')->group(function () {
        Route::get('/compensation', CompensationWorkspace::class)->name('compensation')->middleware('hr.authorize:hr.salary.view');
    });

    Route::middleware('hr.module:analitik')->group(function () {
        Route::get('/analytics', HrAnalyticsDashboard::class)->name('analytics')->middleware('hr.authorize:hr.analytics.view');
    });

    // İzin ayarları — izin modül lisansı + tür yönetimi
    Route::middleware('hr.module:izin')->group(function () {
        Route::get('/leaves', LeaveList::class)->name('leaves')
            ->middleware('hr.authorize:hr.leaves.view');
        Route::get('/leaves/create', LeaveRequestForm::class)->name('leaves.create')
            ->middleware('hr.authorize:hr.leaves.create');
        Route::get('/my/leaves', MyLeaveList::class)->name('my-leaves')
            ->middleware('hr.authorize:hr.leaves.create');
        Route::get('/my/leaves/create', LeaveRequestForm::class)->name('my-leaves.create')
            ->defaults('selfService', true)
            ->middleware('hr.authorize:hr.leaves.create');
        Route::get('/leaves/approvals', LeaveApprovalInbox::class)->name('leaves.approvals')
            ->middleware('hr.authorize:hr.leaves.approve');
        Route::get('/leaves/balances', LeaveBalanceManager::class)->name('leaves.balances')
            ->middleware('hr.authorize:hr.leaves.manage_balance');
        Route::get('/leaves/export', function () {
            $path = app(ExportLeavesAction::class)->execute(request()->only(['status', 'leave_type_id']));
            $fullPath = storage_path("app/private/{$path}");
            return response()->download($fullPath, basename($path))->deleteFileAfterSend(true);
        })->name('leaves.export')
            ->middleware('hr.authorize:hr.leaves.export');
        Route::get('/settings/leave-types', LeaveTypeList::class)->name('settings.leave-types')
            ->middleware('hr.authorize:hr.leaves.view');
        Route::get('/settings/leave-types/create', LeaveTypeForm::class)->name('settings.leave-types.create')
            ->middleware('hr.authorize:hr.leaves.manage_type');
        Route::get('/settings/leave-types/{id}/edit', LeaveTypeForm::class)->name('settings.leave-types.edit')
            ->middleware('hr.authorize:hr.leaves.manage_type');
        Route::get('/settings/leave-policies', LeavePolicyList::class)->name('settings.leave-policies')
            ->middleware('hr.authorize:hr.leaves.view');
        Route::get('/settings/leave-policies/create', LeavePolicyForm::class)->name('settings.leave-policies.create')
            ->middleware('hr.authorize:hr.leaves.manage_policy');
        Route::get('/settings/leave-policies/{id}/edit', LeavePolicyForm::class)->name('settings.leave-policies.edit')
            ->middleware('hr.authorize:hr.leaves.manage_policy');
    });

    Route::middleware('hr.module:vardiya')->group(function () {
        Route::get('/shifts', ShiftPlanner::class)->name('shifts')
            ->middleware('hr.authorize:hr.shifts.view');
        Route::get('/my/shift-availability', MyShiftAvailability::class)->name('my-shift-availability')
            ->middleware('hr.authorize:hr.shifts.view');
        Route::get('/my/shift-change-requests', MyShiftChangeRequests::class)->name('my-shift-change-requests')
            ->middleware('hr.authorize:hr.shifts.view');
        Route::get('/shifts/change-requests', ShiftChangeApprovalInbox::class)->name('shifts.change-requests')
            ->middleware('hr.authorize:hr.shifts.plan');
        Route::get('/settings/shift-templates', ShiftTemplateList::class)->name('settings.shift-templates')
            ->middleware('hr.authorize:hr.shifts.manage');
        Route::get('/settings/shift-templates/create', ShiftTemplateForm::class)->name('settings.shift-templates.create')
            ->middleware('hr.authorize:hr.shifts.manage');
        Route::get('/settings/shift-templates/{id}/edit', ShiftTemplateForm::class)->name('settings.shift-templates.edit')
            ->middleware('hr.authorize:hr.shifts.manage');
    });

    Route::middleware('hr.module:pdks')->group(function () {
        Route::get('/attendance', AttendanceEventList::class)->name('attendance')
            ->middleware('hr.authorize:hr.attendance.view');
        Route::get('/attendance/anomalies', AttendanceAnomalyInbox::class)->name('attendance.anomalies')
            ->middleware('hr.authorize:hr.attendance.view_anomaly');
        Route::get('/my/attendance', MyAttendanceTerminal::class)->name('my-attendance')
            ->middleware('hr.authorize:hr.attendance.view');
        Route::get('/settings/attendance-devices', AttendanceDeviceManager::class)->name('settings.attendance-devices')
            ->middleware('hr.authorize:hr.attendance.manage');
    });

    Route::middleware('hr.module:puantaj')->group(function () {
        Route::get('/timesheet', TimesheetPeriodList::class)->name('timesheets')
            ->middleware('hr.authorize:hr.timesheet.view');
        Route::get('/timesheet/{period}', TimesheetDetail::class)->name('timesheets.show')
            ->middleware('hr.authorize:hr.timesheet.view');
        Route::get('/timesheet/{period}/export', function (int $period) {
            $tenantId = app(\App\Modules\Hr\Core\Services\TenantContext::class)->getId();
            $model = \App\Modules\Hr\Timesheet\Models\HrTimesheetPeriod::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->findOrFail($period);
            $path = app(ExportTimesheetAction::class)->execute($model);
            return response()->download(storage_path("app/private/{$path}"), basename($path))->deleteFileAfterSend(true);
        })->name('timesheets.export')->middleware('hr.authorize:hr.timesheet.view');
        Route::get('/overtime', OvertimeWorkspace::class)->name('overtime')
            ->middleware('hr.authorize:hr.timesheet.view');
        Route::get('/my/overtime', OvertimeWorkspace::class)->name('my-overtime')
            ->defaults('selfService', true)
            ->middleware('hr.authorize:hr.timesheet.view');
        Route::get('/settings/overtime-types', OvertimeTypeManager::class)->name('settings.overtime-types')
            ->middleware('hr.authorize:hr.timesheet.close');
    });

    Route::middleware('hr.module:bordro')->group(function () {
        Route::get('/payroll', PayrollWorkspace::class)->name('payroll')
            ->middleware('hr.authorize:hr.payroll.view');
        Route::get('/settings/payroll-rules', PayrollRuleManager::class)->name('settings.payroll-rules')
            ->middleware('hr.authorize:hr.payroll.manage_rules');
    });

    Route::middleware('hr.module:masraf')->group(function () {
        Route::get('/expenses', ExpenseWorkspace::class)->name('expenses')
            ->middleware('hr.authorize:hr.expenses.view');
        Route::get('/my/expenses', ExpenseWorkspace::class)->name('my-expenses')
            ->defaults('selfService', true)
            ->middleware('hr.authorize:hr.expenses.create');
        Route::get('/settings/expense-categories', ExpenseCategoryManager::class)->name('settings.expense-categories')
            ->middleware('hr.authorize:hr.expenses.approve');
        Route::get('/expenses/receipts/{file}', [HrFileController::class, 'downloadExpenseReceipt'])->name('expenses.receipt');
    });

    Route::middleware('hr.module:avans')->group(function () {
        Route::get('/advances', AdvanceWorkspace::class)->name('advances')->middleware('hr.authorize:hr.advances.view');
        Route::get('/my/advances', AdvanceWorkspace::class)->name('my-advances')->defaults('selfService', true)->middleware('hr.authorize:hr.advances.create');
    });

    Route::middleware('hr.module:zimmet')->group(function () {
        Route::get('/assets', AssetWorkspace::class)->name('assets')->middleware('hr.authorize:hr.assets.view');
        Route::get('/my/assets', AssetWorkspace::class)->name('my-assets')->defaults('selfService', true)->middleware('hr.authorize:hr.assets.view');
    });

    Route::middleware('hr.module:performans')->group(function () {
        Route::get('/performance', PerformanceWorkspace::class)->name('performance')->middleware('hr.authorize:hr.performance.view');
        Route::get('/my/performance', PerformanceWorkspace::class)->name('my-performance')->defaults('selfService', true)->middleware('hr.authorize:hr.performance.view');
    });

    Route::middleware('hr.module:egitim')->group(function () {
        Route::get('/training', TrainingWorkspace::class)->name('training')->middleware('hr.authorize:hr.training.view');
        Route::get('/my/training', TrainingWorkspace::class)->name('my-training')->defaults('selfService', true)->middleware('hr.authorize:hr.training.view');
    });

    Route::middleware('hr.module:baglilik')->group(function () {
        Route::get('/engagement', EngagementWorkspace::class)->name('engagement')->middleware('hr.authorize:hr.engagement.view');
        Route::get('/my/engagement', EngagementWorkspace::class)->name('my-engagement')->defaults('selfService', true)->middleware('hr.authorize:hr.engagement.view');
    });

    Route::middleware('hr.module:aday_takip')->group(function () {
        Route::get('/recruitment', RecruitmentWorkspace::class)->name('recruitment')->middleware('hr.authorize:hr.recruitment.view');
    });

    Route::middleware('hr.module:personel')->group(function () {
        Route::get('/lifecycle', LifecycleWorkspace::class)->name('lifecycle')->middleware('hr.authorize:hr.lifecycle.view');
    });

    // Personel
    Route::get('/personnel', EmployeeList::class)->name('personnel')
        ->middleware('hr.authorize:hr.employees.view');

    Route::get('/personnel/create', EmployeeCreate::class)->name('personnel.create')
        ->middleware('hr.authorize:hr.employees.create');

    Route::get('/personnel/{employee}', EmployeeDetail::class)->name('personnel.show')
        ->middleware('hr.authorize:hr.employees.view');

    Route::get('/personnel/{employee}/edit', EmployeeEdit::class)->name('personnel.edit')
        ->middleware('hr.authorize:hr.employees.update');

    // Export
    Route::get('/personnel/export', [HrExportController::class, 'exportEmployees'])
        ->name('personnel.export')
        ->middleware('hr.authorize:hr.employees.export');

    // Dosya işlemleri
    Route::get('/files/{file}/download', [HrFileController::class, 'download'])
        ->name('files.download')
        ->middleware('hr.authorize:hr.employees.view');

    Route::get('/files/{file}/signed-url', [HrFileController::class, 'signedUrl'])
        ->name('files.signed-url')
        ->middleware('hr.authorize:hr.employees.view');
});
