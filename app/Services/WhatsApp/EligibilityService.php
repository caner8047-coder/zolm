<?php

namespace App\Services\WhatsApp;

use App\Models\WaContact;
use App\Models\WaContactPreference;
use App\Models\WaSuppression;
use Illuminate\Support\Facades\Config;

class EligibilityService
{
    private const QUIET_EXEMPT_PURPOSES = ['order_updates', 'stock_alert'];

    public function isEligibleForMessaging(WaContact $contact, string $purpose): bool
    {
        if ($contact->status !== 'active') {
            return false;
        }

        if (empty($contact->phone_hash)) {
            return false;
        }

        if ($this->isSuppressed($contact)) {
            return false;
        }

        if (!$this->hasGrantedPreference($contact, $purpose)) {
            return false;
        }

        if ($this->isTestMode() && !$this->isTestNumber($contact)) {
            return false;
        }

        if (!$this->isQuietHoursExempt($purpose) && $this->isWithinQuietHours()) {
            return false;
        }

        return true;
    }

    public function isSuppressed(WaContact $contact): bool
    {
        return WaSuppression::where('contact_id', $contact->id)
            ->active()
            ->exists();
    }

    public function hasGrantedPreference(WaContact $contact, string $purpose): bool
    {
        return WaContactPreference::where('contact_id', $contact->id)
            ->where('purpose', $purpose)
            ->where('status', 'granted')
            ->exists();
    }

    public function isTestMode(): bool
    {
        return (bool) Config::get('whatsapp.features.test_mode', true);
    }

    public function isTestNumber(WaContact $contact): bool
    {
        $testNumbers = Config::get('whatsapp.features.test_phone_numbers', []);

        if (empty($testNumbers)) {
            return false;
        }

        $phone = $contact->phone_e164_encrypted;
        $normalized = preg_replace('/[^0-9+]/', '', (string) $phone);

        return in_array($normalized, $testNumbers, true)
            || in_array($contact->phone_hash, $testNumbers, true);
    }

    public function isQuietHoursExempt(string $purpose): bool
    {
        return in_array($purpose, self::QUIET_EXEMPT_PURPOSES, true);
    }

    public function isWithinQuietHours(): bool
    {
        $start = Config::get('whatsapp.sending.quiet_hours_start', '22:00');
        $end = Config::get('whatsapp.sending.quiet_hours_end', '08:00');

        $startMinutes = $this->timeToMinutes($start);
        $endMinutes = $this->timeToMinutes($end);
        $nowMinutes = $this->timeToMinutes(now()->format('H:i'));

        if ($startMinutes > $endMinutes) {
            // Gece 22:00 - Sabah 08:00 gibi geceyi aşan aralık
            return $nowMinutes >= $startMinutes || $nowMinutes < $endMinutes;
        }

        return $nowMinutes >= $startMinutes && $nowMinutes < $endMinutes;
    }

    private function timeToMinutes(string $time): int
    {
        [$hours, $minutes] = explode(':', $time);
        return ((int) $hours) * 60 + ((int) $minutes);
    }
}
