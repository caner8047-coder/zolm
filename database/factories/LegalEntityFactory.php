<?php

namespace Database\Factories;

use App\Models\LegalEntity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LegalEntity>
 */
class LegalEntityFactory extends Factory
{
    protected $model = LegalEntity::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->company,
            'tax_number' => (string) fake()->unique()->numberBetween(1000000000, 9999999999),
            'tax_office' => fake()->city,
            'is_active' => true,
        ];
    }
}
