<?php

use App\Modules\Hr\Core\Http\Controllers\HrFileController;
use App\Modules\Hr\Core\Http\Middleware\HrAuthorize;
use App\Modules\Hr\Core\Http\Middleware\ResolveHrTenant;
use App\Modules\Hr\Core\Livewire\HrDashboard;
use App\Modules\Hr\Core\Livewire\HrSettings;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', ResolveHrTenant::class])->prefix('hr')->name('hr.')->group(function () {

    // Ana sayfa
    Route::get('/', HrDashboard::class)->name('dashboard')
        ->middleware('hr.authorize:hr.dashboard.view');

    // Ayarlar
    Route::get('/settings', HrSettings::class)->name('settings')
        ->middleware('hr.authorize:hr.settings.manage');

    // Dosya işlemleri
    Route::get('/files/{file}/download', [HrFileController::class, 'download'])
        ->name('files.download')
        ->middleware('hr.authorize:hr.employees.view');

    Route::get('/files/{file}/signed-url', [HrFileController::class, 'signedUrl'])
        ->name('files.signed-url')
        ->middleware('hr.authorize:hr.employees.view');
});
