<?php

namespace App\Services\WhatsApp;

use App\Models\WaExternalIntegration;

class NullIntegrationAdapter implements IntegrationAdapterInterface
{
    public function key(): string { return 'unsupported'; }
    public function name(): string { return 'Desteklenmeyen Entegrasyon'; }

    public function healthCheck(?WaExternalIntegration $integration): array
    {
        return ['status' => 'unsupported', 'message' => 'Bu entegrasyon desteklenmiyor'];
    }

    public function sync(WaExternalIntegration $integration, array $payload): array
    {
        return ['synced' => 0, 'message' => 'Desteklenmeyen entegrasyon'];
    }

    public function canSend(): bool
    {
        return false;
    }
}
