<?php

namespace App\Livewire;

use App\Models\Profile;
use App\Services\DynamicTransformEngine;
use App\Services\ProductionEngine;
use App\Services\OperationEngine;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class UniversalMotor extends Component
{
    use WithFileUploads;

    public $file;
    public bool $isProcessing = false;
    public string $message = '';
    public string $messageType = 'info';
    public array $generatedFiles = [];
    public $selectedProfileId;
    public string $motorType;

    public function mount(string $type = 'production')
    {
        $this->motorType = $type;
        $this->selectedProfileId = Profile::where('type', $type)
            ->where('is_default', true)
            ->ready()
            ->first()?->id;
    }

    public function process()
    {
        $this->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:10240',
        ]);

        $this->isProcessing = true;
        $this->message = '';
        $this->generatedFiles = [];

        $profile = Profile::find($this->selectedProfileId);
        
        if (!$profile) {
            $this->message = 'Profil bulunamadı!';
            $this->messageType = 'error';
            $this->isProcessing = false;
            return;
        }

        $report = \App\Models\Report::create([
            'user_id' => auth()->id(),
            'profile_id' => $profile->id,
            'original_filename' => $this->file->getClientOriginalName(),
            'status' => 'pending',
        ]);

        // Profil türüne göre motor seç
        if ($profile->isAiGenerated()) {
            $engine = app(DynamicTransformEngine::class);
        } elseif ($profile->isProduction()) {
            $engine = app(ProductionEngine::class);
        } else {
            $engine = app(OperationEngine::class);
        }

        $result = $engine->run($this->file, $profile, $report);

        $this->isProcessing = false;
        $this->message = $result['message'];
        $this->messageType = $result['success'] ? 'success' : 'error';

        if ($result['success']) {
            $this->generatedFiles = $report->fresh()->files->toArray();
        }

        $this->reset('file');
    }

    public function downloadFile($fileId)
    {
        $file = \App\Models\ReportFile::find($fileId);
        if ($file) {
            $fullPath = Storage::disk('local')->path($file->file_path);
            if (file_exists($fullPath)) {
                return response()->streamDownload(function () use ($fullPath) {
                    echo file_get_contents($fullPath);
                }, $file->filename, [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ]);
            }
        }
        
        $this->message = 'Dosya bulunamadı!';
        $this->messageType = 'error';
    }

    public function getProfilesProperty()
    {
        return Profile::where('type', $this->motorType)
            ->ready()
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();
    }

    public function getSelectedProfileProperty()
    {
        return Profile::find($this->selectedProfileId);
    }

    public function render()
    {
        return view('livewire.universal-motor')
            ->layout('layouts.app');
    }
}
