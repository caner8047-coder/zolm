<?php

namespace App\Livewire\CustomerCare;

use App\Models\SupportChannel;
use App\Models\SupportOnboardingState;
use App\Livewire\CustomerCare\Concerns\ResolvesAccessibleStores;
use App\Services\Support\TenantContext;
use App\Services\Support\BrandVoiceService;
use App\Services\Support\CustomerCarePilotReadinessService;
use App\Services\Support\CustomerCareOnboardingVerificationService;
use Livewire\Component;

class Onboarding extends Component
{
    use ResolvesAccessibleStores;

    public $selectedStoreId;
    public $currentStep = 1;
    public $stepsCompleted = [];
    public $recommendedMode = 'manual';
    public $status = 'in_progress';

    // Brand Voice Inputs
    public $tone;
    public $prompt_context;
    public $return_policy;
    public $hitap;
    public $use_emoji = true;
    public $greeting;
    public $signature;
    public $sample_response;
    public $sampleQuestion = 'Merhaba';
    public $verificationResult = [];

    public $successMessage = '';
    public $errorMessage = '';

    public function mount()
    {
        // 1. Feature Flag Protection
        if (!config('customer-care.onboarding_enabled', false)) {
            abort(404, 'Müşteri hizmetleri onboarding sihirbazı aktif değil.');
        }

        $myStores = $this->getMyStores();
        if ($myStores->isEmpty()) {
            abort(403, 'Erişilebilir mağazanız bulunmuyor.');
        }

        if (!$this->selectedStoreId) {
            $this->selectedStoreId = $myStores->first()->id;
        }

        $this->loadOnboardingState();
    }

    public function updatedSelectedStoreId()
    {
        $this->enforceStoreAccess($this->selectedStoreId);
        $this->currentStep = 1;
        $this->loadOnboardingState();
    }

    protected function getMyStores()
    {
        return $this->resolveAccessibleStores();
    }

    protected function enforceStoreAccess($storeId)
    {
        $user = auth()->user() ?? TenantContext::getSystemActor();
        TenantContext::enforceStoreAccess($storeId, $user);
    }

    protected function loadOnboardingState()
    {
        $this->enforceStoreAccess($this->selectedStoreId);

        $state = SupportOnboardingState::firstOrCreate(
            ['store_id' => $this->selectedStoreId],
            [
                'current_step' => 1,
                'steps_completed' => [],
                'status' => 'in_progress',
                'recommended_mode' => 'manual',
            ]
        );

        $this->currentStep = $state->current_step;
        $this->stepsCompleted = $state->steps_completed ?? [];
        $this->recommendedMode = $state->recommended_mode ?? 'manual';
        $this->status = $state->status ?? 'in_progress';
        $this->sampleQuestion = $state->sample_question ?: 'Merhaba';
        $this->verificationResult = $state->sample_result_json ?? [];

        $this->loadBrandVoice();
        $this->successMessage = '';
        $this->errorMessage = '';
    }

    protected function loadBrandVoice()
    {
        $channel = SupportChannel::where('store_id', $this->selectedStoreId)->first();
        if ($channel) {
            $voiceService = app(BrandVoiceService::class);
            $user = auth()->user() ?? TenantContext::getSystemActor();
            $voice = $voiceService->getBrandVoice($channel, $user);

            $this->tone = $voice['tone'];
            $this->prompt_context = $voice['prompt_context'];
            $this->return_policy = $voice['return_policy'];
            $this->hitap = $voice['hitap'];
            $this->use_emoji = $voice['use_emoji'];
            $this->greeting = $voice['greeting'];
            $this->signature = $voice['signature'];
            $this->sample_response = $voice['sample_response'];
        } else {
            $this->resetBrandVoiceInputs();
        }
    }

    protected function resetBrandVoiceInputs()
    {
        $this->tone = 'kibar ve yardımsever';
        $this->prompt_context = 'Müşteri hizmetleri asistanısınız.';
        $this->return_policy = 'Genel e-ticaret iade kuralları geçerlidir.';
        $this->hitap = 'siz';
        $this->use_emoji = true;
        $this->greeting = 'Merhaba,';
        $this->signature = 'ZOLM Destek Ekibi';
        $this->sample_response = '';
    }

    public function saveBrandVoiceStep()
    {
        $this->enforceStoreAccess($this->selectedStoreId);

        $channel = SupportChannel::where('store_id', $this->selectedStoreId)->first();
        if (!$channel) {
            $this->errorMessage = 'Mağazaya ait aktif bir kanal bulunamadığı için marka sesi kaydedilemedi.';
            return false;
        }

        $voiceService = app(BrandVoiceService::class);
        $user = auth()->user() ?? TenantContext::getSystemActor();

        try {
            $voiceService->updateBrandVoice($channel, [
                'tone' => $this->tone,
                'prompt_context' => $this->prompt_context,
                'return_policy' => $this->return_policy,
                'hitap' => $this->hitap,
                'use_emoji' => $this->use_emoji,
                'greeting' => $this->greeting,
                'signature' => $this->signature,
                'sample_response' => $this->sample_response,
            ], $user);

            $this->successMessage = 'Marka sesi ayarları kaydedildi ve PII maskelemesi uygulandı.';
            return true;
        } catch (\InvalidArgumentException $e) {
            $this->errorMessage = $e->getMessage();
            return false;
        }
    }

    public function nextStep()
    {
        $this->enforceStoreAccess($this->selectedStoreId);

        // Adım bazlı özel kaydetme/kontrol işlemleri
        if ($this->currentStep === 3) {
            if (!$this->saveBrandVoiceStep()) {
                return; // Hata durumunda ilerlemeyi engelle
            }
        }

        if (!in_array($this->currentStep, $this->stepsCompleted)) {
            $this->stepsCompleted[] = $this->currentStep;
        }

        if ($this->currentStep < 6) {
            $this->currentStep++;
            $this->updateOnboardingState();
        }
    }

    public function verifySetup()
    {
        $this->enforceStoreAccess($this->selectedStoreId);
        $this->validate([
            'sampleQuestion' => ['required', 'string', 'min:1', 'max:500'],
        ]);

        $result = app(CustomerCareOnboardingVerificationService::class)
            ->verify((int) $this->selectedStoreId, (string) $this->sampleQuestion, auth()->user());
        $this->verificationResult = $result['result'] ?? [
            'success' => $result['success'] ?? false,
            'status' => ($result['success'] ?? false) ? 'draft' : 'failed',
        ];

        if ($result['success'] ?? false) {
            $seconds = (int) ($result['duration_seconds'] ?? 0);
            $this->successMessage = "Bağlantı ve ilk doğrulanmış AI taslağı başarıyla üretildi ({$seconds} saniye).";
            $this->errorMessage = '';
            return;
        }

        $this->errorMessage = (string) ($result['message'] ?? 'Kurulum doğrulanamadı.');
        $this->successMessage = '';
    }

    public function requestTechnicalSupport(): void
    {
        $this->enforceStoreAccess($this->selectedStoreId);
        $state = SupportOnboardingState::where('store_id', $this->selectedStoreId)->firstOrFail();
        $state->update(['support_requested_at' => now(), 'status' => 'needs_support']);
        \App\Models\SupportAgentAction::create([
            'conversation_id' => null,
            'user_id' => auth()->id() ?? TenantContext::getSystemActor()->id,
            'action' => 'onboarding_support_requested',
            'details_json' => [
                'store_id' => (int) $this->selectedStoreId,
                'support_bundle' => $state->support_bundle_json,
                'secrets_included' => false,
            ],
        ]);
        $this->status = 'needs_support';
        $this->successMessage = 'Teknik tanılama paketi destek ekibine iletildi.';
        $this->errorMessage = '';
    }

    public function prevStep()
    {
        $this->enforceStoreAccess($this->selectedStoreId);

        if ($this->currentStep > 1) {
            $this->currentStep--;
            $this->updateOnboardingState();
        }
    }

    public function completeOnboarding()
    {
        $this->enforceStoreAccess($this->selectedStoreId);

        try {
            app(\App\Services\Support\Security\SupportRbacService::class)
                ->enforcePermission(auth()->user() ?? TenantContext::getSystemActor(), (int) $this->selectedStoreId, 'toggle_automation');
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->errorMessage = $e->getMessage();
            return;
        }

        // Readiness check bypass edilmesin
        $readinessService = app(CustomerCarePilotReadinessService::class);
        $readiness = $readinessService->checkReadiness($this->selectedStoreId);

        $state = SupportOnboardingState::where('store_id', $this->selectedStoreId)->first();
        if ($this->recommendedMode === 'automatic'
            && $state?->connection_started_at
            && !$state?->first_verified_draft_at) {
            $this->errorMessage = 'Gerçek bağlantı ve ilk doğrulanmış AI taslağı kanıtlanmadan Otomatik Yanıt moduna geçilemez.';
            return;
        }
        if ($this->recommendedMode === 'automatic' && !$readiness['ready']) {
            $this->errorMessage = 'Pilot hazırlık kriterleri tam olarak karşılanmadan Otomatik Yanıt moduna geçilemez.';
            return;
        }
        if ($this->recommendedMode === 'automatic' && !$state?->first_verified_draft_at) {
            $this->errorMessage = 'Gerçek bağlantı ve ilk doğrulanmış AI taslağı kanıtlanmadan Otomatik Yanıt moduna geçilemez.';
            return;
        }

        $this->status = 'completed';
        if (!in_array(6, $this->stepsCompleted)) {
            $this->stepsCompleted[] = 6;
        }

        // Sync automation settings to channel config_json
        $channels = SupportChannel::where('store_id', $this->selectedStoreId)->get();
        foreach ($channels as $channel) {
            $config = $channel->config_json ?? [];
            $automation = $config['automation_settings'] ?? [];
            $automation['ai_mode'] = $this->recommendedMode;
            $automation['min_confidence'] = $automation['min_confidence'] ?? 80;
            $config['automation_settings'] = $automation;
            $channel->update(['config_json' => $config]);
        }

        $this->updateOnboardingState();
        $this->successMessage = 'Tebrikler! Kurulum sihirbazı başarıyla tamamlandı ve otomasyon ayarları senkronize edildi.';
    }

    protected function updateOnboardingState()
    {
        SupportOnboardingState::where('store_id', $this->selectedStoreId)->update([
            'current_step' => $this->currentStep,
            'steps_completed' => $this->stepsCompleted,
            'status' => $this->status,
            'recommended_mode' => $this->recommendedMode,
        ]);
    }

    public function render()
    {
        $myStores = $this->getMyStores();
        $channels = SupportChannel::where('store_id', $this->selectedStoreId)->get();

        $readinessService = app(CustomerCarePilotReadinessService::class);
        $readiness = $readinessService->checkReadiness($this->selectedStoreId);
        $onboardingState = SupportOnboardingState::where('store_id', $this->selectedStoreId)->first();

        return view('livewire.customer-care.onboarding', [
            'myStores' => $myStores,
            'channels' => $channels,
            'readiness' => $readiness,
            'onboardingState' => $onboardingState,
        ])->layout('layouts.app');
    }
}
