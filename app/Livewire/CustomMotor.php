<?php

namespace App\Livewire;

use App\Models\Profile;
use App\Models\Report;
use App\Services\DynamicTransformEngine;
use Livewire\Component;
use Livewire\WithFileUploads;

class CustomMotor extends Component
{
    use WithFileUploads;

    public $file;
    public bool $isProcessing = false;
    public string $message = '';
    public string $messageType = 'info';
    public array $generatedFiles = [];
    public $selectedProfileId = null;

    public function mount(): void
    {
        $this->selectedProfileId = Profile::query()
            ->where('user_id', auth()->id())
            ->where('type', 'custom')
            ->where(function ($q) {
                $q->where('status', 'ready')->orWhereNull('status');
            })
            ->orderBy('is_default', 'desc')
            ->value('id');
    }

    public function process(): void
    {
        $this->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:10240',
        ]);

        $this->isProcessing = true;
        $this->message = '';
        $this->generatedFiles = [];

        $profile = Profile::query()
            ->where('user_id', auth()->id())
            ->where('type', 'custom')
            ->where('id', $this->selectedProfileId)
            ->first();

        if (!$profile) {
            $this->message = 'Özel motor profili bulunamadı.';
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

        $result = app(DynamicTransformEngine::class)->run($this->file, $profile, $report);

        $this->isProcessing = false;
        $this->message = $result['message'];
        $this->messageType = $result['success'] ? 'success' : 'error';

        if ($result['success']) {
            $this->generatedFiles = $report->fresh()->files->toArray();
        }

        $this->reset('file');
    }

    public function getProfilesProperty()
    {
        return Profile::query()
            ->where('user_id', auth()->id())
            ->where('type', 'custom')
            ->where(function ($q) {
                $q->where('status', 'ready')->orWhereNull('status');
            })
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();
    }

    public function render()
    {
        return view('livewire.custom-motor')
            ->layout('layouts.app');
    }
}
