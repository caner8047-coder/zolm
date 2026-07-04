<?php

namespace App\Services\WhatsApp;

use App\Models\WaContact;
use App\Models\WaOnboardingFlow;
use App\Models\WaOnboardingStep;
use App\Models\WaSetting;
use Carbon\Carbon;

class WelcomeOnboardingService
{
    private const DEFAULT_STEPS = [
        [
            'name' => 'hoş_geldin',
            'delay_type' => 'immediate',
            'delay_value' => 0,
            'template_key' => 'welcome_message',
        ],
    ];

    /**
     * Yeni üye için karşılama akışı başlat
     */
    public function startWelcomeFlow(WaContact $contact): ?WaOnboardingFlow
    {
        // Zaten aktif akış var mı?
        $existing = WaOnboardingFlow::where('contact_id', $contact->id)
            ->where('store_id', $contact->store_id)
            ->where('flow_type', 'welcome')
            ->where('status', 'active')
            ->exists();

        if ($existing) {
            return null;
        }

        $config = WaSetting::get('onboarding.welcome', [
            'enabled' => true,
            'steps' => self::DEFAULT_STEPS,
            'coupon_enabled' => false,
            'coupon_type' => 'percent',
            'coupon_value' => 10,
            'coupon_expiry_hours' => 48,
        ]);

        if (empty($config['enabled'])) {
            return null;
        }

        $flow = WaOnboardingFlow::create([
            'contact_id' => $contact->id,
            'store_id' => $contact->store_id,
            'flow_type' => 'welcome',
            'status' => 'active',
            'current_step' => 0,
            'steps_config' => $config,
            'started_at' => now(),
        ]);

        // Adımları oluştur
        foreach ($config['steps'] as $index => $stepConfig) {
            $scheduledAt = $this->calculateScheduledAt($stepConfig);

            WaOnboardingStep::create([
                'flow_id' => $flow->id,
                'step_index' => $index,
                'name' => $stepConfig['name'],
                'delay_type' => $stepConfig['delay_type'] ?? 'immediate',
                'delay_value' => $stepConfig['delay_value'] ?? 0,
                'template_key' => $stepConfig['template_key'] ?? null,
                'template_params' => $stepConfig['template_params'] ?? null,
                'coupon_key' => $stepConfig['coupon_key'] ?? null,
                'status' => $scheduledAt ? 'pending' : 'sent',
                'scheduled_at' => $scheduledAt,
            ]);
        }

        return $flow;
    }

    /**
     * Sipariş oluştuğunda akışı sonlandır
     */
    public function completeFlow(WaContact $contact, string $reason = 'order_placed'): void
    {
        WaOnboardingFlow::where('contact_id', $contact->id)
            ->where('store_id', $contact->store_id)
            ->where('status', 'active')
            ->update([
                'status' => 'completed',
                'completed_at' => now(),
                'exit_reason' => $reason,
            ]);
    }

    /**
     * Zamanlanmış adımları işle
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

            // Template seç ve gönder
            $outboxService = app(OutboxService::class);
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

            // Template parametreleri
            $templateParams = array_merge(
                $step->template_params ?? [],
                ['customer_name' => $contact->first_name ?: 'Değerli müşterimiz']
            );

            try {
                $outbox = $outboxService->enqueue(
                    contact: $contact,
                    messageType: 'template',
                    templateName: $templateName,
                    templateLanguage: 'tr',
                    templateParams: $templateParams,
                    priority: 'high',
                    automationKey: 'onboarding_' . $flow->flow_type,
                );

                $step->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                    'outbox_id' => $outbox->id,
                ]);

                // Flow adımını güncelle
                $flow->update(['current_step' => $step->step_index + 1]);

                $processed++;
            } catch (\Throwable $e) {
                $step->update(['status' => 'failed']);
                $this->logError($flow, $step, $e->getMessage());
            }
        }

        return $processed;
    }

    private function calculateScheduledAt(array $stepConfig): ?Carbon
    {
        $delayType = $stepConfig['delay_type'] ?? 'immediate';
        $delayValue = $stepConfig['delay_value'] ?? 0;

        return match ($delayType) {
            'immediate' => now(),
            'minutes' => now()->addMinutes($delayValue),
            'days' => now()->addDays($delayValue),
            default => now(),
        };
    }

    private function logError(WaOnboardingFlow $flow, WaOnboardingStep $step, string $message): void
    {
        app(AuditLogService::class)->log(
            'onboarding_step_failed',
            'wa_onboarding_steps',
            $step->id,
            ['flow_id' => $flow->id, 'step' => $step->name, 'error' => $message],
        );
    }
}
