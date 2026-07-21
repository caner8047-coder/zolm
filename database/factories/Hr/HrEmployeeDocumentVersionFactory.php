<?php

namespace Database\Factories\Hr;

use App\Models\HrFile;
use App\Models\User;
use App\Modules\Hr\Document\Models\HrEmployeeDocument;
use App\Modules\Hr\Document\Models\HrEmployeeDocumentVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

class HrEmployeeDocumentVersionFactory extends Factory
{
    protected $model = HrEmployeeDocumentVersion::class;

    public function definition(): array
    {
        return [
            'employee_document_id' => HrEmployeeDocument::factory(),
            'file_id' => HrFile::factory(),
            'version_number' => fake()->numberBetween(1, 5),
            'uploaded_by' => User::factory(),
            'change_reason' => fake()->optional()->sentence,
        ];
    }
}
