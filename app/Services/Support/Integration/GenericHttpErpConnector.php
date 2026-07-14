<?php

namespace App\Services\Support\Integration;

class GenericHttpErpConnector implements GenericErpConnectorInterface
{
    public function __construct(private CustomerCareHttpConnector $http, private CustomerCareIntegrationHubService $hub)
    {
    }

    public function pushOrder(int $storeId, array $orderData, ?string $idempotencyKey = null): array
    {
        if ($storeId <= 0 || empty($orderData['order_number'])) {
            throw new \InvalidArgumentException('ERP aktarımı için mağaza ve sipariş numarası zorunludur.');
        }
        $connection = $this->http->connection($storeId, 'erp');
        $credentials = $connection->credentials_encrypted ?? [];
        $idempotencyKey ??= 'erp-order-' . hash('sha256', $storeId . ':' . $orderData['order_number']);
        $event = $this->hub->queueConnectorOperation($storeId, 'erp', (string) ($credentials['orders_path'] ?? '/v1/orders'), [
            'schema_version' => '1.0',
            'store_id' => $storeId,
            'order' => $orderData,
        ], $idempotencyKey);

        return ['success' => true, 'queued' => true, 'event_id' => $event->event_id];
    }
}
