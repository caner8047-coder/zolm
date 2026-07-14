<?php

namespace App\Livewire\CustomerCare;

use App\Models\MarketplaceStore;
use App\Models\SupportChannel;
use App\Models\SupportAgentAction;
use App\Services\Support\TenantContext;
use App\Services\Support\BrandVoiceService;
use App\Services\Support\CustomerCarePilotReadinessService;
use App\Services\Support\CustomerCarePilotMonitorService;
use App\Services\Support\CustomerCareChannelProvisioningService;
use App\Services\Support\CustomerCareOrganizationContext;
use App\Services\Support\Policy\SupportPolicyEngine;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Component;

class Settings extends Component
{
    public $selectedStoreId;
    public $selectedChannelId;

    // Brand Voice Inputs
    public $tone;
    public $prompt_context;
    public $return_policy;
    public $hitap;
    public $use_emoji = true;
    public $greeting;
    public $signature;
    public $sample_response;
    public $responseLength = 'medium';
    public $emojiLevel = 'normal';
    public $preferredExpressions = '';
    public $forbiddenExpressions = '';
    public $complaintTone = '';
    public $salesTone = '';
    public $crisisTone = '';
    public $languageRulesJson = '';

    // Automation settings
    public bool $channelEnabled = false;
    public $aiMode = 'manual';
    public $minConfidence = 80;
    public $intentModesJson = '';

    public $successMessage = '';
    public $errorMessage = '';

    public function mount()
    {
        // Feature flag protection
        if (!config('customer-care.settings_enabled', false)) {
            abort(404, 'Müşteri hizmetleri ayarlar modülü aktif değil.');
        }

        $myStores = $this->getMyStores();
        if ($myStores->isNotEmpty() && !$this->selectedStoreId) {
            $this->selectedStoreId = $myStores->first()->id;
        }
        $this->enforceSelectedStoreAccess($myStores->pluck('id')->map(fn ($id) => (int) $id)->all());

        // selectedChannelId dışarıdan geldiyse loadChannel ezmesin
        if (!$this->selectedChannelId) {
            $this->loadChannel();
        } else {
            // Sadece ayarları yükle, kanal seçimini koru
            $this->loadSettings();
        }
    }

    public function updatedSelectedStoreId()
    {
        $this->enforceSelectedStoreAccess();

        $this->selectedChannelId = null;
        $this->loadChannel();
    }

    public function updatedSelectedChannelId()
    {
        $this->loadSettings();
    }

    protected function getMyStores()
    {
        $user = auth()->user() ?? TenantContext::getSystemActor();

        return CustomerCareOrganizationContext::getAccessibleStores($user)->get();
    }

    protected function loadChannel()
    {
        if ($this->selectedStoreId) {
            $channel = SupportChannel::where('store_id', $this->selectedStoreId)->first();
            if ($channel) {
                $this->selectedChannelId = $channel->id;
            }
        }
        $this->loadSettings();
    }

    protected function loadSettings()
    {
        // Mesajları sadece açıkça resetlemek isteniyorsa sıfırla; kayıt sonrası değil
        if (!$this->selectedChannelId) {
            $this->resetInputs();
            return;
        }

        $channel = SupportChannel::find($this->selectedChannelId);
        if (!$channel) {
            $this->resetInputs();
            return;
        }

        // Verify store access
        $myStoreIds = $this->getMyStores()->pluck('id')->toArray();
        if (!in_array($channel->store_id, $myStoreIds)) {
            abort(403, 'Bu kanal ayarlarına erişim yetkiniz yok.');
        }
        if ((int) $this->selectedStoreId !== (int) $channel->store_id) {
            $this->selectedStoreId = (int) $channel->store_id;
        }

        $config = $channel->config_json ?? [];
        $bv = $config['brand_voice'] ?? [];
        $auto = $config['automation_settings'] ?? [];

        $this->channelEnabled = (bool) $channel->is_enabled;
        $this->tone = $bv['tone'] ?? 'kibar ve yardımsever';
        $this->prompt_context = $bv['prompt_context'] ?? 'Müşteri hizmetleri asistanısınız.';
        $this->return_policy = $bv['return_policy'] ?? 'Genel e-ticaret iade kuralları geçerlidir.';
        $this->hitap = $bv['hitap'] ?? 'siz';
        $this->use_emoji = filter_var($bv['use_emoji'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $this->greeting = $bv['greeting'] ?? 'Merhaba,';
        $this->signature = $bv['signature'] ?? 'ZOLM Destek Ekibi';
        $this->sample_response = $bv['sample_response'] ?? '';
        $this->responseLength = $bv['response_length'] ?? 'medium';
        $this->emojiLevel = $bv['emoji_level'] ?? 'normal';
        $this->preferredExpressions = implode(', ', $bv['preferred_expressions'] ?? []);
        $this->forbiddenExpressions = implode(', ', $bv['forbidden_expressions'] ?? []);
        $this->complaintTone = $bv['complaint_tone'] ?? 'sakin, empatik ve çözüm odaklı';
        $this->salesTone = $bv['sales_tone'] ?? 'bilgilendirici ve baskısız';
        $this->crisisTone = $bv['crisis_tone'] ?? 'net, sorumluluk sahibi ve insana devir odaklı';
        $this->languageRulesJson = json_encode($bv['language_rules'] ?? ['tr' => ['forbidden_expressions' => [], 'examples' => []]], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $this->aiMode = $auto['ai_mode'] ?? 'manual';
        $this->minConfidence = $auto['min_confidence'] ?? 80;
        $this->intentModesJson = json_encode(
            $auto['intent_modes'] ?? $this->defaultIntentModes((string) $this->aiMode),
            JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
        );
    }

    protected function resetInputs()
    {
        $this->tone = '';
        $this->prompt_context = '';
        $this->return_policy = '';
        $this->hitap = 'siz';
        $this->use_emoji = true;
        $this->greeting = '';
        $this->signature = '';
        $this->sample_response = '';
        $this->responseLength = 'medium';
        $this->emojiLevel = 'normal';
        $this->preferredExpressions = '';
        $this->forbiddenExpressions = '';
        $this->complaintTone = '';
        $this->salesTone = '';
        $this->crisisTone = '';
        $this->languageRulesJson = '';
        $this->channelEnabled = false;
        $this->aiMode = 'manual';
        $this->minConfidence = 80;
        $this->intentModesJson = '';
    }

    public function saveSettings()
    {
        $this->successMessage = '';
        $this->errorMessage = '';

        if (!$this->selectedChannelId) {
            $this->errorMessage = 'Kaydedilecek kanal bulunamadı.';
            return;
        }

        $channel = SupportChannel::find($this->selectedChannelId);
        if (!$channel) {
            $this->errorMessage = 'Kanal bulunamadı.';
            return;
        }

        $myStoreIds = $this->getMyStores()->pluck('id')->toArray();
        if (!in_array($channel->store_id, $myStoreIds)) {
            abort(403);
        }
        if ((int) $this->selectedStoreId !== (int) $channel->store_id) {
            $this->errorMessage = 'Seçili mağaza ile kanal eşleşmiyor. Kanal ayarları yeniden yüklendi.';
            $this->selectedStoreId = (int) $channel->store_id;
            return;
        }

        $storeId = (int) $channel->store_id;
        $actor = auth()->user() ?? TenantContext::getSystemActor();

        try {
            app(\App\Services\Support\Security\SupportRbacService::class)
                ->enforcePermission($actor, $storeId, 'toggle_automation');
        } catch (AuthorizationException $e) {
            $this->errorMessage = $e->getMessage();
            return;
        }

        $targetChannelEnabled = filter_var($this->channelEnabled, FILTER_VALIDATE_BOOLEAN);

        // Automatic Mode Validations
        if ($this->aiMode === 'automatic') {
            // 1. Allowlist
            $allowlist = config('customer-care.pilot_store_allowlist', []);
            $allowlist = array_map('intval', $allowlist);
            if (!in_array($storeId, $allowlist, true)) {
                $this->errorMessage = 'Otomatik mod etkinleştirilemez: Mağaza pilot izin listesinde değil.';
                return;
            }

            // 2. Golden Eval
            $readinessService = app(CustomerCarePilotReadinessService::class);
            $readiness = $readinessService->checkReadiness($storeId, $actor);
            $goldenStatus = $readiness['checks']['golden_eval']['status'] ?? 'failed';
            if ($goldenStatus !== 'passed') {
                $this->errorMessage = 'Otomatik mod etkinleştirilemez: Golden dataset değerlendirmesi başarısız veya güncel değil.';
                return;
            }
            if (!($readiness['ready'] ?? false)) {
                $failedLabels = collect($readiness['checks'] ?? [])
                    ->filter(fn (array $check): bool => ($check['status'] ?? 'failed') === 'failed')
                    ->pluck('label')
                    ->filter()
                    ->implode(', ');
                $this->errorMessage = 'Otomatik mod etkinleştirilemez: Pilot hazırlık kapısı tamamlanmadı'
                    . ($failedLabels !== '' ? " ({$failedLabels})." : '.');
                return;
            }

            // 3. Circuit Breaker
            $monitor = app(CustomerCarePilotMonitorService::class);
            $metrics = $monitor->getStoreMetrics($storeId, $actor);
            if (($metrics['circuit_breaker_status'] ?? 'unknown') !== 'closed') {
                $this->errorMessage = 'Otomatik mod etkinleştirilemez: Devre kesici kapalı ve doğrulanmış durumda değil.';
                return;
            }

            // 4. Auto Reply Global Flag
            if (!config('customer-care.auto_reply_enabled', false)) {
                $this->errorMessage = 'Otomatik mod etkinleştirilemez: Global otomatik yanıt özelliği devre dışı.';
                return;
            }

            // 5. Channel Enabled
            if (!$targetChannelEnabled) {
                $this->errorMessage = 'Otomatik mod etkinleştirilemez: Kanal devre dışı bırakılmış.';
                return;
            }

            // 6. Policy Engine Self-test
            $policyEngine = app(SupportPolicyEngine::class);
            $selfTest = $policyEngine->validate('Merhaba', $channel->key);
            if (!($selfTest['allowed'] ?? false)) {
                $this->errorMessage = 'Otomatik mod etkinleştirilemez: Politika motoru self-testi başarısız.';
                return;
            }

            // 7. Threshold
            if ((int)$this->minConfidence < 80) {
                $this->errorMessage = 'Otomatik mod etkinleştirilemez: Güven eşiği 80 altında olamaz.';
                return;
            }


            // 8. Gerçek bağlantı + ilk doğrulanmış taslak kanıtı
            $onboarding = \App\Models\SupportOnboardingState::where('store_id', $storeId)->first();
            if (!$onboarding?->first_verified_draft_at) {
                $this->errorMessage = 'Otomatik mod etkinleştirilemez: İlk doğrulanmış AI taslağı ve bağlantı kanıtı eksik.';
                return;
            }
        }

        try {
            $intentModes = trim((string) $this->intentModesJson) === ''
                ? $this->defaultIntentModes((string) $this->aiMode)
                : json_decode((string) $this->intentModesJson, true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($intentModes)) {
                throw new \InvalidArgumentException('Intent modları geçerli bir JSON nesnesi olmalıdır.');
            }
            $allowedIntents = ['general', 'product', 'order_status', 'return_or_cancel', 'health_or_legal'];
            foreach ($intentModes as $intent => $mode) {
                if (!in_array($intent, $allowedIntents, true) || !in_array($mode, ['manual', 'copilot', 'automatic'], true)) {
                    throw new \InvalidArgumentException('Intent modları geçerli intent ve manual/copilot/automatic değerlerinden oluşmalıdır.');
                }
                if ($this->modeRank($mode) < $this->modeRank($this->aiMode)) {
                    throw new \InvalidArgumentException("{$intent} intent modu kanal modundan daha geniş olamaz.");
                }
            }

            // Save Brand Voice
            $bvService = app(BrandVoiceService::class);
            $bvService->updateBrandVoice($channel, [
                'tone' => $this->tone,
                'prompt_context' => $this->prompt_context,
                'return_policy' => $this->return_policy,
                'hitap' => $this->hitap,
                'use_emoji' => $this->use_emoji,
                'greeting' => $this->greeting,
                'signature' => $this->signature,
                'sample_response' => $this->sample_response,
                'response_length' => $this->responseLength,
                'emoji_level' => $this->emojiLevel,
                'preferred_expressions' => $this->preferredExpressions,
                'forbidden_expressions' => $this->forbiddenExpressions,
                'complaint_tone' => $this->complaintTone,
                'sales_tone' => $this->salesTone,
                'crisis_tone' => $this->crisisTone,
                'language_rules' => $this->languageRulesJson,
            ]);

            // Save Automation Settings
            $config = $channel->config_json ?? [];
            $oldAutomationSettings = $config['automation_settings'] ?? [];
            $config['automation_settings'] = [
                'ai_mode' => $this->aiMode,
                'min_confidence' => (int)$this->minConfidence,
                'auto_reply' => ($config['automation_settings']['auto_reply'] ?? false),
                'intent_modes' => $intentModes,
            ];
            $channel->update([
                'is_enabled' => $targetChannelEnabled,
                'config_json' => $config,
            ]);

            // Audit log
            SupportAgentAction::create([
                'conversation_id' => null,
                'message_id' => null,
                'user_id' => auth()->id() ?? TenantContext::getSystemActor()->id,
                'action' => 'channel_settings_updated',
                'details_json' => [
                    'channel_id' => $channel->id,
                    'store_id' => $storeId,
                    'is_enabled' => $targetChannelEnabled,
                    'ai_mode' => $this->aiMode,
                    'min_confidence' => (int)$this->minConfidence,
                    'old_settings' => $oldAutomationSettings,
                    'reason' => 'customer_care_settings_save',
                ],
            ]);

            $this->successMessage = 'Ayarlar başarıyla kaydedildi.';
            $this->loadSettings();

        } catch (\InvalidArgumentException $e) {
            $this->errorMessage = $e->getMessage();
        } catch (\Throwable $e) {
            $this->errorMessage = 'Bir hata oluştu: ' . $e->getMessage();
        }
    }

    private function modeRank(string $mode): int
    {
        return ['automatic' => 0, 'copilot' => 1, 'manual' => 2][$mode] ?? 2;
    }

    private function defaultIntentModes(string $channelMode): array
    {
        return match ($channelMode) {
            'automatic' => [
                'general' => 'automatic',
                'product' => 'automatic',
                'order_status' => 'copilot',
                'return_or_cancel' => 'copilot',
                'health_or_legal' => 'manual',
            ],
            'copilot' => [
                'general' => 'copilot',
                'product' => 'copilot',
                'order_status' => 'copilot',
                'return_or_cancel' => 'manual',
                'health_or_legal' => 'manual',
            ],
            default => [
                'general' => 'manual',
                'product' => 'manual',
                'order_status' => 'manual',
                'return_or_cancel' => 'manual',
                'health_or_legal' => 'manual',
            ],
        };
    }

    /**
     * Mevcut mağaza entegrasyonlarından SupportChannel kayıtlarını oluştur.
     * Tenant guard: seçili mağaza kullanıcıya ait olmalı.
     */
    public function provisionChannels(): void
    {
        $this->successMessage = '';
        $this->errorMessage   = '';

        if (!$this->selectedStoreId) {
            $this->errorMessage = 'Önce bir mağaza seçin.';
            return;
        }

        // Tenant guard
        $myStoreIds = $this->getMyStores()->pluck('id')->toArray();
        if (!in_array((int)$this->selectedStoreId, $myStoreIds)) {
            abort(403);
        }

        try {
            $service = app(CustomerCareChannelProvisioningService::class);
            $result  = $service->provisionForStore((int)$this->selectedStoreId, auth()->user());

            $createdCount = count($result['created']);
            if ($createdCount > 0) {
                $this->successMessage = $createdCount . ' kanal başarıyla oluşturuldu.';
                // İlk oluşturulan kanalı seç
                $this->selectedChannelId = $result['created'][0]->id;
                $this->loadSettings();
            } else {
                $this->successMessage = 'Tüm kanallar zaten mevcut.';
            }
        } catch (AuthorizationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->errorMessage = 'Kanal oluşturma hatası: ' . $e->getMessage();
        }
    }

    public function render()
    {
        $myStores = $this->getMyStores();
        $this->enforceSelectedStoreAccess($myStores->pluck('id')->map(fn ($id) => (int) $id)->all());
        $channels = [];
        $readiness = null;
        $usageData = [];

        if ($this->selectedStoreId) {
            $channels = SupportChannel::where('store_id', $this->selectedStoreId)->get();
            $readiness = app(CustomerCarePilotReadinessService::class)->checkReadiness($this->selectedStoreId);

            $usageService = app(\App\Services\Support\CustomerCareUsageService::class);
            $metrics = ['ai_drafts', 'auto_replies', 'connected_channels', 'knowledge_suggestions'];
            foreach ($metrics as $m) {
                $usageData[$m] = $usageService->checkLimit($this->selectedStoreId, $m);
            }
        }

        return view('livewire.customer-care.settings', [
            'myStores'              => $myStores,
            'channels'              => $channels,
            'readiness'             => $readiness,
            'usageData'             => $usageData,
            'availableToProvision'  => $this->selectedStoreId
                ? app(CustomerCareChannelProvisioningService::class)
                    ->availableToProvision((int)$this->selectedStoreId, auth()->user())
                : collect(),
        ])->layout('layouts.app');
    }

    private function enforceSelectedStoreAccess(?array $accessibleStoreIds = null): void
    {
        if (!$this->selectedStoreId) {
            return;
        }

        $accessibleStoreIds ??= $this->getMyStores()->pluck('id')->map(fn ($id) => (int) $id)->all();
        if (!in_array((int) $this->selectedStoreId, $accessibleStoreIds, true)) {
            abort(403, 'Bu mağazaya erişim yetkiniz yok.');
        }
    }
}
