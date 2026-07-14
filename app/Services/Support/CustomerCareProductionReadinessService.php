<?php

namespace App\Services\Support;

use App\Models\SupportChannel;
use App\Models\SupportConnectorCertificationRun;
use App\Models\SupportSecurityFinding;
use App\Models\SupportLaunchPlan;
use App\Models\SupportProductionReadinessRun;
use App\Models\SupportProductionFreezeSnapshot;
use App\Models\SupportCommercialSubscription;
use App\Models\IntegrationConnection;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Auth\Access\AuthorizationException;
use App\Services\Support\Security\SupportRbacService;
use App\Services\Support\AI\CustomerCareGoldenEvalGateService;

class CustomerCareProductionReadinessService
{
    /**
     * Canlıya geçiş hazırlık durumunu denetler ve puanlar.
     */
    public function checkReadiness(int $storeId, ?User $user = null): SupportProductionReadinessRun
    {
        $user = $user ?? Auth::user() ?? TenantContext::getSystemActor();
        TenantContext::enforceStoreAccess($storeId, $user);

        $score = 100;
        $failedChecks = [];
        $checkResults = [];

        // 1. Connector Certification Check
        $activeChannels = SupportChannel::where('store_id', $storeId)->active()->get();
        $certificationMaxAgeDays = (int) config('customer-care.connector_certification_max_age_days', 7);
        if ($activeChannels->isEmpty()) {
            $score -= 25;
            $failedChecks[] = 'active_channel_missing';
        }
        $checkResults['active_channels'] = [
            'label' => 'Aktif iletişim kanalı',
            'status' => $activeChannels->isNotEmpty() ? 'passed' : 'failed',
            'detail' => $activeChannels->isNotEmpty()
                ? $activeChannels->count() . ' aktif kanal bulundu.'
                : 'Canlıya geçiş için en az bir aktif kanal gereklidir.',
        ];
        foreach ($activeChannels as $channel) {
            $latestRun = SupportConnectorCertificationRun::where('store_id', $storeId)
                ->where('channel_key', $channel->key)
                ->latest()
                ->first();

            $certifiedAt = $latestRun?->certified_at ?? $latestRun?->created_at;
            $isFresh = $certifiedAt && $certifiedAt->gte(now()->subDays($certificationMaxAgeDays));
            if (!$latestRun || $latestRun->status !== 'pass' || !$isFresh) {
                $score -= 25;
                $failedChecks[] = "certification_missing_failed_or_stale_{$channel->key}";
            }
            $checkResults["connector_certification_{$channel->key}"] = [
                'label' => "{$channel->name} connector sertifikasyonu",
                'status' => ($latestRun && $latestRun->status === 'pass' && $isFresh) ? 'passed' : 'failed',
                'detail' => !$latestRun
                    ? 'Sertifikasyon kaydı bulunamadı.'
                    : ((!$isFresh)
                        ? "Sertifikasyon {$certificationMaxAgeDays} günlük geçerlilik süresini aştı."
                        : "Son sertifikasyon durumu: {$latestRun->status}."),
            ];
        }

        // 2. Golden Evaluation Freshness Check
        $evalEvidence = app(CustomerCareGoldenEvalGateService::class)->evaluate($storeId, 'tr');
        if (!$evalEvidence['passed']) {
            $score -= 25;
            $failedChecks[] = 'golden_eval_' . $evalEvidence['code'];
        }
        $checkResults['golden_eval'] = [
            'label' => 'Golden değerlendirme',
            'status' => $evalEvidence['passed'] ? 'passed' : 'failed',
            'detail' => $evalEvidence['detail'],
        ];

        // 3. Security Findings Check (No unresolved critical findings allowed)
        $criticalFindingsCount = SupportSecurityFinding::where('store_id', $storeId)
            ->where('severity', 'critical')
            ->whereIn('status', ['open', 'acknowledged'])
            ->count();

        if ($criticalFindingsCount > 0) {
            $score -= 30;
            $failedChecks[] = 'critical_security_findings_exist';
        }
        $checkResults['critical_security_findings'] = [
            'label' => 'Kritik güvenlik bulguları',
            'status' => $criticalFindingsCount === 0 ? 'passed' : 'failed',
            'detail' => $criticalFindingsCount === 0
                ? 'Açık kritik güvenlik bulgusu yok.'
                : "{$criticalFindingsCount} açık kritik güvenlik bulgusu var.",
        ];

        // 4. Launch Plan Check
        $launchPlan = SupportLaunchPlan::where('store_id', $storeId)
            ->whereIn('status', ['approved', 'canary', 'completed'])
            ->whereNotNull('approved_at')
            ->first();

        if (!$launchPlan) {
            $score -= 20;
            $failedChecks[] = 'launch_plan_not_approved';
        }
        $checkResults['approved_launch_plan'] = [
            'label' => 'Onaylı lansman planı',
            'status' => $launchPlan ? 'passed' : 'failed',
            'detail' => $launchPlan
                ? "Onaylı plan: #{$launchPlan->id}."
                : 'Onaylanmış lansman planı bulunamadı.',
        ];

        // 5. Güncel operasyon kapısı: önceki plan onayına güvenmek yerine pilot,
        // provider, bütçe, entegrasyon secretı ve kuyruk sağlığını yeniden doğrula.
        $launchChecklist = app(CustomerCareLaunchService::class)->checkChecklist($storeId, $user);
        $failedOperationalChecks = collect($launchChecklist['checks'] ?? [])
            ->filter(fn (array $check): bool => ($check['status'] ?? 'failed') !== 'passed')
            ->pluck('label')
            ->filter()
            ->values();
        if (!($launchChecklist['allowed'] ?? false)) {
            $score -= 25;
            $failedChecks[] = 'current_operational_checklist_failed';
        }
        $checkResults['current_operational_checklist'] = [
            'label' => 'Güncel operasyon hazırlık kapısı',
            'status' => ($launchChecklist['allowed'] ?? false) ? 'passed' : 'failed',
            'detail' => ($launchChecklist['allowed'] ?? false)
                ? 'Pilot, provider, bütçe, politika, secret ve kuyruk kontrolleri güncel olarak geçti.'
                : 'Başarısız kontroller: ' . ($failedOperationalChecks->isNotEmpty()
                    ? $failedOperationalChecks->implode(', ')
                    : 'operasyon kanıtı doğrulanamadı.'),
        ];

        $score = max(0, $score);
        $status = ($score >= 90 && empty($failedChecks)) ? 'ready' : 'not_ready';

        return SupportProductionReadinessRun::create([
            'store_id'        => $storeId,
            'run_by'          => $user?->id,
            'readiness_score' => $score,
            'status'          => $status,
            'check_results_json' => $checkResults,
            'failed_checks_json' => $failedChecks,
        ]);
    }

    /**
     * Konfigürasyon anlık görüntüsünü (Freeze Snapshot) alır ve şifreli kaydeder.
     */
    public function freezeConfiguration(int $storeId, int $runId, ?User $user = null): SupportProductionFreezeSnapshot
    {
        $user = $user ?? Auth::user() ?? TenantContext::getSystemActor();
        TenantContext::enforceStoreAccess($storeId, $user);
        app(SupportRbacService::class)->enforcePermission($user, $storeId, 'force_circuit_breaker');

        $run = SupportProductionReadinessRun::where('store_id', $storeId)->findOrFail($runId);
        if ($run->status !== 'ready') {
            throw new \RuntimeException('Hazır olmayan bir denetim kaydından freeze snapshot oluşturulamaz.');
        }
        $this->assertCurrentReadinessEvidence($run);

        $channels = SupportChannel::where('store_id', $storeId)->get()->map(function ($c) {
            return [
                'key'        => $c->key,
                'is_enabled' => $c->is_enabled,
                'status'     => $c->status,
            ];
        })->toArray();

        $subscription = SupportCommercialSubscription::where('store_id', $storeId)->first();
        $planName = $subscription?->plan?->name ?? 'Free / Trial';

        $connections = IntegrationConnection::where('store_id', $storeId)->get()->map(function ($c) {
            return [
                'provider' => $c->provider,
                'status'   => $c->status,
                // Webhook secret ve app secret'ları PII/Secret sızıntısı olmaması için kesinlikle redacted yapıyoruz!
                'webhook_secret' => $c->webhook_secret ? '[REDACTED]' : null,
                'app_secret'     => $c->app_secret ? '[REDACTED]' : null,
            ];
        })->toArray();

        $snapshotData = [
            'store_id'     => $storeId,
            'plan'         => $planName,
            'channels'     => $channels,
            'connections'  => $connections,
            'frozen_at'    => now()->toIso8601String(),
        ];

        return SupportProductionFreezeSnapshot::firstOrCreate(
            ['store_id' => $storeId, 'run_id' => $runId],
            ['snapshot_data_encrypted' => json_encode($snapshotData)] // Model automatic encrypted cast protects this in DB
        );
    }

    /**
     * Freeze snapshot onaylama işlemi (Governance kuralı: self-approval engellenmiştir).
     */
    public function approveFreeze(int $snapshotId, int $approverId): SupportProductionFreezeSnapshot
    {
        $approver = User::whereKey($approverId)->where('is_active', true)->firstOrFail();

        return \Illuminate\Support\Facades\DB::transaction(function () use ($snapshotId, $approverId, $approver) {
            $snapshot = SupportProductionFreezeSnapshot::with('run')
                ->lockForUpdate()
                ->findOrFail($snapshotId);
            $run = $snapshot->run;

            CustomerCareOrganizationContext::enforceStoreOrganizationAccess((int) $snapshot->store_id, $approver);
            app(SupportRbacService::class)->enforcePermission($approver, (int) $snapshot->store_id, 'approve_risk_action');

            if (!$run || $run->status !== 'ready') {
                throw new \RuntimeException('Hazır olmayan bir denetimin freeze snapshot kaydı onaylanamaz.');
            }

            // Onay append-only/immutable davranır; sonraki çağrılar onaylayanı değiştiremez.
            if ($snapshot->approved_at) {
                return $snapshot;
            }

            $this->assertCurrentReadinessEvidence($run);

            // Self-approval check: Snapshot'ı oluşturan kullanıcı onaylayamaz
            if ((int) $run->run_by === $approverId) {
                throw new AuthorizationException("Kendi başlattığınız canlıya geçiş talebini onaylayamazsınız (Self-Approval Engeli).");
            }

            $snapshot->update([
                'approved_by' => $approverId,
                'approved_at' => now(),
            ]);

            return $snapshot->fresh();
        });
    }

    /**
     * Geri dönme tatbikatı (Rollback Drill) analizi. Hiçbir veriyi mutasyona uğratmaz (dry-run).
     */
    public function runRollbackDrill(int $storeId, ?User $user = null): array
    {
        $user = $user ?? Auth::user() ?? TenantContext::getSystemActor();
        TenantContext::enforceStoreAccess($storeId, $user);

        // Bekleyen asenkron veya outbox kuyruğundaki mesaj sayısını sayalım
        $pendingDispatches = \App\Models\SupportDispatch::whereHas('conversation', function ($query) use ($storeId) {
                $query->where('store_id', $storeId);
            })
            ->whereIn('status', ['pending', 'sending'])
            ->count();

        // Otomasyon / circuit breaker durumunu alalım
        $manualOverrideActive = (bool) Cache::get("circuit_breaker_forced_open_{$storeId}", false);
        $circuitBreakerActive = $manualOverrideActive || config('customer-care.circuit_breaker_enabled', false);

        return [
            'store_id'                => $storeId,
            'rollback_path'           => 'force_manual_and_cancel_ai_outbox',
            'pending_dispatches'      => $pendingDispatches,
            'automation_circuit_breaker_active' => $circuitBreakerActive,
            'manual_override_active'  => $manualOverrideActive,
            'drill_timestamp'         => now()->toIso8601String(),
            'status'                  => 'analysis_completed',
        ];
    }

    private function assertCurrentReadinessEvidence(SupportProductionReadinessRun $run): void
    {
        $maxAgeMinutes = max(1, (int) config('customer-care.production_readiness_max_age_minutes', 60));
        if (!$run->created_at || $run->created_at->lt(now()->subMinutes($maxAgeMinutes))) {
            throw new \RuntimeException("Üretim hazırlık kanıtı {$maxAgeMinutes} dakikalık geçerlilik süresini aştı.");
        }

        $latestRunId = SupportProductionReadinessRun::where('store_id', $run->store_id)
            ->latest('id')
            ->value('id');
        if ((int) $latestRunId !== (int) $run->id) {
            throw new \RuntimeException('Freeze işlemi yalnızca mağazanın en güncel üretim hazırlık denetimiyle yapılabilir.');
        }
    }
}
