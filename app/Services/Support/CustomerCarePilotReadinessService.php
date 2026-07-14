<?php

namespace App\Services\Support;

use App\Models\SupportAiRun;
use App\Models\SupportChannel;
use App\Models\SupportDispatch;
use App\Models\SupportOnboardingState;
use App\Models\User;
use App\Services\Support\AI\CustomerCareGoldenEvalGateService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;

class CustomerCarePilotReadinessService
{
    /**
     * Mağazanın pilot readiness durumunu analiz eder.
     */
    public function checkReadiness(int $storeId, ?User $actor = null): array
    {
        $actor = $actor ?? Auth::user() ?? TenantContext::getSystemActor();
        TenantContext::enforceStoreAccess($storeId, $actor);

        $checks = [];
        $isReady = true;

        // 1. Master Flag
        $masterEnabled = (bool) Config::get('customer-care.enabled', false);
        $checks['master_enabled'] = [
            'status' => $masterEnabled ? 'passed' : 'failed',
            'label' => 'Müşteri İletişim Merkezi Master Switch',
            'detail' => $masterEnabled ? 'Aktif' : 'Pasif'
        ];
        if (!$masterEnabled) $isReady = false;

        // 2. Auto Reply Flag
        $autoReplyEnabled = (bool) Config::get('customer-care.auto_reply_enabled', false);
        $checks['auto_reply_enabled'] = [
            'status' => $autoReplyEnabled ? 'passed' : 'warning',
            'label' => 'Otomatik Yanıt Özelliği (Auto-Reply)',
            'detail' => $autoReplyEnabled ? 'Aktif' : 'Pasif'
        ];

        // 3. Pilot Store Allowlist
        $allowlist = Config::get('customer-care.pilot_store_allowlist', []);
        $allowlist = array_map(function($val) {
            return (int) trim($val);
        }, $allowlist);

        $inAllowlist = in_array((int)$storeId, $allowlist, true);
        $checks['in_allowlist'] = [
            'status' => $inAllowlist ? 'passed' : 'failed',
            'label' => 'Pilot Mağaza İzin Listesi (Allowlist)',
            'detail' => $inAllowlist ? 'İzinli' : 'İzin verilmemiş'
        ];
        if (!$inAllowlist) $isReady = false;

        // 4. Channel Enabled
        $channels = SupportChannel::where('store_id', $storeId)->get();
        $activeChannelsCount = $channels->where('is_enabled', true)->where('status', 'active')->count();
        $checks['channels_status'] = [
            'status' => $activeChannelsCount > 0 ? 'passed' : 'failed',
            'label' => 'Aktif İletişim Kanalları',
            'detail' => "Toplam {$channels->count()} kanaldan {$activeChannelsCount} tanesi aktif."
        ];
        if ($activeChannelsCount === 0) $isReady = false;

        // 5. AI Provider Health / Key
        $aiHealthy = app(CustomerCareAiProviderHealthService::class)->isProviderHealthy('Gemini');
        $checks['ai_provider'] = [
            'status' => $aiHealthy ? 'passed' : 'failed',
            'label' => 'AI Servis Bağlantısı (Gemini)',
            'detail' => $aiHealthy ? 'Gemini sağlayıcı yapılandırması kullanılabilir.' : 'Gemini sağlayıcı yapılandırması kullanılamıyor.'
        ];
        if (!$aiHealthy) $isReady = false;

        // 6. System Actor Varlığı
        try {
            $systemActor = TenantContext::getSystemActor();
        } catch (\Throwable) {
            $systemActor = null;
        }
        $checks['system_actor'] = [
            'status' => $systemActor ? 'passed' : 'failed',
            'label' => 'Sistem Aktörü (System Actor)',
            'detail' => $systemActor ? "Kullanıcı bulundu: {$systemActor->name}" : 'Kullanıcı bulunamadı (Fail-Closed tetiklenecek)'
        ];
        if (!$systemActor) $isReady = false;

        // 7. Queue Backlog
        $backlogCount = SupportDispatch::whereIn('status', ['pending', 'failed'])
            ->whereHas('conversation', function ($q) use ($storeId) {
                $q->where('store_id', $storeId);
            })
            ->count();
        $maxBacklog = max(1, (int) Config::get('customer-care.pilot_max_backlog', 10));
        $checks['outbox_backlog'] = [
            'status' => $backlogCount < $maxBacklog ? 'passed' : 'failed',
            'label' => 'Outbox Bekleyen Mesaj Kuyruğu (Backlog)',
            'detail' => "Kuyrukta bekleyen {$backlogCount} mesaj var (üst sınır: {$maxBacklog})."
        ];
        if ($backlogCount >= $maxBacklog) $isReady = false;

        // 8. Golden Eval Skoru
        $evalEvidence = app(CustomerCareGoldenEvalGateService::class)->evaluate($storeId, 'tr');
        $evalRun = $evalEvidence['run'];
        $evalDetail = match ($evalEvidence['code']) {
            'missing' => 'Henüz değerlendirme yapılmadı (kanıt bulunamadı).',
            'stale' => 'Eski Sonuç: ' . $evalEvidence['detail'],
            'score_failed' => 'Başarısız: ' . $evalEvidence['detail'],
            default => $evalEvidence['detail'],
        };
        $checks['golden_eval'] = [
            'status' => $evalEvidence['passed'] ? 'passed' : 'failed',
            'label' => 'Golden Dataset Değerlendirme Eşiği',
            'detail' => $evalEvidence['passed']
                ? $evalEvidence['detail'] . ' Tarih: ' . $evalRun->finished_at->format('Y-m-d H:i')
                : $evalDetail,
        ];
        if (!$evalEvidence['passed']) $isReady = false;

        // 9. Shadow Match Ortalaması
        $shadowQuery = SupportAiRun::where('store_id', $storeId)->whereNotNull('shadow_match_score');
        $shadowSampleCount = (clone $shadowQuery)->count();
        $avgShadowScore = (clone $shadowQuery)->avg('shadow_match_score');
        $shadowMinSamples = max(1, (int) Config::get('customer-care.shadow_min_samples', 20));
        $shadowMinAverage = max(0, min(100, (int) Config::get('customer-care.shadow_min_average', 80)));
        $shadowReady = $shadowSampleCount >= $shadowMinSamples
            && $avgShadowScore !== null
            && (float) $avgShadowScore >= $shadowMinAverage;
        $checks['shadow_match'] = [
            'status' => $shadowReady ? 'passed' : 'failed',
            'label' => 'Shadow Mode Benzerlik Ortalaması',
            'detail' => $avgShadowScore !== null
                ? '%' . round($avgShadowScore, 1) . " / {$shadowSampleCount} örnek (hedef: ≥%{$shadowMinAverage}, ≥{$shadowMinSamples} örnek)"
                : 'Henüz karşılaştırma yapılmadı'
        ];
        if (!$shadowReady) $isReady = false;

        // 9.5 Türkçe kalite kapısı (birincil dil)
        $languageReady = app(\App\Services\Support\AI\CustomerCareLanguageService::class)
            ->hasPassedAutomationGate($storeId, 'tr');
        $checks['language_quality_tr'] = [
            'status' => $languageReady ? 'passed' : 'failed',
            'label' => 'Türkçe Dil Kalite Kapısı',
            'detail' => $languageReady ? 'Golden dil seti güncel ve başarılı' : 'En az 20 örnek, %80 kalite, %95 kaynak doğruluğu ve sıfır kritik hata gerekli',
        ];
        if (!$languageReady) $isReady = false;

        // 9.6 Onboarding doğrulaması: gerçek kanal/capability, katalog ve ilk
        // doğrulanmış taslak kanıtı olmadan pilot hazır sayılamaz.
        $onboardingMaxAgeDays = max(1, (int) Config::get('customer-care.onboarding_verification_max_age_days', 30));
        $onboardingState = SupportOnboardingState::where('store_id', $storeId)->first();
        $onboardingVerified = $onboardingState
            && $onboardingState->first_verified_draft_at
            && $onboardingState->catalog_verified_at
            && $onboardingState->last_verified_at
            && $onboardingState->last_verified_at->gte(now()->subDays($onboardingMaxAgeDays))
            && (bool) data_get($onboardingState->sample_result_json, 'success', false);
        $checks['onboarding_verification'] = [
            'status' => $onboardingVerified ? 'passed' : 'failed',
            'label' => 'Doğrulanmış Onboarding Kanıtı',
            'detail' => $onboardingVerified
                ? "Kanal, katalog ve ilk AI taslağı son {$onboardingMaxAgeDays} gün içinde doğrulandı."
                : 'Güncel kanal/capability, katalog ve ilk doğrulanmış AI taslağı kanıtı eksik.',
        ];
        if (!$onboardingVerified) $isReady = false;

        // 10. Policy Engine Aktifliği
        $policyEngineActive = class_exists(\App\Services\Support\Policy\SupportPolicyEngine::class);
        $checks['policy_engine'] = [
            'status' => $policyEngineActive ? 'passed' : 'failed',
            'label' => 'Kanal Politika Motoru (Policy Engine)',
            'detail' => $policyEngineActive ? 'Aktif' : 'Pasif'
        ];
        if (!$policyEngineActive) $isReady = false;

        // 11. Son Dispatch Hataları
        $latestErrors = SupportDispatch::where('status', 'failed')
            ->whereHas('conversation', function ($q) use ($storeId) {
                $q->where('store_id', $storeId);
            })
            ->latest()
            ->limit(5)
            ->pluck('last_error')
            ->filter()
            ->toArray();

        return [
            'store_id' => $storeId,
            'ready' => $isReady,
            'checks' => $checks,
            'latest_errors' => $latestErrors
        ];
    }
}
