<?php

namespace App\Http\Controllers;

use App\Models\IntegrationWebhookEvent;
use App\Models\MarketplaceStore;
use App\Services\Marketplace\Contracts\ReceivesWebhooks;
use App\Services\Marketplace\MarketplaceConnectorManager;
use App\Services\Marketplace\MarketplaceWebhookDispatchService;
use App\Services\Marketplace\MarketplaceProviderRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketplaceWebhookController extends Controller
{
    public function handle(
        Request $request,
        string $provider,
        MarketplaceStore $store,
        MarketplaceConnectorManager $connectorManager,
        MarketplaceWebhookDispatchService $webhookDispatchService,
    ): JsonResponse {
        $normalizedProvider = MarketplaceProviderRegistry::normalize($provider);

        if ($normalizedProvider !== MarketplaceProviderRegistry::normalize($store->marketplace)) {
            abort(404);
        }

        $connector = $connectorManager->resolve($normalizedProvider);
        $metadata = $connector instanceof ReceivesWebhooks
            ? $connector->extractWebhookMetadata($request)
            : [
                'event_type' => null,
                'external_event_id' => null,
                'payload' => $request->json()->all() ?: $request->all(),
            ];

        $signatureValid = $connector instanceof ReceivesWebhooks
            ? $connector->verifyWebhookSignature($request, $store->connection)
            : false;

        $externalEventId = $this->resolveWebhookEventId(
            $normalizedProvider,
            $store->id,
            trim((string) ($metadata['external_event_id'] ?? ''))
        );

        $event = $externalEventId !== ''
            ? IntegrationWebhookEvent::firstOrNew([
                'provider' => $normalizedProvider,
                'external_event_id' => $externalEventId,
            ])
            : new IntegrationWebhookEvent([
                'store_id' => $store->id,
                'provider' => $normalizedProvider,
                'external_event_id' => null,
            ]);
        $alreadyExists = $event->exists;

        $event->fill([
            'store_id' => $store->id,
            'event_type' => $metadata['event_type'],
            'signature_valid' => $signatureValid,
            'payload_json' => $metadata['payload'],
            'received_at' => $event->received_at ?? now(),
            'status' => $signatureValid ? 'received' : 'needs_review',
            'error_message' => $signatureValid ? null : 'Webhook imzasi doğrulanamadı.',
        ]);
        $event->save();

        $syncRun = null;

        if (!$signatureValid) {
            app(\App\Services\NotificationCenterService::class)->createForStore($store, [
                'type' => 'integration_failed',
                'severity' => 'critical',
                'event_key' => "webhook-signature-failed:{$event->id}",
                'title' => 'Webhook imzası doğrulanamadı',
                'body' => implode(' · ', array_values(array_filter([
                    strtoupper($normalizedProvider),
                    $event->event_type,
                ]))),
                'subject_type' => get_class($event),
                'subject_id' => $event->id,
                'data_json' => [
                    'webhook_event_id' => $event->id,
                    'event_type' => $event->event_type,
                    'provider' => $normalizedProvider,
                ],
                'action_url' => route('mp.overview'),
            ]);
        }

        if ($signatureValid && !$alreadyExists) {
            $syncRun = $webhookDispatchService->dispatchForEvent($store, $event);
        }

        return response()->json([
            'ok' => true,
            'provider' => $normalizedProvider,
            'store_id' => $store->id,
            'signature_valid' => $signatureValid,
            'event_id' => $event->id,
            'status' => $event->status,
            'sync_type' => $syncRun?->sync_type,
            'sync_run_id' => $syncRun?->id,
            'sync_debounced' => $event->status === 'debounced',
            'sync_ignored' => $event->status === 'ignored',
        ]);
    }

    protected function resolveWebhookEventId(string $provider, int $storeId, string $externalEventId): string
    {
        if ($externalEventId === '') {
            return '';
        }

        $storeScopedEventId = $externalEventId . '::store:' . $storeId;

        $existingStoreEvent = IntegrationWebhookEvent::query()
            ->where('provider', $provider)
            ->where('store_id', $storeId)
            ->whereIn('external_event_id', [$externalEventId, $storeScopedEventId])
            ->first();

        if ($existingStoreEvent) {
            return (string) $existingStoreEvent->external_event_id;
        }

        $crossStoreCollisionExists = IntegrationWebhookEvent::query()
            ->where('provider', $provider)
            ->where('external_event_id', $externalEventId)
            ->where(function ($query) use ($storeId) {
                $query->whereNull('store_id')
                    ->orWhere('store_id', '!=', $storeId);
            })
            ->exists();

        return $crossStoreCollisionExists ? $storeScopedEventId : $externalEventId;
    }
}
