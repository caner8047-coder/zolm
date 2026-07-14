<?php

namespace App\Services\Support;

use App\Models\MarketplaceQuestion;
use App\Models\SupportChannel;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SupportProjectionService
{
    /**
     * Bir MarketplaceQuestion kaydını idempotent biçimde support_conversations ve support_messages tablolarına yansıtır.
     */
    public function projectQuestion(MarketplaceQuestion $question): SupportConversation
    {
        return DB::transaction(function () use ($question) {
            $question->loadMissing('store');
            $marketplace = Str::of((string) ($question->store?->marketplace ?: 'marketplace'))
                ->lower()
                ->ascii()
                ->replaceMatches('/[^a-z0-9_\-]/', '_')
                ->limit(40, '')
                ->value();
            $channelName = Str::limit(Str::headline($marketplace) . ' Soru-Cevap', 80, '');

            // 1. Kanalı bul veya oluştur
            $channel = SupportChannel::firstOrCreate(
                [
                    'store_id' => $question->store_id,
                    'key' => $marketplace,
                ],
                [
                    'name' => $channelName,
                    'status' => 'not_configured',
                    'is_enabled' => false,
                    'config_json' => [
                        'automation_settings' => [
                            'ai_mode' => 'manual',
                            'min_confidence' => 80,
                        ],
                    ],
                ]
            );

            // 2. Konuşmayı bul veya oluştur
            $externalConvId = $marketplace . '_questions_' . $question->id;

            // Map question status to support conversation status
            // open, pending, resolved, closed, snoozed
            $statusMap = [
                'open' => 'open',
                'pending' => 'pending',
                'answered' => 'resolved',
                'closed' => 'closed',
            ];
            $convStatus = $statusMap[$question->status] ?? 'open';
            $channelAutomation = $channel->config_json['automation_settings'] ?? [];
            $channelMode = in_array(($channelAutomation['ai_mode'] ?? 'manual'), ['manual', 'suggestion_only', 'automatic'], true)
                ? $channelAutomation['ai_mode']
                : 'manual';

            $conversation = SupportConversation::updateOrCreate(
                [
                    'support_channel_id' => $channel->id,
                    'external_conversation_id' => $externalConvId,
                ],
                [
                    'external_customer_id' => $question->customer_external_id,
                    'store_id' => $question->store_id,
                    'source_type' => $marketplace,
                    'status' => $convStatus,
                    'ai_mode' => $channelMode,
                    'assigned_user_id' => $question->answered_by_user_id,
                    'last_message_at' => $question->answered_at ?? $question->asked_at,
                    'last_inbound_at' => $question->asked_at,
                    'last_outbound_at' => $question->answered_at,
                    'source_reference_json' => [
                        'question_id' => $question->id,
                        'external_question_id' => $question->external_question_id,
                        'product_name' => $question->product_name,
                        'product_sku' => $question->product_sku,
                        'product_barcode' => $question->product_barcode,
                    ],
                ]
            );

            // 3. Gelen soruyu (inbound message) yansıt
            if ($question->question_text) {
                SupportMessage::updateOrCreate(
                    [
                        'conversation_id' => $conversation->id,
                        'external_message_id' => $marketplace . '_msg_in_' . $question->id,
                    ],
                    [
                        'direction' => 'inbound',
                        'sender_type' => 'customer',
                        'message_type' => 'text',
                        'body_encrypted' => $question->question_text,
                        'body_preview' => mb_substr($question->question_text, 0, 100),
                        'sent_at' => $question->asked_at,
                        'received_at' => $question->asked_at,
                        'delivery_status' => 'delivered',
                        'source_reference_type' => 'MarketplaceQuestion',
                        'source_reference_id' => $question->id,
                    ]
                );
            }

            // 4. Cevabı (outbound message) yansıt (varsa)
            if ($question->answer_text && in_array($question->status, ['answered', 'closed'], true)) {
                SupportMessage::updateOrCreate(
                    [
                        'conversation_id' => $conversation->id,
                        'external_message_id' => $marketplace . '_msg_out_' . $question->id,
                    ],
                    [
                        'direction' => 'outbound',
                        'sender_type' => 'agent',
                        'message_type' => 'text',
                        'body_encrypted' => $question->answer_text,
                        'body_preview' => mb_substr($question->answer_text, 0, 100),
                        'sent_at' => $question->answered_at,
                        'received_at' => $question->answered_at,
                        'delivery_status' => 'delivered',
                        'source_reference_type' => 'MarketplaceQuestion',
                        'source_reference_id' => $question->id,
                    ]
                );
            }

            return $conversation;
        });
    }
}
