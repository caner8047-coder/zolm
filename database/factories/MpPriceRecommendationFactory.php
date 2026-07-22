<?php

namespace Database\Factories;

use App\Models\MarketplaceStore;
use App\Models\MpPriceRecommendation;
use Illuminate\Database\Eloquent\Factories\Factory;

class MpPriceRecommendationFactory extends Factory
{
    protected $model = MpPriceRecommendation::class;

    public function definition(): array
    {
        $buyboxPrice = $this->faker->randomFloat(2, 50, 500);
        $currentPrice = $buyboxPrice + $this->faker->randomFloat(2, -10, 20);
        $minSafePrice = $buyboxPrice * 0.8;

        return [
            'store_id' => MarketplaceStore::factory(),
            'barcode' => $this->faker->unique()->ean13(),
            'current_price' => $currentPrice,
            'buybox_price' => $buyboxPrice,
            'second_price' => $buyboxPrice + 5,
            'third_price' => $buyboxPrice + 10,
            'recommended_price' => max($minSafePrice, $buyboxPrice - 0.10),
            'minimum_safe_price' => $minSafePrice,
            'maximum_allowed_price' => $currentPrice * 1.3,
            'unit_cost' => 50,
            'commission_amount' => 15,
            'cargo_cost' => 35,
            'vat_amount' => 10,
            'service_cost' => 5,
            'other_cost' => 0,
            'expected_profit' => 20,
            'expected_profit_margin' => 15,
            'current_profit' => 18,
            'current_profit_margin' => 12,
            'price_difference' => -0.10,
            'recommendation_type' => 'LOWER_TO_WIN',
            'risk_level' => 'low',
            'reason_codes' => ['LOWER_PRICE_SAFE'],
            'calculation_snapshot' => [],
            'status' => 'new',
            'expires_at' => now()->addHours(24),
        ];
    }
}
