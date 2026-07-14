<?php

namespace App\Services\Support;

use App\Models\SupportProjectionCursor;
use App\Models\SupportReconciliationRun;
use App\Models\SupportReconciliationFinding;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\SupportDispatch;
use App\Models\SupportChannel;
use App\Models\User;
use App\Services\Support\TenantContext;
use App\Services\Support\Security\SupportRbacService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class CustomerCareReconciliationService
{
    public function runReconciliation(int $storeId, ?User $actor = null): SupportReconciliationRun
    {
        $actor = $actor ?? Auth::user() ?? TenantContext::getSystemActor();
        TenantContext::enforceStoreAccess($storeId, $actor);

        return DB::transaction(function () use ($storeId) {
            $run = SupportReconciliationRun::create([
                'store_id' => $storeId,
                'started_at' => now(),
                'status' => 'running',
            ]);

            $findingsCount = 0;

            // 1. Check for Channel Store Mismatches (IDOR / integrity leaks)
            $mismatchedConversations = SupportConversation::where('store_id', $storeId)
                ->whereHas('channel', function ($q) use ($storeId) {
                    $q->where('store_id', '!=', $storeId);
                })
                ->get();

            foreach ($mismatchedConversations as $conv) {
                SupportReconciliationFinding::create([
                    'run_id' => $run->id,
                    'store_id' => $storeId,
                    'finding_type' => 'channel_store_mismatch',
                    'details_json' => [
                        'conversation_id' => $conv->id,
                        'conversation_store_id' => $conv->store_id,
                        'channel_store_id' => $conv->channel->store_id ?? null,
                    ],
                    'status' => 'detected',
                ]);
                $findingsCount++;
            }

            // 2. Check for Orphan Dispatches (Missing message or channel mismatch)
            $orphanDispatches = SupportDispatch::whereHas('conversation', function ($q) use ($storeId) {
                    $q->where('store_id', $storeId);
                })
                ->where(function ($q) {
                    $q->whereNull('message_id')
                      ->orWhereHas('message', function ($mq) {
                          $mq->where('conversation_id', '!=', DB::raw('support_dispatches.conversation_id'));
                      });
                })
                ->get();

            foreach ($orphanDispatches as $disp) {
                SupportReconciliationFinding::create([
                    'run_id' => $run->id,
                    'store_id' => $storeId,
                    'finding_type' => 'orphan_dispatch',
                    'details_json' => [
                        'dispatch_id' => $disp->id,
                        'message_id' => $disp->message_id,
                    ],
                    'status' => 'detected',
                ]);
                $findingsCount++;
            }

            // 3. Check for Stale Cursors
            $staleCursors = SupportProjectionCursor::where('store_id', $storeId)
                ->where('last_synced_at', '<=', now()->subHours(24))
                ->get();

            foreach ($staleCursors as $cur) {
                SupportReconciliationFinding::create([
                    'run_id' => $run->id,
                    'store_id' => $storeId,
                    'finding_type' => 'stale_cursor',
                    'details_json' => [
                        'cursor_id' => $cur->id,
                        'channel_type' => $cur->channel_type,
                        'last_synced_at' => $cur->last_synced_at ? $cur->last_synced_at->toIso8601String() : null,
                    ],
                    'status' => 'detected',
                ]);
                $findingsCount++;
            }

            $run->update([
                'completed_at' => now(),
                'status' => 'completed',
                'summary_json' => [
                    'findings_count' => $findingsCount,
                    'mismatched_conversations' => $mismatchedConversations->count(),
                    'orphan_dispatches' => $orphanDispatches->count(),
                    'stale_cursors' => $staleCursors->count(),
                ]
            ]);

            return $run;
        });
    }

    public function repairFinding(SupportReconciliationFinding $finding, ?User $actor = null, bool $execute = false): void
    {
        $actor = $actor ?? Auth::user() ?? TenantContext::getSystemActor();
        $storeId = $finding->store_id;

        // Tenant access check
        TenantContext::enforceStoreAccess($storeId, $actor);

        if ($finding->status === 'repaired') {
            return;
        }

        if (!$execute) {
            // Dry run: does not mutate
            return;
        }

        // Enforce RBAC permission checks for execution
        $rbac = app(SupportRbacService::class);
        $rbac->enforcePermission($actor, $storeId, 'force_circuit_breaker');
        $rbac->enforceApproval($actor, $storeId, 'repair_finding_' . $finding->id, [
            'finding_id' => $finding->id,
            'finding_type' => $finding->finding_type,
        ]);

        DB::transaction(function () use ($finding, $actor, $storeId) {
            $details = $finding->details_json;

            // Execute repairs safely without raw database restore
            if ($finding->finding_type === 'channel_store_mismatch') {
                $conv = SupportConversation::find($details['conversation_id']);
                if ($conv && $conv->channel) {
                    // Update conversation store_id to match channel store_id to fix leak
                    $conv->update(['store_id' => $conv->channel->store_id]);
                }
            } elseif ($finding->finding_type === 'orphan_dispatch') {
                $disp = SupportDispatch::find($details['dispatch_id']);
                if ($disp) {
                    $disp->update(['status' => 'cancelled', 'last_error' => 'Reconciliation: cancelled orphaned dispatch.']);
                }
            } elseif ($finding->finding_type === 'stale_cursor') {
                $cursor = SupportProjectionCursor::find($details['cursor_id']);
                if ($cursor) {
                    $cursor->update(['status' => 'synced', 'last_synced_at' => now()]);
                }
            }

            $finding->update([
                'status' => 'repaired',
                'repaired_at' => now(),
                'repaired_by' => $actor->id,
            ]);

            // Append event log
            \App\Models\SupportAgentAction::create([
                'conversation_id' => null,
                'user_id' => $actor->id,
                'action' => 'reconciliation_repair',
                'details_json' => [
                    'finding_id' => $finding->id,
                    'finding_type' => $finding->finding_type,
                    'store_id' => $storeId,
                ],
            ]);
        });
    }

    public function backfillMessage(int $storeId, int $channelId, array $externalPayload): array
    {
        // Enforce provider health - fail-closed if missing
        $channel = SupportChannel::find($channelId);
        if (!$channel || !$channel->is_enabled) {
            throw new \RuntimeException('Kanal veya sağlayıcı devre dışı (fail-closed).');
        }

        $externalId = $externalPayload['external_id'] ?? null;
        if (!$externalId) {
            throw new \RuntimeException('Geçersiz external_id.');
        }

        // Idempotency: prevent duplicates by checking external_message_id
        $existing = SupportMessage::where('external_message_id', $externalId)
            ->whereHas('conversation', function ($q) use ($channelId) {
                $q->where('support_channel_id', $channelId);
            })
            ->first();

        if ($existing) {
            return ['success' => true, 'duplicate' => true, 'message_id' => $existing->id];
        }

        // Tenant IDOR check
        if ((int)$channel->store_id !== $storeId) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Store ID mismatch (IDOR Protection).');
        }

        // Prevent leaking raw webhook payload in message body
        $cleanBody = $externalPayload['body'] ?? '[KVKK-TEMİZLENDİ]';

        return DB::transaction(function () use ($storeId, $channel, $externalId, $cleanBody) {
            $conv = SupportConversation::firstOrCreate([
                'store_id' => $storeId,
                'support_channel_id' => $channel->id,
                'external_conversation_id' => 'conv_' . $externalId,
            ], [
                'external_customer_id' => 'cust_' . $externalId,
                'status' => 'open',
                'ai_mode' => 'manual',
                'source_type' => 'chat',
            ]);

            $message = SupportMessage::create([
                'conversation_id' => $conv->id,
                'external_message_id' => $externalId,
                'direction' => 'inbound',
                'sender_type' => 'customer',
                'message_type' => 'text',
                'body_encrypted' => $cleanBody,
                'body_preview' => mb_substr($cleanBody, 0, 100),
                'sent_at' => now(),
                'delivery_status' => 'sent',
            ]);

            $channelType = str_contains($channel->key, 'whatsapp') ? 'whatsapp' : (str_contains($channel->key, 'trendyol') ? 'trendyol' : $channel->key);

            // Update Cursor
            SupportProjectionCursor::updateOrCreate([
                'store_id' => $storeId,
                'channel_type' => $channelType,
                'cursor_key' => 'last_seen_message',
            ], [
                'channel_id' => $channel->id,
                'last_seen_external_id' => $externalId,
                'last_synced_at' => now(),
                'status' => 'synced',
            ]);

            return ['success' => true, 'duplicate' => false, 'message_id' => $message->id];
        });
    }
}
