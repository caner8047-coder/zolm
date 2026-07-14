<?php

namespace App\Services\Support\Integration;

use App\Models\SupportConversation;

class GenericHttpCrmConnector implements GenericCrmConnectorInterface
{
    public function __construct(private CustomerCareHttpConnector $http, private CustomerCareIntegrationHubService $hub)
    {
    }

    public function syncContact(array $contactData): array
    {
        $storeId = (int) ($contactData['store_id'] ?? 0);
        if ($storeId <= 0) {
            throw new \InvalidArgumentException('CRM kişi senkronizasyonu için store_id zorunludur.');
        }
        unset($contactData['store_id']);
        $connection = $this->http->connection($storeId, 'crm');
        $credentials = $connection->credentials_encrypted ?? [];
        $key = 'crm-contact-' . hash('sha256', json_encode([$storeId, $contactData['external_id'] ?? $contactData['email_hash'] ?? $contactData]));
        $event = $this->hub->queueConnectorOperation($storeId, 'crm', (string) ($credentials['contacts_path'] ?? '/v1/contacts'), [
            'schema_version' => '1.0',
            'store_id' => $storeId,
            'contact' => $contactData,
        ], $key);

        return ['success' => true, 'queued' => true, 'event_id' => $event->event_id];
    }

    public function syncConversation(SupportConversation $conversation): array
    {
        $connection = $this->http->connection((int) $conversation->store_id, 'crm');
        $credentials = $connection->credentials_encrypted ?? [];
        $key = 'crm-conv-' . hash('sha256', $conversation->store_id . ':' . $conversation->id . ':' . $conversation->updated_at?->timestamp);
        $event = $this->hub->queueConnectorOperation((int) $conversation->store_id, 'crm', (string) ($credentials['conversations_path'] ?? '/v1/conversations'), [
            'schema_version' => '1.0',
            'store_id' => $conversation->store_id,
            'conversation' => [
                'id' => $conversation->id,
                'external_id' => $conversation->external_conversation_id,
                'customer_hash' => $conversation->external_customer_hash,
                'source_type' => $conversation->source_type,
                'status' => $conversation->status,
                'priority' => $conversation->priority,
                'last_message_at' => $conversation->last_message_at?->toIso8601String(),
            ],
        ], $key);

        return ['success' => true, 'queued' => true, 'event_id' => $event->event_id];
    }
}
