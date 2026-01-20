<?php

namespace App\Livewire;

use App\Models\Profile;
use App\Models\Report;
use App\Services\DynamicTransformEngine;
use App\Services\OperationEngine;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OperationMotor extends Component
{
    use WithFileUploads;

    public $file;
    public $isProcessing = false;
    public $message = '';
    public $messageType = 'info';
    public array $generatedFiles = [];
    public $selectedProfileId;

    public function mount()
    {
        $this->selectedProfileId = Profile::where('type', 'operation')
            ->where('is_default', true)
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

        $profile = Profile::find($this->selectedProfileId) 
            ?? Profile::where('type', 'operation')->where('is_default', true)->first();

        if (!$profile) {
            $this->message = 'Operasyon profili bulunamadı!';
            $this->messageType = 'error';
            $this->isProcessing = false;
            return;
        }

        $report = Report::create([
            'user_id' => auth()->id(),
            'profile_id' => $profile->id,
            'original_filename' => $this->file->getClientOriginalName(),
            'status' => 'pending',
        ]);

        // Profil türüne göre motor seç
        if ($profile->isAiGenerated()) {
            $engine = app(DynamicTransformEngine::class);
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

    public function downloadFile($fileId): ?BinaryFileResponse
    {
        $file = \App\Models\ReportFile::find($fileId);
        
        if (!$file) {
            $this->message = 'Dosya kaydı bulunamadı!';
            $this->messageType = 'error';
            return null;
        }

        $fullPath = Storage::disk('local')->path($file->file_path);
        
        if (!file_exists($fullPath)) {
            $this->message = 'Dosya bulunamadı: ' . $file->file_path;
            $this->messageType = 'error';
            return null;
        }

        // BinaryFileResponse kullan - daha güvenilir
        return response()->download($fullPath, $file->filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }

    public function downloadAll()
    {
        $this->message = 'Toplu indirme özelliği yakında eklenecek.';
        $this->messageType = 'info';
    }

    public bool $isSaving = false;
    public string $saveMessage = '';

    public function saveAllToHistory()
    {
        if ($this->isSaving) {
            return;
        }

        if (empty($this->generatedFiles)) {
            $this->message = 'Kaydedilecek dosya yok!';
            $this->messageType = 'error';
            return;
        }

        $this->isSaving = true;
        $this->saveMessage = '';

        try {
            // Dosyalar zaten Report'a bağlı, sadece onay mesajı göster
            $count = count($this->generatedFiles);
            $this->saveMessage = "✓ {$count} dosya başarıyla geçmişe kaydedildi!";
            $this->message = $this->saveMessage;
            $this->messageType = 'success';
        } catch (\Exception $e) {
            $this->saveMessage = 'Kaydetme hatası: ' . $e->getMessage();
            $this->message = $this->saveMessage;
            $this->messageType = 'error';
        } finally {
            $this->isSaving = false;
        }
    }

    public function getProfilesProperty()
    {
        return Profile::where('type', 'operation')
            ->where(function ($q) {
                $q->where('status', 'ready')->orWhereNull('status');
            })
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
        return view('livewire.operation-motor')
            ->layout('layouts.app');
    }
}
