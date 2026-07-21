<?php

namespace Database\Factories\Hr;

use App\Models\HrFile;
use App\Models\LegalEntity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class HrFileFactory extends Factory
{
    protected $model = HrFile::class;

    public function definition(): array
    {
        return [
            'legal_entity_id' => LegalEntity::factory(),
            'uploader_id' => User::factory(),
            'subject_type' => null,
            'subject_id' => null,
            'category' => 'documents',
            'original_name' => fake()->word . '.pdf',
            'disk_path' => 'hr/' . fake()->numberBetween(1, 999) . '/documents/test.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'checksum' => hash('sha256', fake()->text),
            'is_verified' => false,
        ];
    }
}
