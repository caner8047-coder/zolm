<?php

namespace App\Services\WhatsApp;

use App\Models\WaBirthdayProfile;
use App\Models\WaContact;
use App\Models\WaOutbox;
use App\Models\WaSetting;

class BirthdayService
{
    /**
     * Doğum günü profili oluştur veya güncelle
     */
    public function updateProfile(WaContact $contact, ?string $birthDate, bool $consentGranted): WaBirthdayProfile
    {
        return WaBirthdayProfile::updateOrCreate(
            ['contact_id' => $contact->id, 'store_id' => $contact->store_id],
            [
                'birth_date' => $birthDate,
                'consent_granted' => $consentGranted,
                'consent_at' => $consentGranted ? now() : null,
            ]
        );
    }

    /**
     * Bugünün doğum günü olan müşterileri bul ve mesaj gönder
     */
    public function processBirthdayMessages(): int
    {
        $config = WaSetting::get('birthday', [
            'enabled' => false,
            'template_key' => 'birthday_message',
            'coupon_enabled' => true,
            'coupon_type' => 'percent',
            'coupon_value' => 15,
            'coupon_expiry_hours' => 72,
        ]);

        if (empty($config['enabled'])) {
            return 0;
        }

        $today = now()->startOfDay();
        $thisYear = $today->year;

        $birthdays = WaBirthdayProfile::where('consent_granted', true)
            ->where('birth_date', '!=', null)
            ->whereDay('birth_date', $today->day)
            ->whereMonth('birth_date', $today->month)
            ->where(function ($q) use ($thisYear) {
                $q->whereNull('last_birthday_year')
                    ->orWhere('last_birthday_year', '<', $thisYear);
            })
            ->with('contact')
            ->get();

        $sent = 0;

        foreach ($birthdays as $profile) {
            $contact = $profile->contact;
            if (!$contact || $contact->status !== 'active') {
                continue;
            }

            $eligibleService = app(EligibilityService::class);
            if (!$eligibleService->isEligibleForMessaging($contact, 'birthday')) {
                continue;
            }

            // Kupon oluştur
            $templateParams = [
                'customer_name' => $contact->first_name ?: 'Değerli müşterimiz',
            ];

            if ($config['coupon_enabled'] ?? false) {
                $couponCode = $this->createBirthdayCoupon($profile, $config);
                if ($couponCode) {
                    $templateParams['coupon_code'] = $couponCode;
                }
            }

            try {
                $idempotencyKey = "birthday:{$profile->store_id}:{$profile->contact_id}:{$thisYear}";

                $outbox = app(OutboxService::class)->enqueue(
                    contact: $contact,
                    messageType: 'template',
                    templateName: $config['template_key'] ?? 'birthday_message',
                    templateLanguage: 'tr',
                    templateParams: $templateParams,
                    priority: 'normal',
                    automationKey: 'birthday',
                    idempotencyKey: $idempotencyKey,
                );

                $profile->update(['last_birthday_year' => $thisYear]);
                $sent++;
            } catch (\Throwable $e) {
                app(AuditLogService::class)->log(
                    'birthday_send_failed',
                    'wa_birthday_profiles',
                    $profile->id,
                    ['error' => $e->getMessage()],
                );
            }
        }

        return $sent;
    }

    private function createBirthdayCoupon(WaBirthdayProfile $profile, array $config): ?string
    {
        $code = 'DOGUM-' . strtoupper(substr(uniqid(), -8));

        $year = $profile->last_birthday_year ?? now()->year;
        $idempotencyKey = "birthday_coupon:{$profile->store_id}:{$profile->contact_id}:{$year}";

        $existing = \App\Models\WaCoupon::where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return $existing->code;
        }

        \App\Models\WaCoupon::create([
            'store_id' => $profile->store_id,
            'contact_id' => $profile->contact_id,
            'automation_key' => 'birthday',
            'code' => $code,
            'discount_type' => $config['coupon_type'] ?? 'percent',
            'discount_value' => $config['coupon_value'] ?? 15,
            'minimum_spend' => $config['coupon_minimum_spend'] ?? 0,
            'expires_at' => now()->addHours($config['coupon_expiry_hours'] ?? 72),
            'idempotency_key' => $idempotencyKey,
        ]);

        return $code;
    }
}
