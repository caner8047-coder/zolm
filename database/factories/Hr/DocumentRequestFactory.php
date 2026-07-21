<?php

namespace Database\Factories\Hr;

use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Hr\Document\Models\HrDocumentRequest;
use App\Modules\Hr\Document\Models\HrDocumentType;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentRequestFactory extends Factory
{
    protected $model = HrDocumentRequest::class;

    public function definition(): array
    {
        return [
            'legal_entity_id' => LegalEntity::factory(),
            'employee_id' => HrEmployee::factory(),
            'document_type_id' => HrDocumentType::factory(),
            'requested_by' => User::factory(),
            'due_date' => now()->addDays(7),
            'status' => 'pending',
        ];
    }
}
