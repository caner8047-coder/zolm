<?php

use App\Http\Controllers\Auth\LoginController;
use App\Livewire\AIChat;
use App\Livewire\ProductionMotor;
use App\Livewire\OperationMotor;
use App\Livewire\ProfileManager;
use App\Livewire\ProfileWizard;
use App\Livewire\ReportHistory;
use Illuminate\Support\Facades\Route;

// Public routes
Route::get('/', function () {
    return redirect()->route('login');
});

// Auth routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

Route::post('/logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');

// Protected routes
Route::middleware('auth')->group(function () {
    // Dashboard (redirect to production by default)
    Route::get('/dashboard', function () {
        if (auth()->user()->canAccessProduction()) {
            return redirect()->route('production');
        } elseif (auth()->user()->canAccessOperation()) {
            return redirect()->route('operation');
        } else {
            return redirect()->route('reports');
        }
    })->name('dashboard');

    // Production Motor
    Route::get('/production', ProductionMotor::class)
        ->name('production')
        ->middleware('can:accessProduction');

    // Operation Motor
    Route::get('/operation', OperationMotor::class)
        ->name('operation')
        ->middleware('can:accessOperation');

    // Report History
    Route::get('/reports', ReportHistory::class)
        ->name('reports')
        ->middleware('can:accessReports');

    // AI Chat
    Route::get('/ai-chat', AIChat::class)->name('ai-chat');

    // Profile Management
    Route::get('/profiles', ProfileManager::class)
        ->name('profiles')
        ->middleware('can:admin');

    // Profile Wizard (AI-powered profile creation)
    Route::get('/profiles/create', ProfileWizard::class)
        ->name('profile.wizard')
        ->middleware('can:admin');

    // File downloads
    Route::get('/download/{reportFile}', function (\App\Models\ReportFile $reportFile) {
        $fullPath = \Illuminate\Support\Facades\Storage::disk('local')->path($reportFile->file_path);
        if (file_exists($fullPath)) {
            return response()->streamDownload(function () use ($fullPath) {
                echo file_get_contents($fullPath);
            }, $reportFile->filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
        }
        abort(404);
    })->name('download');
});
