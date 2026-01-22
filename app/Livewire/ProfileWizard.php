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

    // Step 2: Örnek Girdi (Çoklu dosya desteği)
    public $tempInputFile; // Geçici dosya (yükleme için)
    public array $sampleInputFiles = []; // {path, name, structure}
    public array $inputStructure = [];
    public bool $inputAnalyzed = false;

    // Step 3: Çıktı Tanımı (Çoklu dosya desteği)
    public $tempOutputFile; // Geçici dosya (yükleme için)
    public array $sampleOutputFiles = []; // {path, name, structure}
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
                'sampleInputFiles' => 'required|array|min:1',
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

    // === FILE MANAGEMENT ===

    public function addInputFile()
    {
        if (!$this->tempInputFile) return;

        try {
            $analyzer = app(AIProfileAnalyzer::class);
            $structure = $analyzer->extractFileStructure($this->tempInputFile->getRealPath());
            
            $this->sampleInputFiles[] = [
                'name' => $this->tempInputFile->getClientOriginalName(),
                'file' => $this->tempInputFile,
                'structure' => $structure,
            ];
            
            $this->tempInputFile = null;
            $this->updateInputStructure();
            
        } catch (\Exception $e) {
            $this->addError('tempInputFile', 'Dosya analiz edilemedi: ' . $e->getMessage());
        }
    }

    public function removeInputFile($index)
    {
        if (isset($this->sampleInputFiles[$index])) {
            unset($this->sampleInputFiles[$index]);
            $this->sampleInputFiles = array_values($this->sampleInputFiles);
            $this->updateInputStructure();
        }
    }

    protected function updateInputStructure()
    {
        $this->inputStructure = ['sheets' => []];
        foreach ($this->sampleInputFiles as $file) {
            if (isset($file['structure']['sheets'])) {
                foreach ($file['structure']['sheets'] as $sheet) {
                    $sheet['file'] = $file['name'];
                    $this->inputStructure['sheets'][] = $sheet;
                }
            }
        }
        $this->inputAnalyzed = !empty($this->sampleInputFiles);
    }

    public function addOutputFile()
    {
        if (!$this->tempOutputFile) return;

        try {
            $analyzer = app(AIProfileAnalyzer::class);
            $structure = $analyzer->extractFileStructure($this->tempOutputFile->getRealPath());
            
            $this->sampleOutputFiles[] = [
                'name' => $this->tempOutputFile->getClientOriginalName(),
                'file' => $this->tempOutputFile,
                'structure' => $structure,
            ];
            
            $this->tempOutputFile = null;
            $this->updateOutputStructure();
            
        } catch (\Exception $e) {
            $this->addError('tempOutputFile', 'Dosya analiz edilemedi: ' . $e->getMessage());
        }
    }

    public function removeOutputFile($index)
    {
        if (isset($this->sampleOutputFiles[$index])) {
            unset($this->sampleOutputFiles[$index]);
            $this->sampleOutputFiles = array_values($this->sampleOutputFiles);
            $this->updateOutputStructure();
        }
    }

    protected function updateOutputStructure()
    {
        $this->outputStructure = ['sheets' => []];
        foreach ($this->sampleOutputFiles as $file) {
            if (isset($file['structure']['sheets'])) {
                foreach ($file['structure']['sheets'] as $sheet) {
                    $sheet['file'] = $file['name'];
                    $this->outputStructure['sheets'][] = $sheet;
                }
            }
        }
        $this->outputAnalyzed = !empty($this->sampleOutputFiles);
    }

    // Eski metodlar - geri uyumluluk için
    public function analyzeInputFile()
    {
        $this->addInputFile();
    }

    public function analyzeOutputFile()
    {
        $this->addOutputFile();
    }

    // === AI ANALYSIS ===

    public function runAIAnalysis()
    {
        $this->isAnalyzing = true;
        $this->analysisError = '';
        $this->analysisComplete = false;

        try {
            $analyzer = app(AIProfileAnalyzer::class);
            
            // İlk girdi dosyasının yolunu al
            $inputPath = !empty($this->sampleInputFiles) 
                ? $this->sampleInputFiles[0]['file']->getRealPath() 
                : null;
            
            // İlk çıktı dosyasının yolunu al
            $outputPath = !empty($this->sampleOutputFiles) 
                ? $this->sampleOutputFiles[0]['file']->getRealPath() 
                : null;

            $this->generatedRules = $analyzer->analyze(
                inputFilePath: $inputPath,
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
    
    public bool $isSaving = false;

    public function saveProfile()
    {
        // Çift kayıt engelleme
        if ($this->isSaving) {
            return;
        }
        
        if (!$this->analysisComplete || empty($this->generatedRules)) {
            return;
        }
        
        $this->isSaving = true;

        try {
            // Girdi dosyalarını kaydet
            $inputPaths = [];
            foreach ($this->sampleInputFiles as $inputFile) {
                if (isset($inputFile['file'])) {
                    $inputPaths[] = $inputFile['file']->store('profile-samples', 'local');
                }
            }

            // Çıktı dosyalarını kaydet
            $outputPaths = [];
            foreach ($this->sampleOutputFiles as $outputFile) {
                if (isset($outputFile['file'])) {
                    $outputPaths[] = $outputFile['file']->store('profile-samples', 'local');
                }
            }

            // Profil oluştur
            $profile = Profile::create([
                'user_id' => auth()->id(),
                'name' => $this->name,
                'type' => $this->type,
                'input_config' => $this->inputStructure,
                'output_config' => $this->outputStructure,
                'ai_prompt' => $this->aiPrompt,
                'sample_input_path' => !empty($inputPaths) ? $inputPaths[0] : null,
                'sample_output_path' => !empty($outputPaths) ? $outputPaths[0] : null,
                'ai_generated_rules' => $this->generatedRules,
                'is_ai_generated' => true,
                'is_default' => false,
                'status' => 'ready',
            ]);

            session()->flash('success', "'{$this->name}' profili başarıyla oluşturuldu!");
        
            return redirect()->route('profiles');
            
        } finally {
            $this->isSaving = false;
        }
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
