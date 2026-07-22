<?php

namespace Database\Factories;

use App\Models\MarketplaceStore;
use App\Models\MpBuyboxListing;
use Illuminate\Database\Eloquent\Factories\Factory;

class MpBuyboxListingFactory extends Factory
{
    protected $model = MpBuyboxListing::class;

    public function definition(): array
    {
        $buyboxPrice = $this->faker->randomFloat(2, 10, 500);
        $sellerPrice = $buyboxPrice + $this->faker->randomFloat(2, -20, 20);
        $rank = $this->faker->numberBetween(1, 5);

        return [
            'store_id' => MarketplaceStore::factory(),
            'barcode' => $this->faker->unique()->ean13(),
            'listing_id' => $this->faker->numberBetween(10000, 99999),
            'seller_rank' => $rank,
            'buybox_price' => $buyboxPrice,
            'seller_price' => max(1, $sellerPrice),
            'second_price' => $buyboxPrice + $this->faker->randomFloat(2, 1, 30),
            'third_price' => $buyboxPrice + $this->faker->randomFloat(2, 5, 50),
            'has_multiple_sellers' => $this->faker->boolean(70),
            'raw_payload' => [],
            'retrieved_at' => now()->subMinutes($this->faker->numberBetween(0, 120)),
        ];
    }

    public function winning(): static
    {
        return $this->state(['seller_rank' => 1]);
    }

    public function losing(): static
    {
        return $this->state(['seller_rank' => $this->faker->numberBetween(2, 10)]);
    }

    public function stale(): static
    {
        return $this->state(['retrieved_at' => now()->subHours(2)]);
    }
}
