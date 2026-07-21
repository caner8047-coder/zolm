<?php

namespace Database\Factories\Hr;

use App\Models\LegalEntity;
use App\Modules\Hr\Document\Models\HrDocumentRequirement;
use App\Modules\Hr\Document\Models\HrDocumentType;
use Illuminate\Database\Eloquent\Factories\Factory;

class HrDocumentRequirementFactory extends Factory
{
    protected $model = HrDocumentRequirement::class;

    public function definition(): array
    {
        return [
            'legal_entity_id' => LegalEntity::factory(),
            'document_type_id' => HrDocumentType::factory(),
            'is_required' => true,
            'required_on_hire' => false,
            'effective_from' => now()->toDateString(),
        ];
    }
}
