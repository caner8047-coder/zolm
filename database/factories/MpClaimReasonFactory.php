<?php

namespace Database\Factories;

use App\Models\MarketplaceStore;
use App\Models\MpClaimReason;
use Illuminate\Database\Eloquent\Factories\Factory;

class MpClaimReasonFactory extends Factory
{
    protected $model = MpClaimReason::class;

    public function definition(): array
    {
        return [
            'store_id' => MarketplaceStore::factory(),
            'platform_reason_id' => (string) $this->faker->numberBetween(100, 9999),
            'name' => $this->faker->words(3, true),
            'mapped_zolm_reason_code' => null,
            'is_active' => true,
            'raw_payload' => [],
        ];
    }
}
