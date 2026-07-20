<?php

namespace App\Jobs;

use App\Models\ChannelListing;
use App\Models\IntegrationPushRun;
use App\Models\MpPriceAction;
use App\Services\Marketplace\Contracts\PushesPrice;
use App\Services\Marketplace\MarketplaceConnectorManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class PushMarketplacePriceActionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public int $tries = 3;

    public function __construct(public int $priceActionId)
    {
        $this->onQueue((string) config('marketplace.queues.listing_push', 'default'));
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("mp-price-action:{$this->priceActionId}"))
                ->releaseAfter(60)
                ->expireAfter(600),
        ];
    }

    public function handle(MarketplaceConnectorManager $connectorManager): void
    {
        $action = MpPriceAction::with(['store', 'recommendation'])->find($this->priceActionId);

        if (! $action || in_array($action->status, ['success', 'cancelled', 'rolled_back'], true)) {
            return;
        }

        // Execution-Time Revalidation Guard
        $revalidator = app(\App\Services\Marketplace\MarketplacePriceActionRevalidatorService::class);
        if (! $revalidator->revalidateAtExecution($action)) {
            Log::warning('[PushMarketplacePriceActionJob] Revalidation failed, action blocked.', [
                'action_id' => $action->id,
                'status' => $action->status,
            ]);

            return;
        }

        // Dry-run mode interceptor
        $dryRun = config('marketplace.trendyol.dry_run_enabled') || env('TRENDYOL_PRICE_CANARY_DRY_RUN_ENABLED', false);
        if ($dryRun) {
            $action->update([
                'status' => 'dry_run_completed',
                'response_payload' => ['dry_run' => true, 'message' => 'Dry run simulation completed successfully. No price sent to Trendyol.'],
                'completed_at' => now(),
            ]);

            if ($action->recommendation) {
                $action->recommendation->update(['status' => 'success']);
            }

            Log::info('[PushMarketplacePriceActionJob] DRY-RUN: Fiyat push simülasyonu tamamlandı', [
                'action_id' => $action->id,
                'barcode' => $action->barcode,
                'requested_price' => $action->requested_price,
            ]);

            return;
        }

        $store = $action->store;
        if (! $store) {
            $action->update([
                'status' => 'failed',
                'failure_code' => 'STORE_NOT_FOUND',
                'failure_message' => 'Mağaza bulunamadı.',
            ]);

            return;
        }

        // Locate ChannelListing by barcode
        $listing = ChannelListing::where('store_id', $store->id)
            ->whereHas('channelProduct', fn ($q) => $q->where('barcode', $action->barcode))
            ->with(['channelProduct'])
            ->first();

        if (! $listing) {
            $action->update([
                'status' => 'failed',
                'failure_code' => 'LISTING_NOT_FOUND',
                'failure_message' => "Barkod ({$action->barcode}) için pazaryeri ilanı bulunamadı.",
            ]);

            return;
        }

        $action->update(['status' => 'processing']);

        try {
            $connector = $connectorManager->resolveForStore($store);

            if (! ($connector instanceof PushesPrice)) {
                throw new \RuntimeException('Bu pazar yeri için fiyat gönderme desteklenmiyor.');
            }

            // Create or update IntegrationPushRun for batch tracking
            $pushRun = IntegrationPushRun::create([
                'store_id' => $store->id,
                'channel_listing_id' => $listing->id,
                'mp_product_id' => $action->recommendation?->marketplace_product_id,
                'triggered_by' => $action->approved_by ?: 1,
                'push_type' => 'price',
                'status' => 'processing',
                'target_price' => $action->requested_price,
                'currency' => 'TRY',
                'request_context_json' => [
                    'price_action_id' => $action->id,
                    'barcode' => $action->barcode,
                    'old_price' => $action->old_price,
                ],
            ]);

            $response = $connector->pushPrice(
                $listing,
                (float) $action->requested_price,
                ['price_action_id' => $action->id]
            );

            $batchId = data_get($response, 'batch_request_id');

            $pushRun->update([
                'status' => $batchId ? 'processing' : 'completed',
                'external_batch_id' => $batchId,
                'response_json' => $response,
                'finished_at' => $batchId ? null : now(),
            ]);

            $action->update([
                'integration_push_run_id' => $pushRun->id,
                'batch_request_id' => $batchId,
                'status' => $batchId ? 'processing' : 'success',
                'confirmed_price' => $batchId ? null : $action->requested_price,
                'request_payload' => ['price' => $action->requested_price, 'barcode' => $action->barcode],
                'response_payload' => $response,
                'completed_at' => $batchId ? null : now(),
            ]);

            if ($action->recommendation) {
                $action->recommendation->update([
                    'status' => $batchId ? 'sent' : 'success',
                ]);
            }

            if (! $batchId) {
                \App\Jobs\VerifyMarketplaceListingPriceJob::dispatch($action->id);
            }

            Log::info('[PushMarketplacePriceActionJob] Fiyat push gönderildi', [
                'action_id' => $action->id,
                'barcode' => $action->barcode,
                'requested_price' => $action->requested_price,
                'batch_id' => $batchId,
            ]);
        } catch (Throwable $e) {
            $action->update([
                'status' => 'failed',
                'failure_code' => 'API_ERROR',
                'failure_message' => $e->getMessage(),
            ]);

            if ($action->recommendation) {
                $action->recommendation->update(['status' => 'failed']);
            }

            // Auto-pause Canary mode on API error
            app(\App\Services\Marketplace\MarketplacePriceCanaryService::class)
                ->onStoreCanaryPause($action->store_id, "API Push Error: " . $e->getMessage());

            Log::error('[PushMarketplacePriceActionJob] Fiyat push hatası', [
                'action_id' => $action->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
