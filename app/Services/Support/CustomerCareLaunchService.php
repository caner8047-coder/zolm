<?php

namespace App\Services\Support;

use App\Models\SupportLaunchPlan;
use App\Models\SupportLaunchPlanStep;
use App\Models\SupportLaunchEvent;
use App\Models\SupportDispatch;
use App\Models\SupportChannel;
use App\Models\SupportConversation;
use App\Models\User;
use App\Services\Support\CustomerCarePilotReadinessService;
use App\Services\Support\CustomerCarePilotMonitorService;
use App\Services\Support\CustomerCareAiProviderHealthService;
use App\Services\Support\AI\CustomerCareGoldenEvalGateService;
use App\Services\Support\Reliability\CustomerCareQueueHealthService;
use App\Services\Support\Security\SupportRbacService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class CustomerCareLaunchService
{
    public function checkChecklist(int $storeId, ?User $actor = null): array
    {
        $actor = $actor ?? Auth::user() ?? TenantContext::getSystemActor();
        TenantContext::enforceStoreAccess($storeId, $actor);

        $checks = [];
        $allPassed = true;

        // 1. Pilot Readiness Check
        $readinessService = app(CustomerCarePilotReadinessService::class);
        $readiness = $readinessService->checkReadiness($storeId, $actor);
        $pilotReadinessPassed = ($readiness['ready'] ?? false) === true;
        $failedPilotChecks = collect($readiness['checks'] ?? [])
            ->filter(fn (array $check) => ($check['status'] ?? 'failed') === 'failed')
            ->pluck('label')
            ->filter()
            ->values();
        $checks['pilot_readiness'] = [
            'status' => $pilotReadinessPassed ? 'passed' : 'failed',
            'label' => 'Pilot Hazırlık Analizi',
            'detail' => $pilotReadinessPassed
                ? 'Tüm hazırlık kriterleri sağlandı.'
                : 'Başarısız kontroller: ' . ($failedPilotChecks->isNotEmpty()
                    ? $failedPilotChecks->implode(', ')
                    : 'pilot hazırlık sonucu doğrulanamadı.'),
        ];
        if (!$pilotReadinessPassed) $allPassed = false;

        // 2. Latest Golden Eval Check
        $evalEvidence = app(CustomerCareGoldenEvalGateService::class)->evaluate($storeId, 'tr');
        $checks['golden_eval'] = [
            'status' => $evalEvidence['passed'] ? 'passed' : 'failed',
            'label' => 'Golden Dataset Değerlendirmesi',
            'detail' => $evalEvidence['detail'],
        ];
        if (!$evalEvidence['passed']) $allPassed = false;

        // 3. Circuit Breaker status (must be closed/safe)
        $monitorService = app(CustomerCarePilotMonitorService::class);
        $metrics = $monitorService->getStoreMetrics($storeId, $actor);
        $circuitStatus = $metrics['circuit_breaker_status'] ?? 'unknown';
        $circuitClosed = $circuitStatus === 'closed';

        $checks['circuit_breaker'] = [
            'status' => $circuitClosed ? 'passed' : 'failed',
            'label' => 'Circuit Breaker Durumu',
            'detail' => match ($circuitStatus) {
                'closed' => 'Kapalı (Güvenli)',
                'open' => 'Açık; otomasyon bloke edildi: ' . ($metrics['trip_reason'] ?? 'Bilinmeyen neden'),
                'disabled' => 'Devre kesici izleme özelliği kapalı; güvenli durum doğrulanamadı.',
                default => 'Devre kesici durumu doğrulanamadı.',
            },
        ];
        if (!$circuitClosed) $allPassed = false;

        // 4. Provider Health check
        $healthService = app(CustomerCareAiProviderHealthService::class);
        $geminiHealthy = $healthService->isProviderHealthy('Gemini');
        $checks['provider_health'] = [
            'status' => $geminiHealthy ? 'passed' : 'failed',
            'label' => 'Gemini Sağlayıcı Sağlığı',
            'detail' => $geminiHealthy ? 'Gemini API anahtarı tanımlı ve sağlıklı.' : 'API anahtarı eksik veya geçersiz.',
        ];
        if (!$geminiHealthy) $allPassed = false;

        // 5. Budget & Quotas limit check
        $exceeded = $healthService->hasExceededBudget($storeId);
        $checks['budget_quota'] = [
            'status' => !$exceeded ? 'passed' : 'failed',
            'label' => 'Bütçe ve Limit Kontrolü',
            'detail' => !$exceeded ? 'Günlük/Aylık AI bütçe limitleri dahilinde.' : 'Günlük veya aylık AI bütçe limiti aşıldı.',
        ];
        if ($exceeded) $allPassed = false;

        // 6. Policy Engine check
        $policyEnabled = (bool) config('customer-care.enabled', false);
        $checks['policy_engine'] = [
            'status' => $policyEnabled ? 'passed' : 'failed',
            'label' => 'Politika Motoru Durumu',
            'detail' => $policyEnabled ? 'Politika doğrulama kuralları aktif.' : 'Master switch kapalı.',
        ];
        if (!$policyEnabled) $allPassed = false;

        // 7. Integration Secret Health check (must be decryptable if exists)
        $webhookChannel = SupportChannel::where('store_id', $storeId)
            ->where('key', 'webhook_outbound')
            ->first();
        $secretValid = true;
        $secretDetail = 'Webhook outbound kanalı tanımlanmamış veya anahtar gerekmiyor.';
        if ($webhookChannel) {
            $rawSecret = $webhookChannel->config_json['webhook_secret'] ?? '';
            if (empty($rawSecret)) {
                $secretValid = false;
                $secretDetail = 'Webhook imzalama anahtarı eksik.';
            } else {
                try {
                    Crypt::decryptString($rawSecret);
                    $secretDetail = 'Webhook imzalama anahtarı şifreli ve başarıyla doğrulanabiliyor.';
                } catch (\Exception $e) {
                    $secretValid = false;
                    $secretDetail = 'Webhook imzalama anahtarı şifresi çözülemedi.';
                }
            }
        }
        $checks['integration_secret'] = [
            'status' => $secretValid ? 'passed' : 'failed',
            'label' => 'Entegrasyon Anahtarı Doğrulaması',
            'detail' => $secretDetail,
        ];
        if (!$secretValid) $allPassed = false;

        // 8. Queue Health check (unknown or critical/backpressure fails)
        $queueService = app(CustomerCareQueueHealthService::class);
        $queueHealth = $queueService->checkBackpressure($storeId);
        $queuePassed = ($queueHealth['status'] ?? 'unknown') === 'healthy';
        $checks['queue_health'] = [
            'status' => $queuePassed ? 'passed' : 'failed',
            'label' => 'Kuyruk Sağlığı (Queue Health)',
            'detail' => $queuePassed ? 'Kuyruk durumu normal.' : 'Kuyruk aşırı yüklü (backpressure aktif) veya veri bulunmuyor (unknown): ' . ($queueHealth['reason'] ?? ''),
        ];
        if (!$queuePassed) $allPassed = false;

        return [
            'allowed' => $allPassed,
            'checks' => $checks,
        ];
    }

    public function createPlan(int $storeId, array $data, ?User $actor = null): SupportLaunchPlan
    {
        $actor = $actor ?? Auth::user() ?? TenantContext::getSystemActor();
        TenantContext::enforceStoreAccess($storeId, $actor);
        app(SupportRbacService::class)->enforcePermission($actor, $storeId, 'force_circuit_breaker');

        $checklist = $this->checkChecklist($storeId, $actor);
        $status = $checklist['allowed'] ? 'draft' : 'readiness_failed';

        return DB::transaction(function () use ($storeId, $data, $checklist, $status) {
            $plan = SupportLaunchPlan::create([
                'store_id' => $storeId,
                'status' => $status,
                'target_channels' => $data['target_channels'] ?? [],
                'initial_mode' => $data['initial_mode'] ?? 'manual',
                'canary_percentage' => $data['canary_percentage'] ?? 100,
                'conversation_limit' => $data['conversation_limit'] ?? null,
                'allowed_categories' => $data['allowed_categories'] ?? [],
                'rollback_rules' => $data['rollback_rules'] ?? [],
                'readiness_snapshot' => $checklist['checks'],
            ]);

            // Create step milestones
            $steps = [
                'Preflight checklist analysis passed',
                'Governance launch review & approval',
                'Canary pilot stage deployment',
                'Completed full rollout production launch'
            ];
            foreach ($steps as $idx => $title) {
                SupportLaunchPlanStep::create([
                    'launch_plan_id' => $plan->id,
                    'step_number' => $idx + 1,
                    'title' => $title,
                    'status' => ($idx === 0 && $status !== 'readiness_failed') ? 'completed' : 'pending',
                ]);
            }

            SupportLaunchEvent::create([
                'store_id' => $storeId,
                'launch_plan_id' => $plan->id,
                'event_type' => 'plan_created',
                'details_json' => ['status' => $status],
            ]);

            return $plan;
        });
    }

    public function transitionTo(SupportLaunchPlan $plan, string $targetStatus, ?User $actor = null): void
    {
        $actor = $actor ?? Auth::user() ?? TenantContext::getSystemActor();
        $storeId = $plan->store_id;

        // Tenant IDOR check
        TenantContext::enforceStoreAccess($storeId, $actor);

        if ($plan->status === $targetStatus) {
            return;
        }

        $allowedTransitions = [
            'draft' => ['approved'],
            'readiness_failed' => ['approved'],
            'ready_for_approval' => ['approved'],
            'approved' => ['canary'],
            'paused' => ['canary'],
            'canary' => ['completed'],
            'completed' => [],
            'rolled_back' => [],
        ];
        if (!in_array($targetStatus, $allowedTransitions[$plan->status] ?? [], true)) {
            throw new \InvalidArgumentException("Geçersiz lansman durum geçişi: {$plan->status} -> {$targetStatus}.");
        }

        // Validity check
        if (in_array($targetStatus, ['approved', 'canary', 'completed'], true)) {
            $checklist = $this->checkChecklist($storeId, $actor);
            if (!$checklist['allowed']) {
                $plan->update(['status' => 'readiness_failed']);
                throw new \RuntimeException('Geçersiz hazırlık durumu (Readiness Checklist fail-closed). Durum readiness_failed olarak güncellendi.');
            }
        }

        // Enforce governance approval for approved, canary, completed
        if (in_array($targetStatus, ['approved', 'canary', 'completed'], true)) {
            $rbac = app(SupportRbacService::class);
            $rbac->enforcePermission($actor, $storeId, 'force_circuit_breaker');
            $rbac->enforceApproval($actor, $storeId, 'launch_plan_status_' . $targetStatus, [
                'plan_id' => $plan->id,
                'target_status' => $targetStatus,
            ]);
        }

        DB::transaction(function () use ($plan, $targetStatus, $actor, $storeId) {
            $oldStatus = $plan->status;
            $updateData = ['status' => $targetStatus];
            if ($targetStatus === 'approved') {
                $updateData['approver_id'] = $actor->id;
                $updateData['approved_at'] = now();
            }
            $plan->update($updateData);

            // Update steps based on status
            if ($targetStatus === 'approved') {
                $plan->steps()->where('step_number', '<=', 2)->update(['status' => 'completed']);
            } elseif ($targetStatus === 'canary') {
                $plan->steps()->where('step_number', '<=', 3)->update(['status' => 'completed']);
            } elseif ($targetStatus === 'completed') {
                $plan->steps()->update(['status' => 'completed']);
            }

            // Write launch event
            SupportLaunchEvent::create([
                'store_id' => $storeId,
                'launch_plan_id' => $plan->id,
                'event_type' => 'status_changed',
                'details_json' => [
                    'old_status' => $oldStatus,
                    'new_status' => $targetStatus,
                    'actor_id' => $actor->id,
                ],
            ]);
        });
    }

    public function rollback(SupportLaunchPlan $plan, ?User $actor = null): void
    {
        $actor = $actor ?? Auth::user() ?? TenantContext::getSystemActor();
        $storeId = $plan->store_id;

        // Tenant access check
        TenantContext::enforceStoreAccess($storeId, $actor);

        // Servis doğrudan çağrılsa dahi acil durdurma yetkisi zorunludur.
        app(SupportRbacService::class)->enforcePermission($actor, $storeId, 'force_circuit_breaker');

        if ($plan->status === 'rolled_back') {
            return;
        }
        if (!in_array($plan->status, ['approved', 'canary', 'paused', 'completed'], true)) {
            throw new \InvalidArgumentException("{$plan->status} durumundaki lansman planı geri alınamaz.");
        }

        // Veritabanı güncellemesi başarısız olsa bile yeni otomatik yanıtları
        // anında durduracak mağaza bazlı kilidi önce açıyoruz (fail-closed).
        Cache::forever("circuit_breaker_forced_open_{$storeId}", true);

        DB::transaction(function () use ($plan, $actor, $storeId) {
            // Update plan status
            $plan->update(['status' => 'rolled_back']);
            $plan->steps()->update(['status' => 'failed']);

            // Kanal varsayılanlarını da kapat; böylece rollback sonrasında oluşan
            // yeni konuşmalar automatic modla başlamaz.
            $updatedChannels = 0;
            SupportChannel::where('store_id', $storeId)->get()->each(function (SupportChannel $channel) use (&$updatedChannels) {
                $config = $channel->config_json ?? [];
                $config['automation_settings'] = array_merge(
                    $config['automation_settings'] ?? [],
                    ['ai_mode' => 'manual', 'auto_reply' => false]
                );
                $channel->update(['config_json' => $config]);
                $updatedChannels++;
            });

            // İnsan devri/handoff durumunu bozmadan yalnızca otomatik veya copilot
            // konuşmaları öneri moduna çek.
            $updatedConversations = SupportConversation::where('store_id', $storeId)
                ->whereIn('ai_mode', ['automatic', 'copilot'])
                ->update(['ai_mode' => 'suggestion_only']);

            // Cancel all pending outbox AI dispatches
            // (status = cancelled, last_error = Rolled back, message->delivery_status = cancelled)
            $dispatches = SupportDispatch::whereHas('conversation', function ($q) use ($storeId) {
                    $q->where('store_id', $storeId);
                })
                ->whereIn('status', ['pending', 'sending'])
                ->whereHas('message', function ($q) {
                    $q->where('sender_type', 'ai');
                })
                ->get();

            foreach ($dispatches as $dispatch) {
                $dispatch->update([
                    'status' => 'cancelled',
                    'last_error' => 'Launch plan rolled back by emergency stop.',
                ]);
                if ($dispatch->message) {
                    $dispatch->message->update(['delivery_status' => 'cancelled']);
                }
            }

            // Write launch event
            SupportLaunchEvent::create([
                'store_id' => $storeId,
                'launch_plan_id' => $plan->id,
                'event_type' => 'rollback_triggered',
                'details_json' => [
                    'actor_id' => $actor->id,
                    'cancelled_dispatches' => $dispatches->count(),
                    'updated_channels' => $updatedChannels,
                    'updated_conversations' => $updatedConversations,
                    'circuit_breaker_forced_open' => true,
                ],
            ]);
        });
    }
}
