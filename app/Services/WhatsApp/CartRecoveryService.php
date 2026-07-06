<?php

namespace App\Services\WhatsApp;

use App\Models\ChannelProduct;
use App\Models\ChannelListing;
use App\Models\MpProduct;
use App\Models\WaAbandonedCart;
use App\Models\WaAutomationConfig;
use App\Models\WaCartRecoveryRun;
use App\Models\WaContact;
use App\Models\WaCoupon;
use App\Models\WaOutbox;
use App\Models\WaSetting;
use App\Models\WaSuppression;
use App\Models\WaTrackingLink;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CartRecoveryService
{
    private const STAGES = ['stage_1', 'stage_2', 'stage_3'];
    private const ACTIVE_STATUSES = ['active', 'waiting', 'stage_1_sent', 'stage_2_sent'];

    /**
     * Yeni sepet sinyali işlenir
     */
    public function onCartUpdated(array $payload): void
    {
        $storeId = (int) ($payload['store_id'] ?? 0);
        $cartKey = $payload['cart_key'] ?? '';
        $wcCustomerId = $payload['wc_customer_id'] ?? null;
        $phone = $payload['phone'] ?? null;
        $cartItems = $payload['cart_items'] ?? [];
        $cartTotal = (float) ($payload['cart_total'] ?? 0);
        $currency = $payload['currency'] ?? 'TRY';
        $cartRecoveryConsent = $payload['cart_recovery_consent'] ?? null;

        if ($storeId <= 0 || empty($cartKey)) {
            return;
        }

        // WC mağazası kontrolü
        $store = \App\Models\MarketplaceStore::where('id', $storeId)
            ->where('marketplace', 'woocommerce')
            ->where('is_active', true)
            ->first();

        if (!$store) {
            return;
        }

        // cart_recovery consent kontrolü
        if ($cartRecoveryConsent !== 'granted') {
            return;
        }

        $cartKeyHash = WaAbandonedCart::hashCartKey($cartKey);

        // Mevcut sepet kaydı
        $cart = WaAbandonedCart::where('store_id', $storeId)
            ->where('cart_key_hash', $cartKeyHash)
            ->first();

        if ($cart) {
            // Güncelle
            $cart->update([
                'cart_snapshot_json' => $cartItems,
                'cart_total_snapshot' => $cartTotal,
                'currency' => $currency,
                'last_activity_at' => now(),
                'wc_customer_id' => $wcCustomerId,
                'contact_id' => $this->resolveContactId($storeId, $phone, $wcCustomerId),
            ]);

            // Durumu aktif'e düşür (yeniden aktif)
            if (in_array($cart->status, [WaAbandonedCart::STATUS_CANCELLED, WaAbandonedCart::STATUS_UNAVAILABLE], true)) {
                $cart->update(['status' => WaAbandonedCart::STATUS_ACTIVE]);
            }
        } else {
            $contactId = $this->resolveContactId($storeId, $phone, $wcCustomerId);

            $cart = WaAbandonedCart::create([
                'store_id' => $storeId,
                'contact_id' => $contactId,
                'wc_customer_id' => $wcCustomerId,
                'cart_key_hash' => $cartKeyHash,
                'cart_snapshot_json' => $cartItems,
                'cart_total_snapshot' => $cartTotal,
                'currency' => $currency,
                'status' => WaAbandonedCart::STATUS_ACTIVE,
                'last_activity_at' => now(),
                'first_detected_at' => now(),
            ]);
        }

        // Aktif recovery run'ları iptal et (sepet değişti, zamanlama sıfırlanmalı)
        $cart->recoveryRuns()
            ->whereIn('status', [WaCartRecoveryRun::STATUS_PENDING])
            ->update(['status' => WaCartRecoveryRun::STATUS_CANCELLED, 'cancel_reason' => 'cart_updated']);

        // İlk aşama için zamanlama kur
        $this->scheduleStage($cart, 1);
    }

    /**
     * Sipariş oluşunca sepet akışını kapat
     */
    public function onOrderCreated(array $payload): void
    {
        $storeId = (int) ($payload['store_id'] ?? 0);
        $cartKey = $payload['cart_key'] ?? null;
        $orderId = $payload['order_id'] ?? null;

        if (!$cartKey) {
            return;
        }

        $cartKeyHash = WaAbandonedCart::hashCartKey($cartKey);

        $cart = WaAbandonedCart::where('store_id', $storeId)
            ->where('cart_key_hash', $cartKeyHash)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->first();

        if (!$cart) {
            return;
        }

        // Tüm pending run'ları iptal et
        $cart->recoveryRuns()
            ->where('status', WaCartRecoveryRun::STATUS_PENDING)
            ->update(['status' => WaCartRecoveryRun::STATUS_CANCELLED, 'cancel_reason' => 'order_created']);

        $cart->update([
            'status' => WaAbandonedCart::STATUS_RECOVERED,
            'recovered_order_id' => $orderId,
            'recovered_at' => now(),
            'next_action_at' => null,
        ]);
    }

    /**
     * Sepet kurtarma akışını işle (scheduler tarafından çağrılır)
     */
    public function processPendingRecoveries(): int
    {
        $config = WaAutomationConfig::get('cart_recovery', [
            'enabled' => false,
            'stages' => [
                ['delay_minutes' => 60, 'enabled' => true, 'template_id' => null, 'coupon_enabled' => false, 'coupon_type' => 'percent', 'coupon_value' => 0, 'minimum_spend' => 0, 'coupon_expiry_hours' => 48],
                ['delay_minutes' => 1440, 'enabled' => true, 'template_id' => null, 'coupon_enabled' => true, 'coupon_type' => 'percent', 'coupon_value' => 10, 'minimum_spend' => 100, 'coupon_expiry_hours' => 48],
                ['delay_minutes' => 4320, 'enabled' => true, 'template_id' => null, 'coupon_enabled' => false, 'coupon_type' => 'percent', 'coupon_value' => 0, 'minimum_spend' => 0, 'coupon_expiry_hours' => 48],
            ],
        ]);

        if (empty($config['enabled'])) {
            return 0;
        }

        $processed = 0;

        foreach (self::STAGES as $stageIndex => $stageName) {
            $stageConfig = $config['stages'][$stageIndex] ?? null;
            if (!$stageConfig || empty($stageConfig['enabled'])) {
                continue;
            }

            $pendingRuns = WaCartRecoveryRun::where('status', WaCartRecoveryRun::STATUS_PENDING)
                ->where('stage', $stageName)
                ->where('scheduled_at', '<=', now())
                ->with('cart.contact')
                ->limit(50)
                ->get();

            foreach ($pendingRuns as $run) {
                $result = $this->processRecoveryRun($run, $stageConfig);
                if ($result) {
                    $processed++;
                }
            }
        }

        return $processed;
    }

    /**
     * Tek bir recovery run'ı işler
     */
    private function processRecoveryRun(WaCartRecoveryRun $run, array $stageConfig): bool
    {
        $cart = $run->cart;
        if (!$cart || !$cart->contact) {
            $run->update(['status' => WaCartRecoveryRun::STATUS_CANCELLED, 'cancel_reason' => 'no_contact']);
            return false;
        }

        $contact = $cart->contact;

        // Eligibility kontrolü
        $eligibleService = app(EligibilityService::class);
        if (!$eligibleService->isEligibleForMessaging($contact, 'cart_recovery')) {
            $run->update(['status' => WaCartRecoveryRun::STATUS_CANCELLED, 'cancel_reason' => 'ineligible']);
            return false;
        }

        // Sepet hâlâ satın alınabilir ürün içeriyor mu?
        if (!$this->hasAvailableProducts($cart)) {
            $cart->update(['status' => WaAbandonedCart::STATUS_UNAVAILABLE]);
            $run->update(['status' => WaCartRecoveryRun::STATUS_CANCELLED, 'cancel_reason' => 'unavailable_products']);
            return false;
        }

        // Kupon oluştur (eğer açıksa)
        $couponId = null;
        if (!empty($stageConfig['coupon_enabled']) && $stageConfig['coupon_value'] > 0) {
            $couponId = $this->createRecoveryCoupon($cart, $contact, $stageConfig);
        }

        // Idempotency key
        $idempotencyKey = "cart_recovery:{$cart->store_id}:{$cart->id}:{$run->stage}";

        // Template seçimi
        $templateId = $stageConfig['template_id'] ?? null;
        if (!$templateId) {
            $run->update(['status' => WaCartRecoveryRun::STATUS_CANCELLED, 'cancel_reason' => 'no_template']);
            return false;
        }

        $template = \App\Models\WaTemplate::where('id', $templateId)->approved()->first();
        if (!$template) {
            $run->update(['status' => WaCartRecoveryRun::STATUS_CANCELLED, 'cancel_reason' => 'template_not_found']);
            return false;
        }

        // Recovery token üret
        $token = WaTrackingLink::generateToken();
        $tokenHash = hash('sha256', $token);

        // Tracking linki kaydet
        $trackingUrl = route('whatsapp.recovery.track', ['token' => $token], false);
        $trackingLink = WaTrackingLink::create([
            'destination_url' => $cart->store->connection?->api_base_url ?? '/',
            'token_hash' => $tokenHash,
            'expires_at' => now()->addDays(7),
        ]);

        // Template parametreleri
        $stageLabel = match ($run->stage) {
            'stage_1' => '1 saat',
            'stage_2' => '24 saat',
            'stage_3' => '3 gün',
            default => '',
        };

        $templateParams = [
            'customer_name' => $contact->first_name ?: 'Değerli müşterimiz',
            'product_count' => count($cart->cart_snapshot_json ?? []),
            'cart_total' => number_format($cart->cart_total_snapshot, 2, ',', '.'),
            'recovery_link' => $trackingUrl,
        ];

        $idempotencyKey = "cart_recovery:{$cart->store_id}:{$cart->id}:{$run->stage}";

        // Outbox'a yaz
        try {
            $outboxService = app(OutboxService::class);
            $outbox = $outboxService->enqueue(
                contact: $contact,
                messageType: 'template',
                templateName: $template->name,
                templateLanguage: $template->language,
                templateParams: $templateParams,
                priority: 'high',
                automationKey: 'cart_recovery',
                relatedCartId: $cart->id,
                idempotencyKey: $idempotencyKey,
            );

            $trackingLink->update(['outbox_id' => $outbox->id]);

            if ($couponId) {
                $run->update(['coupon_id' => $couponId]);
            }

            $run->update([
                'status' => WaCartRecoveryRun::STATUS_SENT,
                'sent_at' => now(),
                'outbox_id' => $outbox->id,
            ]);

            // Cart durumunu güncelle
            $cart->update(['status' => "stage_{$this->stageIndex($run->stage)}_sent"]);

            // Bir sonraki aşamayı zamanla
            $nextStageIndex = $this->stageIndex($run->stage) + 1;
            if ($nextStageIndex <= 3) {
                $this->scheduleStage($cart, $nextStageIndex);
            }

            app(AuditLogService::class)->log(
                'cart_recovery_sent',
                'wa_abandoned_cart',
                $cart->id,
                ['stage' => $run->stage, 'contact_id' => $contact->id],
            );

            return true;
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->errorInfo[1] ?? 0 === 1062) {
                return false; // Duplicate
            }
            throw $e;
        }
    }

    private function scheduleStage(WaAbandonedCart $cart, int $stageNumber): void
    {
        $config = WaAutomationConfig::get('cart_recovery', ['stages' => []]);
        $stageConfig = $config['stages'][$stageNumber - 1] ?? null;

        if (!$stageConfig || empty($stageConfig['enabled'])) {
            return;
        }

        $delayMinutes = $stageConfig['delay_minutes'] ?? 60;
        $stageName = "stage_{$stageNumber}";

        // Zaten bu aşama için pending run var mı?
        $exists = WaCartRecoveryRun::where('cart_id', $cart->id)
            ->where('stage', $stageName)
            ->where('status', WaCartRecoveryRun::STATUS_PENDING)
            ->exists();

        if ($exists) {
            return;
        }

        WaCartRecoveryRun::create([
            'cart_id' => $cart->id,
            'stage' => $stageName,
            'status' => WaCartRecoveryRun::STATUS_PENDING,
            'scheduled_at' => now()->addMinutes($delayMinutes),
        ]);

        $cart->update(['next_action_at' => now()->addMinutes($delayMinutes)]);
    }

    private function hasAvailableProducts(WaAbandonedCart $cart): bool
    {
        $items = $cart->cart_snapshot_json ?? [];

        if (empty($items)) {
            return false;
        }

        foreach ($items as $item) {
            $wcProductId = $item['product_id'] ?? 0;
            $wcVariationId = $item['variation_id'] ?? null;

            $listing = ChannelListing::whereHas('channelProduct', function ($q) use ($wcProductId) {
                $q->where('external_product_id', (string) $wcProductId);
            })->where('stock_quantity', '>', 0)->first();

            if ($listing) {
                return true;
            }
        }

        return false;
    }

    private function createRecoveryCoupon(WaAbandonedCart $cart, WaContact $contact, array $stageConfig): ?int
    {
        $code = 'WA-' . strtoupper(substr(uniqid(), -8));

        $idempotencyKey = "cart_recovery_coupon:{$cart->id}:{$cart->store_id}";

        $existing = WaCoupon::where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return $existing->id;
        }

        $coupon = WaCoupon::create([
            'store_id' => $cart->store_id,
            'contact_id' => $contact->id,
            'cart_id' => $cart->id,
            'automation_key' => 'cart_recovery',
            'code' => $code,
            'discount_type' => $stageConfig['coupon_type'] ?? 'percent',
            'discount_value' => $stageConfig['coupon_value'] ?? 0,
            'minimum_spend' => $stageConfig['minimum_spend'] ?? 0,
            'expires_at' => now()->addHours($stageConfig['coupon_expiry_hours'] ?? 48),
            'idempotency_key' => $idempotencyKey,
        ]);

        // ZOLM'a kupon oluştur komutu gönder
        app(OutboxService::class)->enqueue(
            contact: $contact,
            messageType: 'template',
            templateName: 'cart_recovery_coupon',
            templateParams: ['code' => $code, 'discount' => $stageConfig['coupon_value']],
            priority: 'high',
            automationKey: 'coupon_create',
            relatedCartId: $cart->id,
            idempotencyKey: "coupon_create:{$cart->store_id}:{$cart->id}",
        );

        return $coupon->id;
    }

    private function stageIndex(string $stage): int
    {
        return (int) str_replace('stage_', '', $stage);
    }

    private function resolveContactId(int $storeId, ?string $phone, ?string $wcCustomerId): ?int
    {
        if ($phone) {
            $resolver = app(ContactResolver::class);
            $contact = $resolver->resolveOrCreate($storeId, $phone, $wcCustomerId);
            return $contact?->id;
        }
        return null;
    }

    /**
     * Consent withdrawn sonrası açık akışları iptal et
     */
    public function cancelFlowsForContact(int $contactId): void
    {
        WaAbandonedCart::where('contact_id', $contactId)
            ->active()
            ->update(['status' => WaAbandonedCart::STATUS_CANCELLED]);

        WaCartRecoveryRun::whereHas('cart', fn ($q) => $q->where('contact_id', $contactId))
            ->where('status', WaCartRecoveryRun::STATUS_PENDING)
            ->update(['status' => WaCartRecoveryRun::STATUS_CANCELLED, 'cancel_reason' => 'consent_withdrawn']);
    }
}
