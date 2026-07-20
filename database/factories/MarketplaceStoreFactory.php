<?php

namespace Database\Factories;

use App\Models\MarketplaceStore;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MarketplaceStore>
 */
class MarketplaceStoreFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = MarketplaceStore::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'legal_entity_id' => \App\Models\LegalEntity::factory(),
            'marketplace' => 'trendyol',
            'store_name' => $this->faker->company,
            'store_code' => $this->faker->unique()->word,
            'seller_id' => (string)$this->faker->numberBetween(1000, 9999),
            'status' => 'active',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
            'uses_own_cargo' => false,
        ];
    }
}
