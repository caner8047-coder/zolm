<?php

namespace App\Services\Support\Policy;

use App\Models\SupportConversation;
use App\Models\SupportPolicyDecision;

class CustomerCareChannelPolicyService
{
    public function __construct(private SupportPolicyEngine $engine)
    {
    }

    public function validate(
        SupportConversation $conversation,
        string $message,
        ?int $messageId = null,
        ?int $actorUserId = null,
    ): array {
        $channelKey = $conversation->channel->key;
        if (($conversation->source_reference_json['thread_type'] ?? null) === 'comment') {
            $channelKey .= '_comment';
        }

        $result = $this->engine->validate($message, $channelKey);
        $code = $result['allowed'] ? 'validator_set_passed' : 'content_policy_blocked';

        if ($result['allowed'] && $conversation->channel->key === 'whatsapp') {
            $windowOpen = $conversation->last_inbound_at?->gte(now()->subHours(24)) ?? false;
            $reference = $conversation->source_reference_json ?? [];
            $approvedTemplate = ($reference['approved_template'] ?? false) === true
                && !empty($reference['template_name']);
            if (!$windowOpen && !$approvedTemplate) {
                $result = [
                    'allowed' => false,
                    'reason' => 'WhatsApp 24 saat müşteri hizmetleri penceresi kapalı; onaylı template zorunludur.',
                    'version' => SupportPolicyEngine::VERSION,
                    'validator_set' => $result['validator_set'] ?? [],
                ];
                $code = 'whatsapp_window_template_required';
            }
        }

        SupportPolicyDecision::create([
            'store_id' => $conversation->store_id,
            'support_channel_id' => $conversation->support_channel_id,
            'conversation_id' => $conversation->id,
            'message_id' => $messageId,
            'policy_version' => $result['version'] ?? SupportPolicyEngine::VERSION,
            'channel_key' => $channelKey,
            'allowed' => (bool) $result['allowed'],
            'decision_code' => $code,
            'reason' => $result['reason'] ?? null,
            'validator_set_json' => $result['validator_set'] ?? [],
            'actor_user_id' => $actorUserId,
        ]);

        return $result;
    }
}
