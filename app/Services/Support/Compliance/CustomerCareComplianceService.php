<?php

namespace App\Services\Support\Compliance;

use App\Models\SupportDataSubjectRequest;
use App\Models\SupportLegalHold;
use App\Models\SupportDataLineageEvent;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\SupportAgentAction;
use App\Models\SupportConsentRecord;
use App\Models\SupportWebLead;
use App\Models\SupportWidgetSession;
use App\Models\WaContact;
use App\Models\CrmContact;
use App\Models\User;
use App\Services\Support\TenantContext;
use App\Services\Support\Security\PiiRedactor;
use App\Services\Support\Security\SupportRbacService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Auth\Access\AuthorizationException;

class CustomerCareComplianceService
{
    private PiiRedactor $redactor;

    public function __construct(PiiRedactor $redactor)
    {
        $this->redactor = $redactor;
    }

    /**
     * Müşterinin legal hold (veri saklama emri) altında olup olmadığını kontrol eder.
     */
    public function isUnderLegalHold(int $storeId, string $customerId): bool
    {
        return SupportLegalHold::where('store_id', $storeId)
            ->where('customer_hash', hash('sha256', $customerId))
            ->where('active', true)
            ->exists();
    }

    /**
     * DSR (Kişisel Veri) talebi işler.
     */
    public function processDsrRequest(
        int $storeId,
        string $customerId,
        string $requestType,
        array $details = [],
        ?User $actor = null
    ): array
    {
        if (!in_array($requestType, ['export', 'rectification', 'anonymize', 'delete'], true)) {
            throw new \InvalidArgumentException('Geçersiz DSR talep tipi.');
        }

        $actor = $actor ?? Auth::user() ?? TenantContext::getSystemActor();
        TenantContext::enforceStoreAccess($storeId, $actor);
        $rbac = app(SupportRbacService::class);
        $rbac->enforcePermission($actor, $storeId, 'run_compliance');

        if ($requestType === 'anonymize' || $requestType === 'delete') {
            // Legal hold kontrolü (yasal hold altındaysa işlem engellenir)
            if ($this->isUnderLegalHold($storeId, $customerId)) {
                SupportDataSubjectRequest::create([
                    'store_id' => $storeId,
                    'customer_id' => $customerId,
                    'request_type' => $requestType,
                    'details_json' => $details,
                    'status' => 'failed',
                    'requested_at' => now(),
                    'completed_at' => now(),
                ]);

                // Audit loguna kaydet
                SupportAgentAction::create([
                    'conversation_id' => null,
                    'user_id' => $actor->id,
                    'action' => 'compliance_block',
                    'details_json' => [
                        'reason' => 'Legal Hold Active: Veri silme/anonimleştirme engellendi.',
                        'customer_id_hash' => hash('sha256', $customerId),
                        'store_id' => $storeId,
                    ]
                ]);

                throw new \RuntimeException("Müşteri yasal takip (legal hold) altındadır. Veri silme/anonimleştirme işlemi gerçekleştirilemez.");
            }
        }

        $approvalRequest = null;
        if (in_array($requestType, ['export', 'anonymize', 'delete'], true)) {
            $approvalRequest = $rbac->enforceApproval($actor, $storeId, 'dsr_' . $requestType, [
                'customer_hash' => hash('sha256', $customerId),
            ]);
        }

        $dsr = SupportDataSubjectRequest::create([
            'store_id' => $storeId,
            'customer_id' => $customerId,
            'request_type' => $requestType,
            'approval_request_id' => $approvalRequest?->id,
            'details_json' => $details,
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        if ($requestType === 'anonymize' || $requestType === 'delete') {
            $dsr->update(['status' => 'processing']);

            try {
                $this->executeDataErasure($storeId, $customerId);
                $dsr->update(['status' => 'completed', 'completed_at' => now()]);

                SupportAgentAction::create([
                    'conversation_id' => null,
                    'user_id' => $actor->id,
                    'action' => 'dsr_erasure_completed',
                    'details_json' => [
                        'store_id' => $storeId,
                        'dsr_id' => $dsr->id,
                        'request_type' => $requestType,
                        'customer_id_hash' => hash('sha256', $customerId),
                    ],
                ]);
            } catch (\Throwable $exception) {
                $dsr->update(['status' => 'failed', 'completed_at' => now()]);

                SupportAgentAction::create([
                    'conversation_id' => null,
                    'user_id' => $actor->id,
                    'action' => 'dsr_erasure_failed',
                    'details_json' => [
                        'store_id' => $storeId,
                        'dsr_id' => $dsr->id,
                        'request_type' => $requestType,
                        'customer_id_hash' => hash('sha256', $customerId),
                        'error_type' => $exception::class,
                    ],
                ]);

                throw $exception;
            }

            return ['success' => true, 'message' => 'Veri başarıyla temizlendi / anonimleştirildi.'];
        }

        return ['success' => true, 'dsr_id' => $dsr->id, 'status' => 'pending'];
    }

    /**
     * Belirli bir müşteri için tüm verileri siler/redakte eder (cascade-delete engeli ve audit korumalı).
     */
    protected function executeDataErasure(int $storeId, string $customerId): void
    {
        DB::beginTransaction();
        try {
            $customerHash = hash('sha256', $customerId);
            $conversations = SupportConversation::where('store_id', $storeId)
                ->where('external_customer_hash', $customerHash)
                ->get();
            $conversationIds = $conversations->pluck('id');

            foreach ($conversations as $conv) {
                SupportMessage::where('conversation_id', $conv->id)->get()->each(function (SupportMessage $message): void {
                    $attachmentPath = data_get($message->payload_json, 'encrypted_path');
                    if (is_string($attachmentPath) && str_starts_with($attachmentPath, 'customer-care/attachments/')) {
                        Storage::disk('local')->delete($attachmentPath);
                    }

                    $message->update([
                        'body_encrypted' => '[KVKK-SİLİNDİ]',
                        'body_preview' => '[KVKK-SİLİNDİ]',
                        'payload_json' => null,
                    ]);
                });

                \App\Models\SupportAiRun::where('conversation_id', $conv->id)->get()->each(function ($run): void {
                    $run->update([
                        'prompt_raw' => '[KVKK-SİLİNDİ]',
                        'response_raw' => '[KVKK-SİLİNDİ]',
                    ]);
                });

                SupportAgentAction::where('conversation_id', $conv->id)->get()
                    ->each(function (SupportAgentAction $action): void {
                        $action->update(['details_json' => [
                            'pii_redacted' => true,
                            'original_action' => $action->action,
                        ]]);
                    });

                $conv->update(['external_customer_id' => '[KVKK-SİLİNDİ]-' . $conv->id]);
            }

            $webLeads = SupportWebLead::where('store_id', $storeId)
                ->whereIn('conversation_id', $conversationIds)
                ->get();
            $webLeadIds = $webLeads->pluck('id');

            foreach ($webLeads as $lead) {
                if ($lead->crm_contact_id && !SupportWebLead::where('crm_contact_id', $lead->crm_contact_id)
                    ->whereNotIn('id', $webLeadIds)
                    ->exists()) {
                    $contact = CrmContact::find($lead->crm_contact_id);
                    if ($contact) {
                        $contact->identities()->update([
                            'external_customer_id' => null,
                            'email' => null,
                            'phone' => null,
                            'normalized_phone' => null,
                            'name' => null,
                            'normalized_name' => null,
                            'tax_number' => null,
                            'city' => null,
                            'district' => null,
                            'raw_payload' => null,
                        ]);
                        $contact->update([
                            'display_name' => '[KVKK-SİLİNDİ]-' . $contact->id,
                            'normalized_name' => null,
                            'primary_email' => null,
                            'primary_phone' => null,
                            'normalized_phone' => null,
                            'billing_tax_number' => null,
                            'city' => null,
                            'district' => null,
                            'meta_json' => null,
                        ]);
                    }
                }

                $lead->update([
                    'name_encrypted' => '[KVKK-SİLİNDİ]',
                    'email_encrypted' => '[KVKK-SİLİNDİ]',
                    'phone_encrypted' => '[KVKK-SİLİNDİ]',
                    'purpose_encrypted' => '[KVKK-SİLİNDİ]',
                    'conversation_summary_encrypted' => '[KVKK-SİLİNDİ]',
                    'campaign' => null,
                    'idempotency_key_hash' => hash('sha256', 'kvkk-lead-' . $storeId . '-' . $lead->id),
                    'status' => 'anonymized',
                ]);
            }

            SupportWidgetSession::whereIn('conversation_id', $conversationIds)->get()
                ->each(fn (SupportWidgetSession $session) => $session->update([
                    'metadata_json' => null,
                    'status' => 'anonymized',
                ]));

            WaContact::where('store_id', $storeId)
                ->where(function ($query) use ($customerId): void {
                    $query->where('wc_customer_id', $customerId);
                    if (ctype_digit($customerId)) {
                        $query->orWhere('zolm_customer_id', (int) $customerId);
                    }
                })
                ->get()
                ->each(function (WaContact $contact) use ($storeId): void {
                    $contact->update([
                        'wc_customer_id' => null,
                        'zolm_customer_id' => null,
                        'phone_e164_encrypted' => '[KVKK-SİLİNDİ]',
                        'phone_hash' => 'kvkk-deleted-' . $storeId . '-' . $contact->id,
                        'first_name' => '[KVKK-SİLİNDİ]',
                        'last_name' => '[KVKK-SİLİNDİ]',
                        'status' => 'anonymized',
                    ]);
                });

            SupportConsentRecord::where('store_id', $storeId)
                ->where('customer_hash', $customerHash)
                ->get()
                ->each(fn (SupportConsentRecord $record) => $record->update([
                    'customer_id' => '[KVKK-SİLİNDİ]-' . $record->id,
                ]));

            DB::commit();

            // Log data lineage event
            $this->logLineageEvent($storeId, $customerId, null, 'data_erasure', 'support_conversations', null);

        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Data Subject Access Request (Veri Çıkarma) raporunu XML-safe ve UTF-8 formatında üretir.
     */
    public function generateAccessExport(
        int $storeId,
        string $customerId,
        int $dsrId,
        ?User $actor = null
    ): string
    {
        $actor = $actor ?? Auth::user() ?? TenantContext::getSystemActor();
        TenantContext::enforceStoreAccess($storeId, $actor);
        app(SupportRbacService::class)->enforcePermission($actor, $storeId, 'run_compliance');

        $customerHash = hash('sha256', $customerId);
        $dsr = DB::transaction(function () use ($storeId, $customerHash, $dsrId): SupportDataSubjectRequest {
            $request = SupportDataSubjectRequest::with('approvalRequest')
                ->where('store_id', $storeId)
                ->where('request_type', 'export')
                ->where('customer_hash', $customerHash)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->find($dsrId);
            $approval = $request?->approvalRequest;
            if (!$request || !$approval || $approval->status !== 'consumed'
                || $approval->action_type !== 'dsr_export'
                || ($approval->details_json['customer_hash'] ?? null) !== $customerHash) {
                throw new AuthorizationException('Veri export’u için bağlı, tüketilmiş ve kullanılmamış yönetişim onayı bulunamadı.');
            }

            $request->update(['status' => 'processing']);

            return $request;
        });

        try {
            $conversations = SupportConversation::where('store_id', $storeId)
                ->where('external_customer_hash', $customerHash)
                ->with('messages')
                ->get();

            $conversationData = [];
            foreach ($conversations as $conv) {
                $messages = [];
                foreach ($conv->messages as $msg) {
                    $cleanBody = $this->cleanXmlString($msg->body_encrypted);
                    $messages[] = [
                        'direction' => $msg->direction,
                        'sender_type' => $msg->sender_type,
                        'body' => $cleanBody,
                        'sent_at' => $msg->sent_at?->toIso8601String(),
                    ];
                }
                $conversationData[] = [
                    'conversation_id' => $conv->id,
                    'external_conversation_id' => $this->cleanXmlString($conv->external_conversation_id),
                    'channel' => $conv->source_type,
                    'status' => $conv->status,
                    'messages' => $messages,
                ];
            }

            $conversationIds = $conversations->pluck('id');
            $webLeads = SupportWebLead::where('store_id', $storeId)
                ->whereIn('conversation_id', $conversationIds)
                ->get()
                ->map(fn (SupportWebLead $lead): array => [
                    'lead_id' => $lead->id,
                    'conversation_id' => $lead->conversation_id,
                    'name' => $this->cleanXmlString($lead->name_encrypted),
                    'email' => $this->cleanXmlString($lead->email_encrypted),
                    'phone' => $this->cleanXmlString($lead->phone_encrypted),
                    'purpose' => $this->cleanXmlString($lead->purpose_encrypted),
                    'lead_source' => $lead->lead_source,
                    'campaign' => $this->cleanXmlString($lead->campaign),
                    'conversation_summary' => $this->cleanXmlString($lead->conversation_summary_encrypted),
                    'consent_basis' => $lead->consent_basis,
                    'marketing_consent_granted' => (bool) $lead->marketing_consent_granted,
                    'consented_at' => $lead->consented_at?->toIso8601String(),
                    'marketing_consented_at' => $lead->marketing_consented_at?->toIso8601String(),
                    'status' => $lead->status,
                ])->all();

            $widgetSessions = SupportWidgetSession::whereIn('conversation_id', $conversationIds)
                ->get()
                ->map(fn (SupportWidgetSession $session): array => [
                    'session_id' => $session->id,
                    'conversation_id' => $session->conversation_id,
                    'origin' => $this->cleanXmlString($session->origin),
                    'consent_granted' => (bool) $session->consent_granted,
                    'marketing_consent_granted' => (bool) $session->marketing_consent_granted,
                    'privacy_notice_version' => $session->privacy_notice_version,
                    'marketing_notice_version' => $session->marketing_notice_version,
                    'consented_at' => $session->consented_at?->toIso8601String(),
                    'marketing_consented_at' => $session->marketing_consented_at?->toIso8601String(),
                    'metadata' => $this->cleanExportValue($session->metadata_json),
                    'status' => $session->status,
                ])->all();

            $consents = SupportConsentRecord::where('store_id', $storeId)
                ->where('customer_hash', $customerHash)
                ->get()
                ->map(fn (SupportConsentRecord $record): array => [
                    'consent_id' => $record->id,
                    'channel_key' => $record->channel_key,
                    'consent_type' => $record->consent_type,
                    'status' => $record->status,
                    'recorded_at' => $record->recorded_at?->toIso8601String(),
                ])->all();

            $whatsAppContacts = WaContact::with(['preferences', 'consentEvents'])
                ->where('store_id', $storeId)
                ->where(function ($query) use ($customerId): void {
                    $query->where('wc_customer_id', $customerId);
                    if (ctype_digit($customerId)) {
                        $query->orWhere('zolm_customer_id', (int) $customerId);
                    }
                })
                ->get()
                ->map(fn (WaContact $contact): array => [
                    'contact_id' => $contact->id,
                    'phone' => $this->cleanXmlString($contact->phone_e164_encrypted),
                    'first_name' => $this->cleanXmlString($contact->first_name),
                    'last_name' => $this->cleanXmlString($contact->last_name),
                    'status' => $contact->status,
                    'preferences' => $contact->preferences->map(fn ($preference): array => [
                        'purpose' => $preference->purpose,
                        'status' => $preference->status,
                    ])->all(),
                    'consent_events' => $contact->consentEvents->map(fn ($event): array => [
                        'purpose' => $event->purpose,
                        'action' => $event->action,
                        'source' => $event->source,
                        'consent_text_version' => $event->consent_text_version,
                        'consent_timestamp' => $event->consent_timestamp?->toIso8601String(),
                        'ip_address' => $this->cleanXmlString($event->ip_address),
                        'user_agent' => $this->cleanXmlString($event->user_agent),
                    ])->all(),
                ])->all();

            $exportData = [
                'schema_version' => '1.0',
                'request_reference' => 'dsr-' . $dsr->id,
                'customer_reference_hash' => $customerHash,
                'conversations' => $conversationData,
                'web_leads' => $webLeads,
                'widget_sessions' => $widgetSessions,
                'consents' => $consents,
                'whatsapp_contacts' => $whatsAppContacts,
            ];

            $json = json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $dsr->update(['status' => 'completed', 'completed_at' => now()]);
            SupportAgentAction::create([
                'conversation_id' => null,
                'user_id' => $actor->id,
                'action' => 'dsr_export_downloaded',
                'details_json' => [
                    'dsr_id' => $dsr->id,
                    'customer_id_hash' => $customerHash,
                    'store_id' => $storeId,
                ],
            ]);

            return "\xEF\xBB\xBF" . $json;
        } catch (\Throwable $exception) {
            $dsr->update(['status' => 'failed', 'completed_at' => now()]);
            SupportAgentAction::create([
                'conversation_id' => null,
                'user_id' => $actor->id,
                'action' => 'dsr_export_failed',
                'details_json' => [
                    'dsr_id' => $dsr->id,
                    'customer_id_hash' => $customerHash,
                    'store_id' => $storeId,
                    'error_type' => $exception::class,
                ],
            ]);

            throw $exception;
        }
    }

    /**
     * XML kontrol karakterlerini temizler.
     */
    protected function cleanXmlString(?string $value): string
    {
        if (blank($value)) {
            return '';
        }
        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
    }

    private function cleanExportValue(mixed $value): mixed
    {
        if (is_string($value)) {
            return $this->cleanXmlString($value);
        }
        if (is_array($value)) {
            return array_map(fn (mixed $item): mixed => $this->cleanExportValue($item), $value);
        }

        return $value;
    }

    /**
     * Data lineage (veri soy ağacı) olay kaydı oluşturur.
     */
    public function logLineageEvent(int $storeId, ?string $customerId, ?int $messageId, string $actionType, string $targetType, ?int $targetId): void
    {
        $hashedCustomerId = $customerId ? hash('sha256', $customerId) : 'unknown';
        SupportDataLineageEvent::create([
            'store_id' => $storeId,
            'customer_id' => $hashedCustomerId,
            'message_id' => $messageId,
            'action_type' => $actionType,
            'target_type' => $targetType,
            'target_id' => $targetId,
        ]);
    }
}
