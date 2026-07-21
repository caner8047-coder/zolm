<?php

namespace Database\Factories\Hr;

use App\Models\LegalEntity;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Illuminate\Database\Eloquent\Factories\Factory;

class HrEmployeeFactory extends Factory
{
    protected $model = HrEmployee::class;

    public function definition(): array
    {
        return [
            'legal_entity_id' => LegalEntity::factory(),
            'employee_number' => 'EMP' . fake()->unique()->numberBetween(100000, 999999),
            'national_id_encrypted' => 'enc',
            'national_id_hash' => hash('sha256', fake()->unique()->uuid),
            'national_id_last_four' => '0001',
            'first_name' => fake()->firstName,
            'last_name' => fake()->lastName,
            'status' => 'active',
        ];
    }
}
