<?php

namespace Database\Factories;

use App\Models\MpProduct;
use App\Models\MarketplaceStore;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MpProductFactory extends Factory
{
    protected $model = MpProduct::class;

    public function definition(): array
    {
        return [
            'store_id' => MarketplaceStore::factory(),
            'user_id' => User::factory(),
            'barcode' => $this->faker->ean13,
            'stock_code' => $this->faker->unique()->word,
            'product_name' => $this->faker->words(3, true),
            'status' => 'active',
            'sale_price' => $this->faker->randomFloat(2, 10, 1000),
            'market_price' => $this->faker->randomFloat(2, 10, 1000),
            'stock_quantity' => $this->faker->numberBetween(0, 100),
            'vat_rate' => 20,
            'commission_rate' => 15,
        ];
    }
}
