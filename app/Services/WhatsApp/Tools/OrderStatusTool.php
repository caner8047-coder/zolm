<?php

namespace App\Services\WhatsApp\Tools;

use App\Models\ChannelOrder;
use App\Models\ChannelOrderPackage;
use App\Models\MarketplaceStore;
use App\Models\WaContact;

class OrderStatusTool implements AiTool
{
    public function name(): string { return 'order_status'; }

    public function description(): string
    {
        return 'Müşterinin sipariş durumunu ve kargo bilgisini güvenli biçimde sorgular.';
    }

    public function execute(array $params, int $storeId, ?int $contactId = null): array
    {
        if (!$contactId) {
            return ['found' => false, 'message' => 'Müşteri doğrulaması gerekli.'];
        }

        $contact = WaContact::find($contactId);
        if (!$contact || $contact->store_id !== $storeId) {
            return ['found' => false, 'message' => 'Müşteri doğrulaması başarısız.'];
        }

        $store = MarketplaceStore::find($storeId);
        if (!$store || $store->marketplace !== 'woocommerce') {
            return ['found' => false, 'message' => 'WooCommerce mağazası değil.'];
        }

        $phoneHash = $contact->phone_hash;

        $orders = ChannelOrder::where('store_id', $storeId)
            ->whereRaw('LOWER(REPLACE(REPLACE(REPLACE(customer_phone, " ", ""), "-", ""), ".", "")) = ?', [$phoneHash])
            ->orderByDesc('ordered_at')
            ->limit(5)
            ->with('packages')
            ->get();

        if ($orders->isEmpty()) {
            return ['found' => false, 'message' => 'Sipariş bulunamadı.'];
        }

        $results = $orders->map(function ($order) {
            $trackingNumbers = $order->packages->pluck('cargo_tracking_number')->filter()->toArray();
            $carrier = $order->packages->pluck('cargo_company')->filter()->first();

            return [
                'order_number' => $this->maskOrderNumber($order->order_number),
                'status' => $order->order_status,
                'carrier' => $carrier,
                'tracking_numbers' => $trackingNumbers,
                'ordered_at' => $order->ordered_at?->format('d.m.Y'),
            ];
        })->toArray();

        return ['found' => true, 'orders' => $results];
    }

    private function maskOrderNumber(?string $orderNumber): string
    {
        if (!$orderNumber) return '***';
        $len = strlen($orderNumber);
        if ($len <= 4) return $orderNumber;
        return str_repeat('*', $len - 4) . substr($orderNumber, -4);
    }
}
