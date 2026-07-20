<?php

namespace Database\Factories;

use App\Models\LegalEntity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LegalEntityFactory extends Factory
{
    protected $model = LegalEntity::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => $this->faker->company,
            'tax_number' => $this->faker->unique()->numerify('##########'),
            'tax_office' => $this->faker->city,
        ];
    }
}
