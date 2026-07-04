<?php

namespace App\Listeners\WhatsApp;

use App\Events\ShipmentStatusChanged;
use App\Services\WhatsApp\ContactResolver;
use App\Services\WhatsApp\EligibilityService;
use App\Services\WhatsApp\ShippingNotificationService;

class SendShippingNotificationListener
{
    public function __construct(
        protected ContactResolver $contactResolver,
        protected EligibilityService $eligibilityService,
        protected ShippingNotificationService $shippingNotificationService,
    ) {}

    public function handle(ShipmentStatusChanged $event): void
    {
        $shipment = $event->shipment;

        // WC kanal filtresi
        if ($shipment->store?->marketplace !== 'woocommerce') {
            return;
        }

        // Müşteri telefonu: shipment->order->customer_phone (tek kaynak)
        $customerPhone = $shipment->order?->customer_phone;
        if (empty($customerPhone)) {
            return;
        }

        // ContactResolver ile WaContact bul
        $contact = $this->contactResolver->resolve(
            $shipment->store_id,
            $customerPhone
        );
        if (!$contact) {
            return;
        }

        // EligibilityService: order_updates purpose kontrolü
        if (!$this->eligibilityService->isEligibleForMessaging($contact, 'order_updates')) {
            return;
        }

        // ShippingNotificationService'e yönlendir
        $this->shippingNotificationService->onShipmentStatusChanged(
            $shipment,
            $contact,
            $event->oldStatus,
            $event->newStatus
        );
    }
}
