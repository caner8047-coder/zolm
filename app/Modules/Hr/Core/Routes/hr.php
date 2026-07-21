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
        Route::get('/settings/shift-templates', ShiftTemplateList::class)->name('settings.shift-templates')
            ->middleware('hr.authorize:hr.shifts.manage');
        Route::get('/settings/shift-templates/create', ShiftTemplateForm::class)->name('settings.shift-templates.create')
            ->middleware('hr.authorize:hr.shifts.manage');
        Route::get('/settings/shift-templates/{id}/edit', ShiftTemplateForm::class)->name('settings.shift-templates.edit')
            ->middleware('hr.authorize:hr.shifts.manage');
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
