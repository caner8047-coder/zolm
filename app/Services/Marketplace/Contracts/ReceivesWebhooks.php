<?php

namespace App\Services\Marketplace\Contracts;

use App\Models\IntegrationConnection;
use Illuminate\Http\Request;

interface ReceivesWebhooks
{
    public function verifyWebhookSignature(Request $request, ?IntegrationConnection $connection): bool;

    /**
     * @return array{event_type: string|null, external_event_id: string|null, payload: array<string, mixed>}
     */
    public function extractWebhookMetadata(Request $request): array;
}
