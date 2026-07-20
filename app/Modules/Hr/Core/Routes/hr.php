<?php

use App\Modules\Hr\Core\Http\Controllers\HrExportController;
use App\Modules\Hr\Core\Http\Controllers\HrFileController;
use App\Modules\Hr\Core\Http\Middleware\HrAuthorize;
use App\Modules\Hr\Core\Http\Middleware\ResolveHrTenant;
use App\Modules\Hr\Core\Livewire\HrDashboard;
use App\Modules\Hr\Core\Livewire\HrSettings;
use App\Modules\Hr\Organization\Livewire\OrganizationSettings;
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
