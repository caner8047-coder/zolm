<?php

namespace App\Services\Support;

use App\Models\MarketplaceStore;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\SupportAgentAction;
use App\Models\WaContact;
use App\Models\User;
use App\Services\Support\Security\SupportRbacService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Dalga U — KVKK Retention & Anonymization Service
 *
 * Tasarım kararları:
 * - Audit bütünlüğünü bozmadan çalışır.
 * - Dispatch attempts ve AI runs append-only mantığı korunur; raw PII redakte edilir.
 * - Store scope zorunlu; cross-store anonymization engellenir.
 * - Dry-run varsayılan; force ile gerçek işlem.
 */
class CustomerCareAnonymizationService
{
    private const PII_REDACTED = '[KVKK-SİLİNDİ]';

    /**
     * Store bazlı PII anonymization.
     *
     * @throws \InvalidArgumentException Store bulunamazsa
     * @throws \RuntimeException Force olmadan gerçek işlem denenirse
     */
    public function anonymizeStore(int $storeId, bool $dryRun = true, ?User $actor = null): array
    {
        $actor = $actor ?? Auth::user() ?? TenantContext::getSystemActor();
        TenantContext::enforceStoreAccess($storeId, $actor);
        $store = MarketplaceStore::find($storeId);

        if (!$store) {
            throw new \InvalidArgumentException("Mağaza bulunamadı: {$storeId}");
        }

        if (!$dryRun) {
            $rbac = app(SupportRbacService::class);
            $rbac->enforcePermission($actor, $storeId, 'run_compliance');
            $rbac->enforceApproval($actor, $storeId, 'anonymize_store_' . $storeId, [
                'store_id' => $storeId,
            ]);

            return $this->performAnonymization($store, $actor);
        }

        return $this->dryRunReport($store);
    }

    /**
     * Dry-run: veriye dokunmadan rapor üretir.
     */
    private function dryRunReport(MarketplaceStore $store): array
    {
        $conversationCount = SupportConversation::where('store_id', $store->id)->count();
        $messageCount = SupportMessage::whereIn('conversation_id',
            SupportConversation::where('store_id', $store->id)->pluck('id')
        )->count();
        $waContactCount = WaContact::where('store_id', $store->id)->count();
        $agentActionCount = SupportAgentAction::whereIn('conversation_id',
            SupportConversation::where('store_id', $store->id)->pluck('id')
        )->count();
        $aiRunCount = \App\Models\SupportAiRun::where('store_id', $store->id)->count();
        $webLeadCount = \App\Models\SupportWebLead::where('store_id', $store->id)->count();

        return [
            'dry_run' => true,
            'store_id' => $store->id,
            'store_name' => $store->store_name,
            'would_anonymize' => [
                'support_conversations' => $conversationCount,
                'support_messages' => $messageCount,
                'wa_contacts' => $waContactCount,
                'agent_actions_pii_fields' => $agentActionCount,
                'support_ai_runs' => $aiRunCount,
                'support_web_leads' => $webLeadCount,
            ],
            'audit_ledger_preserved' => true,
            'message' => 'Dry-run tamamlandı. Gerçek anonymization için --force kullanın.',
        ];
    }

    /**
     * Gerçek anonymization — sadece force=true ile çalışır.
     * Audit bütünlüğü korunur; mesaj body ve WaContact PII alanları redakte edilir.
     */
    private function performAnonymization(MarketplaceStore $store, User $actor): array
    {
        $stats = [
            'dry_run' => false,
            'store_id' => $store->id,
            'conversations_processed' => 0,
            'messages_redacted' => 0,
            'wa_contacts_anonymized' => 0,
            'agent_actions_redacted' => 0,
            'ai_runs_redacted' => 0,
            'web_leads_redacted' => 0,
            'widget_sessions_redacted' => 0,
            'errors' => [],
        ];

        DB::beginTransaction();
        try {
            // Find customers under legal hold
            $holdCustomerIds = \App\Models\SupportLegalHold::where('store_id', $store->id)
                ->where('active', true)
                ->get()
                ->map(fn ($hold) => $hold->customer_id)
                ->all();

            if (!empty($holdCustomerIds)) {
                // Log block in audit log
                SupportAgentAction::create([
                    'conversation_id' => null,
                    'user_id' => $actor->id,
                    'action' => 'compliance_block',
                    'details_json' => [
                        'reason' => 'Anonymization partially blocked by active Legal Holds.',
                        'blocked_customer_hashes' => array_map(
                            fn ($customerId) => hash('sha256', (string) $customerId),
                            $holdCustomerIds
                        ),
                    ]
                ]);
            }

            // 1. support_messages — body redakte (excluding legal holds)
            $holdCustomerHashes = array_map(fn ($id) => hash('sha256', (string) $id), $holdCustomerIds);
            $heldConversationIds = SupportConversation::where('store_id', $store->id)
                ->whereIn('external_customer_hash', $holdCustomerHashes)
                ->pluck('id');
            $conversationModels = SupportConversation::where('store_id', $store->id)
                ->where(function ($query) use ($holdCustomerHashes): void {
                    $query->whereNull('external_customer_hash');
                    if (!empty($holdCustomerHashes)) {
                        $query->orWhereNotIn('external_customer_hash', $holdCustomerHashes);
                    }
                })
                ->get();
            $conversations = $conversationModels->pluck('id');
            $stats['conversations_processed'] = $conversations->count();

            $messagesRedacted = 0;
            SupportMessage::whereIn('conversation_id', $conversations)->get()->each(function (SupportMessage $message) use (&$messagesRedacted): void {
                $attachmentPath = data_get($message->payload_json, 'encrypted_path');
                if (is_string($attachmentPath) && str_starts_with($attachmentPath, 'customer-care/attachments/')) {
                    \Illuminate\Support\Facades\Storage::disk('local')->delete($attachmentPath);
                }
                $message->update([
                    'body_encrypted' => self::PII_REDACTED,
                    'body_preview' => self::PII_REDACTED,
                    'payload_json' => null,
                ]);
                $messagesRedacted++;
            });
            $stats['messages_redacted'] = $messagesRedacted;

            \App\Models\SupportAiRun::whereIn('conversation_id', $conversations)->get()->each(function ($run) use (&$stats): void {
                $run->update([
                    'prompt_raw' => self::PII_REDACTED,
                    'response_raw' => self::PII_REDACTED,
                ]);
                $stats['ai_runs_redacted']++;
            });

            foreach ($conversationModels as $conversation) {
                $conversation->update([
                    'external_customer_id' => self::PII_REDACTED . '-' . $conversation->id,
                ]);
            }

            $protectedCrmContactIds = \App\Models\SupportWebLead::where('store_id', $store->id)
                ->whereIn('conversation_id', $heldConversationIds)
                ->whereNotNull('crm_contact_id')
                ->pluck('crm_contact_id');
            $webLeads = \App\Models\SupportWebLead::where('store_id', $store->id)
                ->where(function ($query) use ($heldConversationIds): void {
                    $query->whereNull('conversation_id')
                        ->orWhereNotIn('conversation_id', $heldConversationIds);
                })
                ->get();
            foreach ($webLeads as $lead) {
                if ($lead->crm_contact_id && !$protectedCrmContactIds->contains($lead->crm_contact_id)) {
                    $contact = \App\Models\CrmContact::find($lead->crm_contact_id);
                    if ($contact) {
                        $contact->identities()->update([
                            'external_customer_id' => null, 'email' => null, 'phone' => null,
                            'normalized_phone' => null, 'name' => null, 'normalized_name' => null,
                            'tax_number' => null, 'city' => null, 'district' => null, 'raw_payload' => null,
                        ]);
                        $contact->update([
                            'display_name' => self::PII_REDACTED . '-' . $contact->id,
                            'normalized_name' => null, 'primary_email' => null, 'primary_phone' => null,
                            'normalized_phone' => null, 'billing_tax_number' => null,
                            'city' => null, 'district' => null, 'meta_json' => null,
                        ]);
                    }
                }
                $lead->update([
                    'name_encrypted' => self::PII_REDACTED,
                    'email_encrypted' => self::PII_REDACTED,
                    'phone_encrypted' => self::PII_REDACTED,
                    'purpose_encrypted' => self::PII_REDACTED,
                    'conversation_summary_encrypted' => self::PII_REDACTED,
                    'campaign' => null,
                    'idempotency_key_hash' => hash('sha256', 'kvkk-lead-' . $store->id . '-' . $lead->id),
                    'status' => 'anonymized',
                ]);
                $stats['web_leads_redacted']++;
            }
            \App\Models\SupportWidgetSession::whereHas('channel', fn ($query) => $query->where('store_id', $store->id))
                ->where(function ($query) use ($heldConversationIds): void {
                    $query->whereNull('conversation_id')
                        ->orWhereNotIn('conversation_id', $heldConversationIds);
                })
                ->get()->each(function ($session) use (&$stats): void {
                    $session->update(['metadata_json' => null, 'status' => 'anonymized']);
                    $stats['widget_sessions_redacted']++;
                });

            // 2. WaContact — PII alanları anonymize (audit ilişkileri korunur, excluding legal holds)
            $waContactsCollection = WaContact::where('store_id', $store->id)
                ->where(function ($q) use ($holdCustomerIds) {
                    $q->whereNotIn('wc_customer_id', $holdCustomerIds)
                      ->orWhereNull('wc_customer_id');
                })
                ->where(function ($q) use ($holdCustomerIds) {
                    $q->whereNotIn('zolm_customer_id', $holdCustomerIds)
                      ->orWhereNull('zolm_customer_id');
                })
                ->get();
            $waContactsAnonimized = 0;
            foreach ($waContactsCollection as $waContact) {
                $waContact->update([
                    'first_name' => self::PII_REDACTED,
                    'last_name' => self::PII_REDACTED,
                    // phone_e164_encrypted: şifreli PII placeholder (NOT NULL cast)
                    'phone_e164_encrypted' => self::PII_REDACTED,
                    // phone_hash: unique + NOT NULL — store+id ile benzersiz placeholder
                    'phone_hash' => 'kvkk-deleted-' . $store->id . '-' . $waContact->id,
                    'wc_customer_id' => null,
                    'zolm_customer_id' => null,
                ]);
                $waContactsAnonimized++;
            }
            $stats['wa_contacts_anonymized'] = $waContactsAnonimized;

            // 3. Agent Actions — PII audit işareti ekle (bütünlük korunur)
            // Eloquent JSON alanı ile uyumlu güncelleme
            $agentActionsRedacted = 0;
            $agentActions = SupportAgentAction::whereIn('conversation_id', $conversations)->get();
            foreach ($agentActions as $action) {
                $action->update(['details_json' => [
                    'pii_redacted' => true,
                    'original_action' => $action->action,
                ]]);
                $agentActionsRedacted++;
            }
            $stats['agent_actions_redacted'] = $agentActionsRedacted;

            DB::commit();

            Log::info('CustomerCare anonymization completed', [
                'store_id' => $store->id,
                'stats' => $stats,
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('CustomerCare anonymization failed', [
                'store_id' => $store->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        return $stats;
    }

    /**
     * Conversation bazlı anonymization — belirli bir konuşma için PII redaksiyonu.
     * Cross-store engellenir.
     */
    public function anonymizeConversation(
        int $conversationId,
        int $storeId,
        bool $dryRun = true,
        ?User $actor = null
    ): array
    {
        $actor = $actor ?? Auth::user() ?? TenantContext::getSystemActor();
        TenantContext::enforceStoreAccess($storeId, $actor);
        $conversation = SupportConversation::where('id', $conversationId)
            ->where('store_id', $storeId) // Cross-store engeli
            ->first();

        if (!$conversation) {
            throw new \InvalidArgumentException(
                "Konuşma bulunamadı veya bu mağazaya ait değil: {$conversationId}"
            );
        }

        if ($dryRun) {
            $msgCount = SupportMessage::where('conversation_id', $conversationId)->count();
            return [
                'dry_run' => true,
                'conversation_id' => $conversationId,
                'would_redact_messages' => $msgCount,
            ];
        }

        $customerId = $conversation->external_customer_id;
        if ($customerId && \App\Models\SupportLegalHold::where('store_id', $storeId)
            ->where('customer_hash', hash('sha256', (string) $customerId))
            ->where('active', true)
            ->exists()) {
            throw new \RuntimeException('Konuşmanın müşterisi aktif yasal koruma (legal hold) altındadır.');
        }

        $rbac = app(SupportRbacService::class);
        $rbac->enforcePermission($actor, $storeId, 'run_compliance');
        $rbac->enforceApproval($actor, $storeId, 'anonymize_conversation_' . $conversationId, [
            'store_id' => $storeId,
            'conversation_id' => $conversationId,
        ]);

        $redacted = 0;
        SupportMessage::where('conversation_id', $conversationId)->get()->each(function (SupportMessage $message) use (&$redacted): void {
            $message->update([
                'body_encrypted' => self::PII_REDACTED,
                'body_preview' => self::PII_REDACTED,
                'payload_json' => null,
            ]);
            $redacted++;
        });
        \App\Models\SupportAiRun::where('conversation_id', $conversationId)->get()->each(fn ($run) => $run->update([
            'prompt_raw' => self::PII_REDACTED,
            'response_raw' => self::PII_REDACTED,
        ]));
        $conversation->update(['external_customer_id' => self::PII_REDACTED . '-' . $conversation->id]);

        return [
            'dry_run' => false,
            'conversation_id' => $conversationId,
            'messages_redacted' => $redacted,
        ];
    }

    /**
     * Emergency stop: circuit breaker open olduğunda pending AI dispatch'leri iptal eder.
     * Manual reply'ler (sender_type=agent) etkilenmez.
     */
    public function cancelPendingAiDispatches(int $storeId, ?User $actor = null): int
    {
        $actor = $actor ?? Auth::user() ?? TenantContext::getSystemActor();
        TenantContext::enforceStoreAccess($storeId, $actor);
        app(SupportRbacService::class)->enforcePermission($actor, $storeId, 'force_circuit_breaker');

        // Yalnız AI gönderilecek dispatches'ları yükle
        $dispatches = \App\Models\SupportDispatch::whereIn('conversation_id', function ($q) use ($storeId) {
                $q->select('id')
                    ->from('support_conversations')
                    ->where('store_id', $storeId);
            })
            ->whereIn('status', ['pending', 'failed'])
            ->whereHas('message', fn($q) => $q->where('sender_type', 'ai'))
            ->get();

        $cancelled = 0;
        foreach ($dispatches as $dispatch) {
            $dispatch->update([
                'status' => 'cancelled',
                'last_error' => 'Devre kesici (Circuit Breaker) açık — AI gönderimi iptal edildi.',
            ]);

            if ($dispatch->message) {
                $dispatch->message->update([
                    'delivery_status' => 'cancelled',
                ]);
            }

            // Emergency stop audit izi
            \App\Models\SupportAgentAction::create([
                'conversation_id' => $dispatch->conversation_id,
                'user_id' => $actor->id,
                'action' => 'circuit_breaker_cancel',
                'details_json' => [
                    'dispatch_id' => $dispatch->id,
                    'message_id' => $dispatch->message_id,
                    'store_id' => $storeId,
                    'reason' => 'Devre kesici (Circuit Breaker) açık — AI gönderimi iptal edildi.'
                ],
            ]);

            $cancelled++;
        }

        Log::warning('AI dispatches cancelled due to circuit breaker', [
            'store_id' => $storeId,
            'cancelled_count' => $cancelled,
        ]);

        return $cancelled;
    }
}
