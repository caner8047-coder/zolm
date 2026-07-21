<?php

namespace Database\Factories\Hr;

use App\Models\LegalEntity;
use App\Modules\Hr\Document\Models\HrDocumentType;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentTypeFactory extends Factory
{
    protected $model = HrDocumentType::class;

    public function definition(): array
    {
        return [
            'legal_entity_id' => LegalEntity::factory(),
            'code' => strtoupper(fake()->unique()->bothify('???##')),
            'name' => fake()->words(2, true),
            'category' => 'other',
            'sensitivity' => 'standard',
            'is_active' => true,
        ];
    }
}
