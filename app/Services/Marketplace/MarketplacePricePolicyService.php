<?php

namespace App\Services\Marketplace;

use App\Models\MarketplaceStore;
use Illuminate\Support\Facades\Log;

class MarketplacePricePolicyService
{
    /**
     * @return array<string, mixed>
     */
    public function defaultPolicy(): array
    {
        return [
            'min_profit_amount' => (float) config('marketplace.trendyol.policy_defaults.min_profit_amount', 10.0),
            'min_profit_margin' => (float) config('marketplace.trendyol.policy_defaults.min_profit_margin', 10.0),
            'price_step' => (float) config('marketplace.trendyol.policy_defaults.price_step', 0.10),
            'max_single_drop_percent' => (float) config('marketplace.trendyol.policy_defaults.max_single_drop_percent', 20.0),
            'max_single_raise_percent' => (float) config('marketplace.trendyol.policy_defaults.max_single_raise_percent', 30.0),
            'daily_max_actions' => (int) config('marketplace.trendyol.policy_defaults.daily_max_actions', 50),
            'cooldown_minutes' => (int) config('marketplace.trendyol.policy_defaults.cooldown_minutes', 60),
            'stale_threshold_minutes' => (int) config('marketplace.trendyol.policy_defaults.stale_threshold_minutes', 60),
            'return_reserve_percent' => (float) config('marketplace.trendyol.policy_defaults.return_reserve_percent', 2.0),
            'auto_action_enabled' => false, // Always false by default for safety
            'bulk_action_enabled' => true,
            'rollback_enabled' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getPolicy(MarketplaceStore $store): array
    {
        $store->loadMissing('syncProfile');
        $saved = data_get($store->syncProfile?->options ?? [], 'price_policy', []);

        return array_replace_recursive($this->defaultPolicy(), is_array($saved) ? $saved : []);
    }

    /**
     * @param  array<string, mixed>  $policy
     * @return array<string, mixed>
     */
    public function savePolicy(MarketplaceStore $store, array $policy): array
    {
        $validated = $this->validateAndNormalize($policy);

        $store->loadMissing('syncProfile');
        $profile = $store->syncProfile;

        if ($profile) {
            $options = $profile->options ?? [];
            $options['price_policy'] = $validated;
            $profile->update(['options' => $options]);
        }

        Log::info('[MarketplacePricePolicyService] Fiyat politikası güncellendi', [
            'store_id' => $store->id,
            'user_id' => auth()->id(),
            'policy' => $validated,
        ]);

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $policy
     * @return array<string, mixed>
     */
    public function validateAndNormalize(array $policy): array
    {
        $defaults = $this->defaultPolicy();

        return [
            'min_profit_amount' => round(max(0, (float) ($policy['min_profit_amount'] ?? $defaults['min_profit_amount'])), 2),
            'min_profit_margin' => round(max(0, min(100, (float) ($policy['min_profit_margin'] ?? $defaults['min_profit_margin']))), 2),
            'price_step' => round(max(0.01, min(100, (float) ($policy['price_step'] ?? $defaults['price_step']))), 2),
            'max_single_drop_percent' => round(max(1, min(90, (float) ($policy['max_single_drop_percent'] ?? $defaults['max_single_drop_percent']))), 2),
            'max_single_raise_percent' => round(max(1, min(500, (float) ($policy['max_single_raise_percent'] ?? $defaults['max_single_raise_percent']))), 2),
            'daily_max_actions' => max(1, min(1000, (int) ($policy['daily_max_actions'] ?? $defaults['daily_max_actions']))),
            'cooldown_minutes' => max(0, min(1440, (int) ($policy['cooldown_minutes'] ?? $defaults['cooldown_minutes']))),
            'stale_threshold_minutes' => max(5, min(10080, (int) ($policy['stale_threshold_minutes'] ?? $defaults['stale_threshold_minutes']))),
            'return_reserve_percent' => round(max(0, min(50, (float) ($policy['return_reserve_percent'] ?? $defaults['return_reserve_percent']))), 2),
            'auto_action_enabled' => false, // Enforce false for now
            'bulk_action_enabled' => (bool) ($policy['bulk_action_enabled'] ?? $defaults['bulk_action_enabled']),
            'rollback_enabled' => (bool) ($policy['rollback_enabled'] ?? $defaults['rollback_enabled']),
        ];
    }
}
