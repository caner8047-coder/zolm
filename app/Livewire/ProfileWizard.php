<?php

namespace App\Livewire;

use App\Models\Profile;
use App\Services\AIProfileAnalyzer;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class ProfileWizard extends Component
{
    use WithFileUploads;

    // Wizard State
    public int $currentStep = 1;
    public int $totalSteps = 4;

    // Step 1: Temel Bilgiler
    public string $name = '';
    public string $type = 'production';
    public string $description = '';

    // Step 2: Örnek Girdi
    public $sampleInputFile;
    public array $inputStructure = [];
    public bool $inputAnalyzed = false;

    // Step 3: Çıktı Tanımı
    public $sampleOutputFile;
    public string $aiPrompt = '';
    public array $outputStructure = [];
    public bool $outputAnalyzed = false;

    // Step 4: AI Analiz
    public bool $isAnalyzing = false;
    public array $generatedRules = [];
    public string $analysisError = '';
    public bool $analysisComplete = false;

    // JSON Editor
    public bool $showJsonEditor = false;
    public string $jsonEditorContent = '';
    public string $jsonEditorError = '';

    // Validation Rules
    protected function rules()
    {
        return match ($this->currentStep) {
            1 => [
                'name' => 'required|string|max:255',
                'type' => 'required|in:production,operation',
            ],
            2 => [
                'sampleInputFile' => 'required|file|mimes:xlsx,xls|max:10240',
            ],
            3 => [
                'aiPrompt' => 'required|string|min:20',
            ],
            default => [],
        };
    }

    protected $messages = [
        'name.required' => 'Profil adı zorunludur.',
        'sampleInputFile.required' => 'Örnek girdi dosyası yükleyin.',
        'aiPrompt.required' => 'Lütfen istediğiniz çıktıyı açıklayın.',
        'aiPrompt.min' => 'Açıklama en az 20 karakter olmalı.',
    ];

    // === NAVIGATION ===

    public function nextStep()
    {
        $this->validate();

        if ($this->currentStep === 2 && !$this->inputAnalyzed) {
            $this->analyzeInputFile();
            return;
        }

        if ($this->currentStep === 3 && $this->sampleOutputFile && !$this->outputAnalyzed) {
            $this->analyzeOutputFile();
        }

        if ($this->currentStep < $this->totalSteps) {
            $this->currentStep++;
        }

        if ($this->currentStep === 4) {
            $this->runAIAnalysis();
        }
    }

    public function previousStep()
    {
        if ($this->currentStep > 1) {
            $this->currentStep--;
        }
    }

    public function goToStep(int $step)
    {
        if ($step <= $this->currentStep && $step >= 1) {
            $this->currentStep = $step;
        }
    }

    // === FILE ANALYSIS ===

    public function analyzeInputFile()
    {
        if (!$this->sampleInputFile) return;

        try {
            $analyzer = app(AIProfileAnalyzer::class);
            $this->inputStructure = $analyzer->extractFileStructure(
                $this->sampleInputFile->getRealPath()
            );
            $this->inputAnalyzed = true;
        } catch (\Exception $e) {
            $this->addError('sampleInputFile', 'Dosya analiz edilemedi: ' . $e->getMessage());
        }
    }

    public function analyzeOutputFile()
    {
        if (!$this->sampleOutputFile) return;

        try {
            $analyzer = app(AIProfileAnalyzer::class);
            $this->outputStructure = $analyzer->extractFileStructure(
                $this->sampleOutputFile->getRealPath()
            );
            $this->outputAnalyzed = true;
        } catch (\Exception $e) {
            $this->addError('sampleOutputFile', 'Dosya analiz edilemedi: ' . $e->getMessage());
        }
    }

    // === AI ANALYSIS ===

    public function runAIAnalysis()
    {
        $this->isAnalyzing = true;
        $this->analysisError = '';
        $this->analysisComplete = false;

        try {
            $analyzer = app(AIProfileAnalyzer::class);
            
            $outputPath = $this->sampleOutputFile 
                ? $this->sampleOutputFile->getRealPath() 
                : null;

            $this->generatedRules = $analyzer->analyze(
                inputFilePath: $this->sampleInputFile->getRealPath(),
                outputFilePath: $outputPath,
                userDescription: $this->aiPrompt,
                inputStructure: $this->inputStructure,
                outputStructure: $this->outputStructure
            );

            $this->analysisComplete = true;
            
            // JSON editor için hazırla
            $this->jsonEditorContent = json_encode($this->generatedRules, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            $this->analysisError = $e->getMessage();
        } finally {
            $this->isAnalyzing = false;
        }
    }

    public function retryAnalysis()
    {
        $this->runAIAnalysis();
    }

    // === JSON EDITOR ===

    public function openJsonEditor()
    {
        $this->jsonEditorContent = json_encode($this->generatedRules, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $this->jsonEditorError = '';
        $this->showJsonEditor = true;
    }

    public function closeJsonEditor()
    {
        $this->showJsonEditor = false;
        $this->jsonEditorError = '';
    }

    public function saveJsonEditorChanges()
    {
        $this->jsonEditorError = '';

        // JSON syntax kontrolü
        $decoded = json_decode($this->jsonEditorContent, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->jsonEditorError = 'Geçersiz JSON formatı: ' . json_last_error_msg();
            return;
        }

        // Zorunlu alanları kontrol et
        if (!isset($decoded['version'])) {
            $decoded['version'] = '1.0';
        }

        if (!isset($decoded['outputs']) || empty($decoded['outputs'])) {
            $this->jsonEditorError = 'En az bir çıktı (outputs) tanımlanmalı.';
            return;
        }

        // Outputs içinde sheets kontrolü
        foreach ($decoded['outputs'] as $index => $output) {
            if (!isset($output['filename_pattern'])) {
                $this->jsonEditorError = "Çıktı #" . ($index + 1) . " için filename_pattern gerekli.";
                return;
            }
        }

        // Başarılı - kuralları güncelle
        $this->generatedRules = $decoded;
        $this->showJsonEditor = false;
        
        session()->flash('json-saved', 'JSON kuralları başarıyla güncellendi.');
    }

    public function formatJson()
    {
        $decoded = json_decode($this->jsonEditorContent, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $this->jsonEditorContent = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $this->jsonEditorError = '';
        } else {
            $this->jsonEditorError = 'JSON formatlanamadı: ' . json_last_error_msg();
        }
    }

    // === SAVE PROFILE ===

    public function saveProfile()
    {
        if (!$this->analysisComplete || empty($this->generatedRules)) {
            return;
        }

        // Dosyaları kaydet
        $inputPath = null;
        $outputPath = null;

        if ($this->sampleInputFile) {
            $inputPath = $this->sampleInputFile->store('profile-samples', 'local');
        }

        if ($this->sampleOutputFile) {
            $outputPath = $this->sampleOutputFile->store('profile-samples', 'local');
        }

        // Profil oluştur
        $profile = Profile::create([
            'user_id' => auth()->id(),
            'name' => $this->name,
            'type' => $this->type,
            'input_config' => $this->inputStructure,
            'output_config' => $this->outputStructure,
            'ai_prompt' => $this->aiPrompt,
            'sample_input_path' => $inputPath,
            'sample_output_path' => $outputPath,
            'ai_generated_rules' => $this->generatedRules,
            'is_ai_generated' => true,
            'is_default' => false,
            'status' => 'ready',
        ]);

        session()->flash('success', "'{$this->name}' profili başarıyla oluşturuldu!");
        
        return redirect()->route('profiles');
    }

    // === HELPERS ===

    public function getStepTitleProperty(): string
    {
        return match ($this->currentStep) {
            1 => 'Temel Bilgiler',
            2 => 'Örnek Girdi Dosyası',
            3 => 'Çıktı Tanımı',
            4 => 'AI Analiz & Onay',
            default => '',
        };
    }

    public function getStepDescriptionProperty(): string
    {
        return match ($this->currentStep) {
            1 => 'Profilinize bir isim verin ve türünü seçin.',
            2 => 'Dönüştürmek istediğiniz örnek XLS dosyasını yükleyin.',
            3 => 'İstediğiniz çıktı formatını açıklayın veya örnek dosya yükleyin.',
            4 => 'AI kuralları oluşturuyor. Sonuçları inceleyip düzenleyebilirsiniz.',
            default => '',
        };
    }

    public function render()
    {
        return view('livewire.profile-wizard')
            ->layout('layouts.app');
    }
}
