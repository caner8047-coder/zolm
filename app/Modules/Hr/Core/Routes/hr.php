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
use App\Modules\Hr\Personnel\Livewire\EmployeeCreate;
use App\Modules\Hr\Personnel\Livewire\EmployeeDetail;
use App\Modules\Hr\Personnel\Livewire\EmployeeEdit;
use App\Modules\Hr\Personnel\Livewire\EmployeeList;
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
