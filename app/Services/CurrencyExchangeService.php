<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CurrencyExchangeService
{
    /**
     * Converts the given amount from one currency to another (usually TRY).
     *
     * @param float $amount
     * @param string $from
     * @param string $to
     * @param string|null $date (Y-m-d)
     * @return float
     */
    public function convert(float $amount, string $from, string $to = 'TRY', ?string $date = null): float
    {
        $from = strtoupper(trim($from));
        $to = strtoupper(trim($to));

        if ($from === $to || $amount == 0) {
            return $amount;
        }

        $rate = $this->getExchangeRate($from, $to, $date);

        return round($amount * $rate, 4);
    }

    /**
     * Gets the exchange rate from a currency to another.
     *
     * @param string $from
     * @param string $to
     * @param string|null $date
     * @return float
     */
    public function getExchangeRate(string $from, string $to = 'TRY', ?string $date = null): float
    {
        $from = strtoupper(trim($from));
        $to = strtoupper(trim($to));

        if ($from === $to) {
            return 1.0;
        }

        $targetDate = $date ? Carbon::parse($date)->format('Y-m-d') : now()->format('Y-m-d');
        $cacheKey = "exchange_rate_{$from}_{$to}_{$targetDate}";

        return Cache::remember($cacheKey, now()->addHours(12), function () use ($from, $to, $targetDate) {
            return $this->fetchRateFromApi($from, $to, $targetDate);
        });
    }

    /**
     * Fallback API call to get exchange rate.
     */
    protected function fetchRateFromApi(string $from, string $to, string $date): float
    {
        // Using a free API for demonstration. In production, consider TCMB or a paid reliable API.
        // https://cdn.jsdelivr.net/npm/@fawazahmed0/currency-api@latest/v1/currencies/usd.json
        // Historical is available in some APIs but for simplicity we fetch the latest or fallback to 1.0 on failure.

        try {
            // Because free API doesn't support historical nicely, we just fetch latest as a fallback for now.
            // If historical is strictly needed, you can use frankfurter.app or exchangerate.host
            $base = strtolower($from);
            $target = strtolower($to);
            
            // Using frankfurter.app which is free and open-source, supports historical dates
            // Format: https://api.frankfurter.app/2020-01-01?from=USD&to=TRY
            $url = "https://api.frankfurter.app/{$date}?from={$from}&to={$to}";

            if ($date === now()->format('Y-m-d')) {
                $url = "https://api.frankfurter.app/latest?from={$from}&to={$to}";
            }

            $response = Http::timeout(5)->get($url);

            if ($response->successful() && isset($response->json()['rates'][$to])) {
                return (float) $response->json()['rates'][$to];
            }

            // Fallback to currency-api for latest if frankfurter fails
            $fallbackUrl = "https://cdn.jsdelivr.net/npm/@fawazahmed0/currency-api@latest/v1/currencies/{$base}.json";
            $response = Http::timeout(5)->get($fallbackUrl);
            
            if ($response->successful() && isset($response->json()[$base][$target])) {
                return (float) $response->json()[$base][$target];
            }

        } catch (\Exception $e) {
            Log::warning("CurrencyExchangeService: Failed to fetch rate for {$from}->{$to} on {$date}", ['error' => $e->getMessage()]);
        }

        // Return a safe default (1.0) to prevent application crash
        return 1.0;
    }
}
