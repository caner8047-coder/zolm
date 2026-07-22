<?php

namespace Database\Factories;

use App\Models\IntegrationConnection;
use App\Models\MarketplaceStore;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Crypt;

class IntegrationConnectionFactory extends Factory
{
    protected $model = IntegrationConnection::class;

    public function definition(): array
    {
        return [
            'store_id' => MarketplaceStore::factory(),
            'provider' => 'trendyol',
            'auth_type' => 'basic',
            'credentials_encrypted' => [
                'api_key' => $this->faker->uuid(),
                'api_secret' => $this->faker->uuid(),
                'seller_id' => $this->faker->randomNumber(5, true)
            ],
            'webhook_secret' => null,
            'webhook_url' => null,
            'api_base_url' => null,
            'status' => 'active',
            'last_verified_at' => now(),
            'last_error' => null,
        ];
    }
}
