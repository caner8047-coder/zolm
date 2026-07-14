<?php

namespace App\Services\Support;

use App\Models\SupportAgentAction;
use App\Models\SupportAiRun;
use App\Models\SupportAnswerError;
use App\Models\SupportConversation;
use App\Models\SupportCorrectionTask;
use App\Models\SupportDispatch;
use App\Models\SupportMessage;
use App\Models\SupportRegressionCase;
use App\Models\User;
use App\Services\Support\Security\PiiRedactor;
use App\Services\Support\Security\SupportRbacService;
use Illuminate\Support\Facades\DB;

class CustomerCareCorrectionService
{
    public function __construct(
        private SupportRbacService $rbac,
        private PiiRedactor $redactor,
        private SupportChannelManager $channels,
        private SupportOutboxService $outbox,
    ) {}

    public function report(
        SupportConversation $conversation,
        ?SupportMessage $message,
        User $reporter,
        string $affectedClaim,
        string $rootCause,
        string $severity = 'warning'
    ): SupportAnswerError {
        TenantContext::enforceConversationAccess($conversation->id, $reporter);
        $this->rbac->enforcePermission($reporter, (int) $conversation->store_id, 'approve_quality_review');

        if ($message && (int) $message->conversation_id !== (int) $conversation->id) {
            throw new \InvalidArgumentException('Hatalı mesaj konuşma ile eşleşmiyor.');
        }

        $severity = in_array($severity, ['info', 'warning', 'critical'], true) ? $severity : 'warning';
        $aiRun = $message
            ? SupportAiRun::where('conversation_id', $conversation->id)->where('message_id', $message->id)->latest()->first()
            : SupportAiRun::where('conversation_id', $conversation->id)->latest()->first();

        return DB::transaction(function () use ($conversation, $message, $reporter, $affectedClaim, $rootCause, $severity, $aiRun): SupportAnswerError {
            $error = SupportAnswerError::create([
                'store_id' => $conversation->store_id,
                'conversation_id' => $conversation->id,
                'message_id' => $message?->id,
                'support_ai_run_id' => $aiRun?->id,
                'reported_by' => $reporter->id,
                'severity' => $severity,
                'affected_claim_encrypted' => $this->redactor->maskPii($affectedClaim),
                'root_cause_encrypted' => $this->redactor->maskPii($rootCause),
                'correction_strategy' => 'correction_message',
                'status' => 'reported',
                'detected_at' => now(),
            ]);

            SupportCorrectionTask::create([
                'support_answer_error_id' => $error->id,
                'assigned_user_id' => $conversation->assigned_user_id ?: $reporter->id,
                'task_type' => 'send_correction',
                'status' => 'pending',
                'due_at' => $severity === 'critical' ? now()->addMinutes(15) : now()->addHours(4),
            ]);

            $question = $aiRun?->prompt_raw ?: (string) $conversation->messages()
                ->where('sender_type', 'customer')->latest('id')->value('body_encrypted');
            SupportRegressionCase::create([
                'store_id' => $conversation->store_id,
                'support_answer_error_id' => $error->id,
                'language' => 'tr',
                'intent' => $aiRun?->prompt_template_key ?: 'general',
                'question_encrypted' => $question ?: '[SORU BULUNAMADI]',
                'wrong_answer_encrypted' => $message?->body_encrypted ?: $aiRun?->response_raw,
                'status' => 'pending_review',
            ]);

            $conversation->update(['ownership_status' => 'human', 'ai_mode' => 'handoff']);
            SupportDispatch::where('conversation_id', $conversation->id)
                ->whereIn('status', ['pending', 'failed', 'sending'])
                ->whereHas('message', fn ($query) => $query->where('sender_type', 'ai'))
                ->update(['status' => 'cancelled', 'last_error' => 'Yanlış cevap düzeltme süreci başlatıldı.']);

            if ($severity === 'critical') {
                $config = $conversation->channel->config_json ?? [];
                $config['automation_settings']['ai_mode'] = 'manual';
                $config['automation_settings']['auto_reply'] = false;
                $conversation->channel->update(['config_json' => $config]);
            }

            SupportAgentAction::create([
                'conversation_id' => $conversation->id,
                'message_id' => $message?->id,
                'user_id' => $reporter->id,
                'action' => 'answer_error_reported',
                'details_json' => ['error_id' => $error->id, 'severity' => $severity],
            ]);

            return $error;
        });
    }

    public function correct(SupportAnswerError $error, string $correctionText, User $actor): SupportAnswerError
    {
        $conversation = $error->conversation;
        TenantContext::enforceConversationAccess($conversation->id, $actor);
        $this->rbac->enforcePermission($actor, (int) $error->store_id, 'approve_quality_review');

        $correctionText = trim($this->redactor->maskPii(strip_tags($correctionText)));
        if ($correctionText === '') {
            throw new \InvalidArgumentException('Düzeltme mesajı boş olamaz.');
        }

        return DB::transaction(function () use ($error, $conversation, $correctionText, $actor): SupportAnswerError {
            $strategy = 'correction_message';
            $adapter = $this->channels->resolveForChannel($conversation->channel);
            $originalDispatch = $error->message_id
                ? SupportDispatch::where('message_id', $error->message_id)->whereNotNull('channel_message_id')->latest()->first()
                : null;

            if ($adapter instanceof SupportMessageCorrectionAdapterInterface && $originalDispatch) {
                $capabilities = $adapter->correctionCapabilities($conversation->channel);
                if (!empty($capabilities)) {
                    $result = $adapter->correctMessage(
                        $conversation->channel,
                        $conversation->external_conversation_id,
                        $originalDispatch->channel_message_id,
                        $correctionText
                    );
                    if (($result['success'] ?? false) === true) {
                        $strategy = (string) ($result['strategy'] ?? $capabilities[0]);
                    }
                }
            }

            $correctionMessage = null;
            if ($strategy === 'correction_message') {
                $correctionMessage = SupportMessage::create([
                    'conversation_id' => $conversation->id,
                    'direction' => 'outbound',
                    'sender_type' => 'agent',
                    'message_type' => 'correction',
                    'body_encrypted' => $correctionText,
                    'body_preview' => mb_substr($correctionText, 0, 100),
                    'delivery_status' => 'queued',
                ]);
                $this->outbox->enqueue($correctionMessage, 'correction_error_' . $error->id);
            }

            $error->update([
                'correction_strategy' => $strategy,
                'correction_message_id' => $correctionMessage?->id,
                'status' => 'correction_queued',
                'corrected_at' => now(),
            ]);
            $error->tasks()->where('status', 'pending')->update([
                'status' => 'completed',
                'completed_at' => now(),
                'result_json' => ['strategy' => $strategy, 'correction_message_id' => $correctionMessage?->id],
            ]);
            $error->regressionCase?->update(['expected_answer_encrypted' => $correctionText]);

            SupportAgentAction::create([
                'conversation_id' => $conversation->id,
                'message_id' => $correctionMessage?->id,
                'user_id' => $actor->id,
                'action' => 'answer_correction_queued',
                'details_json' => ['error_id' => $error->id, 'strategy' => $strategy],
            ]);

            return $error->fresh();
        });
    }
}
