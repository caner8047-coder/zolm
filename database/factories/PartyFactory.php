<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PartyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'display_name' => fake()->company(),
            'party_type' => fake()->randomElement(['person', 'organization', 'unknown']),
            'primary_email' => fake()->safeEmail(),
            'primary_phone' => fake()->phoneNumber(),
            'tax_number' => fake()->numerify('###########'),
            'city' => fake()->city(),
            'district' => fake()->city(),
            'status' => 'active',
            'is_blacklisted' => false,
        ];
    }
}
