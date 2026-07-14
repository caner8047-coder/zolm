<?php

namespace App\Services\Support\Integration;

interface GenericErpConnectorInterface
{
    public function pushOrder(int $storeId, array $orderData, ?string $idempotencyKey = null): array;
}
