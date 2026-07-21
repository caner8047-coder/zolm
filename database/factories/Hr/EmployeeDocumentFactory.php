<?php

namespace Database\Factories\Hr;

use App\Models\LegalEntity;
use App\Modules\Hr\Document\Models\HrDocumentType;
use App\Modules\Hr\Document\Models\HrEmployeeDocument;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeDocumentFactory extends Factory
{
    protected $model = HrEmployeeDocument::class;

    public function definition(): array
    {
        return [
            'legal_entity_id' => LegalEntity::factory(),
            'employee_id' => HrEmployee::factory(),
            'document_type_id' => HrDocumentType::factory(),
            'status' => 'active',
            'verification_status' => 'not_required',
            'version_number' => 1,
        ];
    }
}
