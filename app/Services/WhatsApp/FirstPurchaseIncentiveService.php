<?php

namespace App\Services\WhatsApp;

use App\Models\ChannelOrder;
use App\Models\WaContact;
use App\Models\WaOnboardingFlow;
use App\Models\WaOnboardingStep;
use App\Models\WaSetting;

class FirstPurchaseIncentiveService
{
    private const DEFAULT_STEPS = [
        [
            'name' => 'ürün_keşfi',
            'delay_type' => 'days',
            'delay_value' => 3,
            'template_key' => 'first_purchase_day3',
        ],
        [
            'name' => 'hafif_teşvik',
            'delay_type' => 'days',
            'delay_value' => 7,
            'template_key' => 'first_purchase_day7',
            'coupon_key' => 'first_purchase_coupon',
        ],
        [
            'name' => 'son_teklif',
            'delay_type' => 'days',
            'delay_value' => 14,
            'template_key' => 'first_purchase_day14',
            'coupon_key' => 'first_purchase_last_coupon',
        ],
    ];

    /**
     * İlk alışveriş teşvik akışı başlat
     */
    public function startIncentiveFlow(WaContact $contact): ?WaOnboardingFlow
    {
        // Zaten siparişi var mı?
        if ($this->hasOrder($contact)) {
            return null;
        }

        // Zaten aktif akış var mı?
        $existing = WaOnboardingFlow::where('contact_id', $contact->id)
            ->where('store_id', $contact->store_id)
            ->where('flow_type', 'first_purchase')
            ->where('status', 'active')
            ->exists();

        if ($existing) {
            return null;
        }

        $config = WaSetting::get('onboarding.first_purchase', [
            'enabled' => true,
            'steps' => self::DEFAULT_STEPS,
        ]);

        if (empty($config['enabled'])) {
            return null;
        }

        $flow = WaOnboardingFlow::create([
            'contact_id' => $contact->id,
            'store_id' => $contact->store_id,
            'flow_type' => 'first_purchase',
            'status' => 'active',
            'current_step' => 0,
            'steps_config' => $config,
            'started_at' => now(),
        ]);

        foreach ($config['steps'] as $index => $stepConfig) {
            $delayType = $stepConfig['delay_type'] ?? 'days';
            $delayValue = $stepConfig['delay_value'] ?? 3;

            $scheduledAt = match ($delayType) {
                'days' => now()->addDays($delayValue),
                'minutes' => now()->addMinutes($delayValue),
                default => now(),
            };

            WaOnboardingStep::create([
                'flow_id' => $flow->id,
                'step_index' => $index,
                'name' => $stepConfig['name'],
                'delay_type' => $delayType,
                'delay_value' => $delayValue,
                'template_key' => $stepConfig['template_key'] ?? null,
                'template_params' => $stepConfig['template_params'] ?? null,
                'coupon_key' => $stepConfig['coupon_key'] ?? null,
                'status' => 'pending',
                'scheduled_at' => $scheduledAt,
            ]);
        }

        return $flow;
    }

    /**
     * Sipariş oluştuğunda akışı sonlandır
     */
    public function completeFlow(WaContact $contact, int $orderId): void
    {
        WaOnboardingFlow::where('contact_id', $contact->id)
            ->where('store_id', $contact->store_id)
            ->where('flow_type', 'first_purchase')
            ->where('status', 'active')
            ->update([
                'status' => 'completed',
                'completed_at' => now(),
                'exit_reason' => 'order_placed',
            ]);

        // İlgili beklemedeki adımları iptal et
        WaOnboardingStep::whereHas('flow', function ($q) use ($contact) {
            $q->where('contact_id', $contact->id)
                ->where('flow_type', 'first_purchase')
                ->where('status', 'active');
        })->where('status', 'pending')
            ->update(['status' => 'cancelled']);
    }

    /**
     * Pending adımları işle (same as welcome)
     */
    public function processPendingSteps(): int
    {
        $pendingSteps = WaOnboardingStep::where('status', 'pending')
            ->where('scheduled_at', '<=', now())
            ->with('flow.contact')
            ->limit(50)
            ->get();

        $processed = 0;

        foreach ($pendingSteps as $step) {
            $flow = $step->flow;
            if (!$flow || $flow->status !== 'active') {
                $step->update(['status' => 'cancelled']);
                continue;
            }

            $contact = $flow->contact;
            if (!$contact || $contact->status !== 'active') {
                $step->update(['status' => 'cancelled']);
                continue;
            }

            // Sipariş verdi mi kontrol
            if ($this->hasOrder($contact)) {
                $flow->update(['status' => 'completed', 'completed_at' => now(), 'exit_reason' => 'order_placed']);
                $step->update(['status' => 'cancelled']);
                continue;
            }

            $eligibleService = app(EligibilityService::class);
            if (!$eligibleService->isEligibleForMessaging($contact, 'order_updates')) {
                $step->update(['status' => 'cancelled']);
                continue;
            }

            $templateName = $step->template_key;
            if (!$templateName) {
                $step->update(['status' => 'cancelled']);
                continue;
            }

            $templateParams = [
                'customer_name' => $contact->first_name ?: 'Değerli müşterimiz',
            ];

            // Kupon oluştur (eğer varsa)
            if ($step->coupon_key) {
                $couponId = $this->createIncentiveCoupon($step, $contact);
                if ($couponId) {
                    $templateParams['coupon_code'] = \App\Models\WaCoupon::find($couponId)->code ?? '';
                }
            }

            try {
                $idempotencyKey = "first_purchase_incentive:{$flow->id}:{$step->id}";

                $outbox = app(OutboxService::class)->enqueue(
                    contact: $contact,
                    messageType: 'template',
                    templateName: $templateName,
                    templateLanguage: 'tr',
                    templateParams: $templateParams,
                    priority: 'high',
                    automationKey: 'first_purchase_incentive',
                    idempotencyKey: $idempotencyKey,
                );

                $step->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                    'outbox_id' => $outbox->id,
                ]);

                $flow->update(['current_step' => $step->step_index + 1]);
                $processed++;
            } catch (\Throwable $e) {
                $step->update(['status' => 'failed']);
            }
        }

        return $processed;
    }

    private function hasOrder(WaContact $contact): bool
    {
        return ChannelOrder::where('store_id', $contact->store_id)
            ->whereRaw('LOWER(REPLACE(REPLACE(REPLACE(customer_phone, " ", ""), "-", ""), ".", "")) = ?', [$contact->phone_hash])
            ->exists();
    }

    private function createIncentiveCoupon(WaOnboardingStep $step, WaContact $contact): ?int
    {
        $config = WaSetting::get('onboarding.first_purchase', []);
        $couponConfig = $config['coupon'] ?? [
            'type' => 'percent',
            'value' => 10,
            'expiry_hours' => 48,
        ];

        $code = 'ILK-' . strtoupper(substr(uniqid(), -8));

        $idempotencyKey = "first_purchase_coupon:{$contact->store_id}:{$contact->id}:{$step->step_index}";

        $existing = \App\Models\WaCoupon::where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return $existing->id;
        }

        $coupon = \App\Models\WaCoupon::create([
            'store_id' => $contact->store_id,
            'contact_id' => $contact->id,
            'automation_key' => 'first_purchase_incentive',
            'code' => $code,
            'discount_type' => $couponConfig['type'] ?? 'percent',
            'discount_value' => $couponConfig['value'] ?? 10,
            'minimum_spend' => $couponConfig['minimum_spend'] ?? 0,
            'expires_at' => now()->addHours($couponConfig['expiry_hours'] ?? 48),
            'idempotency_key' => $idempotencyKey,
        ]);

        return $coupon->id;
    }
}
