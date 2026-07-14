<?php

namespace App\Services\Support;

use App\Models\SupportMessage;
use App\Models\SupportDispatch;
use App\Models\SupportDispatchAttempt;
use App\Services\Support\SupportChannelManager;
use Illuminate\Support\Facades\DB;
use Throwable;

class SupportOutboxService
{
    /**
     * Mesajı gönderilmek üzere outbox kuyruğuna ekler.
     */
    public function enqueue(SupportMessage $message, ?string $idempotencyKey = null): SupportDispatch
    {
        $idempotencyKey = $idempotencyKey ?? 'msg_dispatch_' . $message->id;

        return DB::transaction(function () use ($message, $idempotencyKey) {
            // Check if already dispatched
            $existing = SupportDispatch::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }

            return SupportDispatch::create([
                'support_channel_id' => $message->conversation->support_channel_id,
                'conversation_id' => $message->conversation_id,
                'message_id' => $message->id,
                'idempotency_key' => $idempotencyKey,
                'status' => 'pending',
                'attempt_count' => 0,
                'retry_at' => now(),
            ]);
        });
    }

    /**
     * Kuyrukta bekleyen veya yeniden denenecek mesajları gönderir.
     */
    public function processPendingDispatches(): void
    {
        // 1. Stale 'sending' kayıtları kurtar (5 dakikadan uzun süredir sending olanları pending'e çek)
        SupportDispatch::where('status', 'sending')
            ->where('updated_at', '<=', now()->subMinutes(5))
            ->update([
                'status' => 'pending',
                'retry_at' => now(),
            ]);

        // 2. Aday kayıtların ID'lerini seç (deneme sınırı aşılmamış olanlar)
        $candidateIds = SupportDispatch::whereIn('status', ['pending', 'failed'])
            ->where('attempt_count', '<', 5)
            ->where(function ($q) {
                $q->whereNull('retry_at')->orWhere('retry_at', '<=', now());
            })
            ->limit(10)
            ->pluck('id')
            ->toArray();

        // 3. Atomik claim ile her bir kaydı işle
        foreach ($candidateIds as $id) {
            $affected = SupportDispatch::where('id', $id)
                ->whereIn('status', ['pending', 'failed'])
                ->where('attempt_count', '<', 5)
                ->where(function ($q) {
                    $q->whereNull('retry_at')->orWhere('retry_at', '<=', now());
                })
                ->update(['status' => 'sending']);

            if ($affected > 0) {
                $dispatch = SupportDispatch::find($id);
                if ($dispatch) {
                    $this->sendDispatch($dispatch);
                }
            }
        }
    }

    /**
     * Tek bir gönderimi işler ve durum güncellemelerini yapar.
     */
    public function sendDispatch(SupportDispatch $dispatch): bool
    {
        // 0. Zaten nihai duruma ulaşmış dispatches'ları yeniden gönderme
        if (in_array($dispatch->status, ['sent', 'accepted', 'queued', 'cancelled', 'exhausted'], true)) {
            return false;
        }

        // 0.1 Bütünlük Doğrulaması (Data Integrity Checks)
        $message = $dispatch->message;
        $conversation = $dispatch->conversation;
        $channel = $dispatch->channel;

        if (!$message || !$conversation || !$channel) {
            $dispatch->update(['status' => 'failed', 'last_error' => 'Data integrity error: missing related models.']);
            return false;
        }

        if ((int)$dispatch->support_channel_id !== (int)$conversation->support_channel_id) {
            $dispatch->update(['status' => 'failed', 'last_error' => 'Integrity breach: support_channel_id mismatch.']);
            return false;
        }

        if ((int)$conversation->store_id !== (int)$channel->store_id) {
            $dispatch->update(['status' => 'failed', 'last_error' => 'Integrity breach: store_id mismatch.']);
            return false;
        }

        if ((int)$message->conversation_id !== (int)$conversation->id) {
            $dispatch->update(['status' => 'failed', 'last_error' => 'Integrity breach: conversation_id mismatch.']);
            return false;
        }

        // 1. Enforce Master Kill Switch
        if (!config('customer-care.enabled')) {
            $dispatch->update(['status' => 'failed', 'last_error' => 'Blocked: Master kill switch is disabled.']);
            $dispatch->message->update(['delivery_status' => 'failed']);
            return false;
        }

        // 2. Enforce Channel Kill Switch
        if (!$dispatch->channel || !$dispatch->channel->is_enabled) {
            $dispatch->update(['status' => 'failed', 'last_error' => 'Blocked: Channel is disabled.']);
            $dispatch->message->update(['delivery_status' => 'failed']);
            return false;
        }

        // 3. Enforce Human Ownership Lock for AI replies
        if ($dispatch->message->sender_type === 'ai' && $dispatch->conversation->ownership_status === 'human') {
            $dispatch->update(['status' => 'cancelled', 'last_error' => 'Blocked: Locked by a human agent.']);
            $dispatch->message->update(['delivery_status' => 'cancelled']);
            return false;
        }

        // 3.1. Enforce Policy Engine Validation
        $policyResult = app(\App\Services\Support\Policy\CustomerCareChannelPolicyService::class)
            ->validate($conversation, $message->body_encrypted, $message->id, auth()->id());
        if (!$policyResult['allowed']) {
            $dispatch->update(['status' => 'cancelled', 'last_error' => 'Policy Violation: ' . $policyResult['reason']]);
            $dispatch->message->update(['delivery_status' => 'cancelled']);

            \App\Models\SupportAgentAction::create([
                'conversation_id' => $conversation->id,
                'user_id' => auth()->id() ?? \App\Services\Support\TenantContext::getSystemActor()?->id,
                'action' => 'policy_block',
                'details_json' => [
                    'reason' => $policyResult['reason'],
                    'channel' => $channel->key,
                    'sender_type' => $message->sender_type,
                ]
            ]);
            return false;
        }

        // 3.2. Enforce Automation Gate & Circuit Breaker (only for AI replies)
        if ($message->sender_type === 'ai') {
            // Quota limit check
            $usageService = app(\App\Services\Support\CustomerCareUsageService::class);
            $limitCheck = $usageService->checkLimit($conversation->store_id, 'auto_replies');
            if (!$limitCheck['allowed']) {
                $dispatch->update(['status' => 'failed', 'last_error' => 'Quota Exceeded: ' . $limitCheck['reason']]);
                $message->update(['delivery_status' => 'failed']);
                return false;
            }

            $aiRun = \App\Models\SupportAiRun::where('message_id', $message->id)->first();
            if (!$aiRun) {
                $dispatch->update(['status' => 'cancelled', 'last_error' => 'Automation Gate Blocked: AI karar defteri kaydı bulunamadı.']);
                $message->update(['delivery_status' => 'cancelled']);
                return false;
            }
            $confidence = (int) $aiRun->confidence_score;

            $sourceLedger = app(\App\Services\Support\AI\CustomerCareSourceLedgerService::class);
            $sources = (array) ($aiRun->sources_used_json ?? []);
            $sourceValidation = $sourceLedger->validate($sources);
            if (!$sourceValidation['valid'] || !$sourceLedger->containsRequiredClaimSource((string) $message->body_encrypted, $sources)) {
                $reason = !$sourceValidation['valid']
                    ? $sourceValidation['reason']
                    : 'Kesin iddia için doğrulanmış kayıt kimliği bulunamadı.';
                $dispatch->update(['status' => 'cancelled', 'last_error' => 'Source Ledger Blocked: ' . $reason]);
                $message->update(['delivery_status' => 'cancelled']);
                return false;
            }

            $gate = app(\App\Services\Support\AI\CustomerCareAutomationGate::class);
            $gateResult = $gate->canAutomate($conversation, $confidence);
            if (!$gateResult['allowed']) {
                $dispatch->update(['status' => 'failed', 'last_error' => 'Automation Gate Blocked: ' . $gateResult['reason']]);
                $message->update(['delivery_status' => 'failed']);
                return false;
            }
        }

        // Enforce Rate Limiting (Reliability P0-3, P0-4 equivalent)
        $reliabilityEnabled = config('customer-care.reliability_enabled', false);
        if ($reliabilityEnabled) {
            $rateLimiter = app(\App\Services\Support\Reliability\CustomerCareRateLimiter::class);
            if (!$rateLimiter->checkLimit($conversation->store_id, $channel->key)) {
                $dispatch->update(['status' => 'failed', 'last_error' => 'Rate Limit Exceeded: Kanal gönderim limiti aşıldı.']);
                $message->update(['delivery_status' => 'failed']);
                return false;
            }
        }

        $startTime = microtime(true);
        $attemptCount = $dispatch->attempt_count + 1;

        try {
            $adapter = app(SupportChannelManager::class)->resolveForChannel($dispatch->channel);

            // Gerçek gönderim
            $result = $adapter->sendReply(
                $dispatch->channel,
                $dispatch->conversation->external_conversation_id,
                $dispatch->message->body_encrypted,
                $dispatch->idempotency_key
            );

            $latency = (int)((microtime(true) - $startTime) * 1000);

            if ($result && isset($result['success']) && $result['success']) {
                $targetStatus = (isset($result['status']) && in_array($result['status'], ['sent', 'accepted', 'queued', 'pending'], true))
                    ? $result['status']
                    : $adapter->getOutboundTargetStatus();

                $dispatch->update([
                    'status' => $targetStatus,
                    'attempt_count' => $attemptCount,
                    'channel_message_id' => $result['channel_message_id'] ?? null,
                    'last_error' => null,
                    'retry_at' => null,
                ]);

                $dispatch->message->update(['delivery_status' => $targetStatus]);
                $dispatch->conversation->update(['last_outbound_at' => now(), 'last_message_at' => now()]);

                // Increment Usage Quotas
                $usageService = app(\App\Services\Support\CustomerCareUsageService::class);
                if ($message->sender_type === 'ai') {
                    $usageService->incrementUsage($conversation->store_id, 'auto_replies');
                } else {
                    $usageService->incrementUsage($conversation->store_id, 'agent_replies');
                }

                SupportDispatchAttempt::create([
                    'support_dispatch_id' => $dispatch->id,
                    'attempted_at' => now(),
                    'status' => 'success',
                    'latency_ms' => $latency,
                ]);

                \App\Models\SupportAgentAction::create([
                    'conversation_id' => $conversation->id,
                    'message_id' => $message->id,
                    'user_id' => \App\Services\Support\TenantContext::getSystemActor()?->id,
                    'action' => 'reply_delivered',
                    'details_json' => [
                        'dispatch_id' => $dispatch->id,
                        'sender_type' => $message->sender_type,
                        'channel' => $channel->key,
                        'delivery_status' => $targetStatus,
                        'channel_message_id' => $result['channel_message_id'] ?? null,
                    ],
                ]);

                return true;
            } else {
                $errorMessage = $result['message'] ?? 'Bilinmeyen kanal hatası';
                $this->handleFailure($dispatch, $errorMessage, $attemptCount, $latency);
                return false;
            }
        } catch (Throwable $e) {
            $latency = (int)((microtime(true) - $startTime) * 1000);
            $this->handleFailure($dispatch, $e->getMessage(), $attemptCount, $latency);
            return false;
        }
    }

    protected function handleFailure(SupportDispatch $dispatch, string $error, int $attemptCount, int $latency): void
    {
        // Exponential backoff retry plan (Örn: 2^attempt * 5 saniye)
        // Maksimum 5 deneme
        $maxAttempts = 5;

        if ($attemptCount >= $maxAttempts) {
            $status = 'exhausted'; // Terminal başarısızlık durumu
            $retryAt = null;
            $dispatch->message->update(['delivery_status' => 'failed']);
        } else {
            $status = 'failed';
            $delaySeconds = pow(2, $attemptCount) * 5;
            $retryAt = now()->addSeconds($delaySeconds);
        }

        $dispatch->update([
            'status' => $status,
            'attempt_count' => $attemptCount,
            'retry_at' => $retryAt,
            'last_error' => $error,
        ]);

        SupportDispatchAttempt::create([
            'support_dispatch_id' => $dispatch->id,
            'attempted_at' => now(),
            'status' => 'failed',
            'error_message' => $error,
            'latency_ms' => $latency,
        ]);
    }
}
