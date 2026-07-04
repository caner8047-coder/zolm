<?php

namespace App\Services\WhatsApp;

use App\Models\WaExternalIntegration;

interface IntegrationAdapterInterface
{
    public function key(): string;
    public function name(): string;
    public function healthCheck(?WaExternalIntegration $integration): array;
    public function sync(WaExternalIntegration $integration, array $payload): array;
    public function canSend(): bool;
}
