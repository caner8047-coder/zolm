<?php

namespace App\Services\WhatsApp;

use App\Models\ChannelListing;
use App\Models\MpProduct;
use App\Models\WaAutomationConfig;
use App\Models\WaOutbox;
use App\Models\WaStockWaitlist;
use Illuminate\Support\Facades\Log;

class StockAlertService
{
    /**
     * Stok 0'dan pozitif değere geçtiğinde çağrılır
     */
    public function onStockAvailable(MpProduct $product, int $newQuantity): void
    {
        $config = WaAutomationConfig::get('stock_alert', [
            'enabled' => false,
            'batch_size' => 10,
            'minimum_sellable_quantity' => 1,
            'template_id' => null,
            'quiet_hours_enabled' => true,
        ]);

        if (empty($config['enabled'])) {
            return;
        }

        $minimumSellable = $config['minimum_sellable_quantity'] ?? 1;
        if ($newQuantity < $minimumSellable) {
            return;
        }

        // WC ürün eşlemesini bul
        $listings = ChannelListing::where('mp_product_id', $product->id)
            ->whereHas('store', fn ($q) => $q->where('marketplace', 'woocommerce')->where('is_active', true))
            ->with('store')
            ->get();

        if ($listings->isEmpty()) {
            return;
        }

        foreach ($listings as $listing) {
            $this->processWaitlistForListing($listing, $newQuantity, $config);
        }
    }

    private function processWaitlistForListing(ChannelListing $listing, int $stockQuantity, array $config): void
    {
        $storeId = $listing->store_id;
        $wcProductId = $listing->listing_id;

        // Bu ürün/varyasyon için bekleyen kayıtları FIFO sırayla al
        $waitlistEntries = WaStockWaitlist::where('store_id', $storeId)
            ->where('wc_product_id', $wcProductId)
            ->waiting()
            ->orderBy('requested_at', 'asc')
            ->limit($config['batch_size'] ?? 10)
            ->with('contact')
            ->get();

        $remainingStock = $stockQuantity;

        foreach ($waitlistEntries as $entry) {
            if ($remainingStock <= 0) {
                break;
            }

            if (!$entry->contact) {
                $entry->update(['status' => WaStockWaitlist::STATUS_CANCELLED]);
                continue;
            }

            // Eligibility kontrolü
            $eligibleService = app(EligibilityService::class);
            if (!$eligibleService->isEligibleForMessaging($entry->contact, 'stock_alert')) {
                continue;
            }

            // Template seçimi
            $templateId = $config['template_id'] ?? null;
            if (!$templateId) {
                continue;
            }

            $template = \App\Models\WaTemplate::where('id', $templateId)->approved()->first();
            if (!$template) {
                continue;
            }

            // Ürün bilgilerini al
            $productName = $listing->channelProduct->title ?? 'Ürün';

            // Template parametreleri
            $templateParams = [
                'product_name' => $productName,
                'product_url' => $listing->raw_payload['permalink'] ?? '#',
            ];

            try {
                $outboxService = app(OutboxService::class);
                $outbox = $outboxService->enqueue(
                    contact: $entry->contact,
                    messageType: 'template',
                    templateName: $template->name,
                    templateLanguage: $template->language,
                    templateParams: $templateParams,
                    priority: 'normal',
                    automationKey: 'stock_alert',
                    idempotencyKey: "stock_alert:{$entry->store_id}:{$entry->id}",
                );

                $entry->update([
                    'status' => WaStockWaitlist::STATUS_NOTIFIED,
                    'notified_at' => now(),
                    'notified_outbox_id' => $outbox->id,
                    'available_stock_snapshot' => $stockQuantity,
                ]);

                $remainingStock--;

                app(AuditLogService::class)->log(
                    'stock_alert_sent',
                    'wa_stock_waitlist',
                    $entry->id,
                    ['product_id' => $wcProductId, 'stock' => $stockQuantity],
                );
            } catch (\Illuminate\Database\QueryException $e) {
                if ($e->errorInfo[1] ?? 0 === 1062) {
                    continue;
                }
                throw $e;
            }
        }
    }

    /**
     * Sipariş oluşunca waitlist'i converted yap
     */
    public function onOrderCreated(array $payload): void
    {
        $storeId = (int) ($payload['store_id'] ?? 0);
        $wcProductIds = $payload['wc_product_ids'] ?? [];
        $orderId = $payload['order_id'] ?? null;

        if (empty($wcProductIds) || !$orderId) {
            return;
        }

        WaStockWaitlist::where('store_id', $storeId)
            ->whereIn('wc_product_id', $wcProductIds)
            ->waiting()
            ->update([
                'status' => WaStockWaitlist::STATUS_CONVERTED,
                'related_order_id' => $orderId,
            ]);
    }
}
