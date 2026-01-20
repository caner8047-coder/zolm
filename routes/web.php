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

    // File downloads - doğrudan binary response
    Route::get('/download/{reportFile}', function (\App\Models\ReportFile $reportFile) {
        $fullPath = \Illuminate\Support\Facades\Storage::disk('local')->path($reportFile->file_path);
        
        if (!file_exists($fullPath)) {
            abort(404, 'Dosya bulunamadı');
        }

        // BinaryFileResponse kullan - output buffering sorununu önler
        return response()->download($fullPath, $reportFile->filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $reportFile->filename . '"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    })->name('download');

    // Report History alias
    Route::get('/report-history', ReportHistory::class)->name('report-history');

    // ============================================
    // ADMIN ROUTES
    // ============================================
    Route::middleware(\App\Http\Middleware\AdminMiddleware::class)->prefix('admin')->group(function () {
        Route::get('/', \App\Livewire\Admin\Dashboard::class)->name('admin.dashboard');
        Route::get('/users', \App\Livewire\Admin\UserManager::class)->name('admin.users');
        Route::get('/logs', \App\Livewire\Admin\ActivityLogs::class)->name('admin.logs');
    });
});

// DEBUG: Excel test route - sorun tespiti için
Route::get('/test-excel', function () {
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Test');
    $sheet->setCellValue('A1', 'Merhaba');
    $sheet->setCellValue('B1', 'Dünya');
    $sheet->setCellValue('A2', 'Test');
    $sheet->setCellValue('B2', '123');
    
    $tempFile = storage_path('app/test-excel.xlsx');
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save($tempFile);
    
    return response()->download($tempFile, 'test-dosya.xlsx', [
        'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ])->deleteFileAfterSend(true);
});

// DEBUG: Son rapor dosyasını test et
Route::get('/test-last-file', function () {
    $lastFile = \App\Models\ReportFile::latest()->first();
    if (!$lastFile) {
        return 'Dosya bulunamadı';
    }
    
    $fullPath = \Illuminate\Support\Facades\Storage::disk('local')->path($lastFile->file_path);
    
    if (!file_exists($fullPath)) {
        return 'Dosya mevcut değil: ' . $lastFile->file_path;
    }
    
    // Dosyayı PhpSpreadsheet ile aç ve kontrol et
    try {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($fullPath);
        $info = [
            'path' => $fullPath,
            'size' => filesize($fullPath),
            'sheet_count' => $spreadsheet->getSheetCount(),
            'sheets' => [],
        ];
        
        foreach ($spreadsheet->getSheetNames() as $name) {
            $sheet = $spreadsheet->getSheetByName($name);
            $info['sheets'][] = [
                'name' => $name,
                'rows' => $sheet->getHighestRow(),
                'cols' => $sheet->getHighestColumn(),
            ];
        }
        
        return response()->json($info);
        
    } catch (\Exception $e) {
        return 'Hata: ' . $e->getMessage();
    }
});


