<?php

namespace Tests\Feature\Hr;

use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Document\Actions\UploadDocumentAction;
use App\Modules\Hr\Document\Actions\UploadNewVersionAction;
use App\Modules\Hr\Document\Models\HrDocumentType;
use App\Modules\Hr\Document\Models\HrEmployeeDocument;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Hr\RefreshHrDatabase;
use Tests\TestCase;

class EmployeeDocumentVersionTest extends TestCase
{
    use RefreshHrDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
        (new \Database\Seeders\Hr\HrPermissionSeeder)->run();
    }

    public function test_new_version_increments_number(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $tenant = LegalEntity::create(['user_id' => $user->id, 'name' => 'Test', 'tax_number' => '1111111111', 'is_active' => true]);
        app(TenantContext::class)->set($tenant);
        $this->actingAs($user);

        $employee = HrEmployee::withoutGlobalScope('tenant')->create([
            'legal_entity_id' => $tenant->id, 'employee_number' => 'EMP00001',
            'national_id_encrypted' => '11111111111', 'national_id_hash' => hash('sha256', '11111111111'),
            'national_id_last_four' => '1111', 'first_name' => 'Test', 'last_name' => 'User', 'status' => 'active',
        ]);

        $docType = HrDocumentType::create(['legal_entity_id' => $tenant->id, 'code' => 'ID', 'name' => 'Kimlik', 'category' => 'identity', 'sensitivity' => 'standard', 'is_active' => true]);

        $uploadAction = app(UploadDocumentAction::class);
        $file1 = UploadedFile::fake()->create('kimlik1.pdf', 100, 'application/pdf');
        $doc = $uploadAction->execute($employee, $docType->id, $file1);
        $this->assertEquals(1, $doc->version_number);

        $versionAction = app(UploadNewVersionAction::class);
        $file2 = UploadedFile::fake()->create('kimlik2.pdf', 100, 'application/pdf');
        $updated = $versionAction->execute($doc, $file2, 'Güncellendi');

        $this->assertEquals(2, $updated->version_number);
        $this->assertDatabaseHas('hr_employee_document_versions', [
            'employee_document_id' => $doc->id,
            'version_number' => 2,
            'change_reason' => 'Güncellendi',
        ]);
    }

    public function test_old_file_preserved(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $tenant = LegalEntity::create(['user_id' => $user->id, 'name' => 'Test', 'tax_number' => '2222222222', 'is_active' => true]);
        app(TenantContext::class)->set($tenant);
        $this->actingAs($user);

        $employee = HrEmployee::withoutGlobalScope('tenant')->create([
            'legal_entity_id' => $tenant->id, 'employee_number' => 'EMP00001',
            'national_id_encrypted' => '22222222222', 'national_id_hash' => hash('sha256', '22222222222'),
            'national_id_last_four' => '2222', 'first_name' => 'Test', 'last_name' => 'User', 'status' => 'active',
        ]);

        $docType = HrDocumentType::create(['legal_entity_id' => $tenant->id, 'code' => 'ID', 'name' => 'Kimlik', 'category' => 'identity', 'sensitivity' => 'standard', 'is_active' => true]);

        $uploadAction = app(UploadDocumentAction::class);
        $file1 = UploadedFile::fake()->create('kimlik1.pdf', 100, 'application/pdf');
        $doc = $uploadAction->execute($employee, $docType->id, $file1);

        $versionAction = app(UploadNewVersionAction::class);
        $file2 = UploadedFile::fake()->create('kimlik2.pdf', 100, 'application/pdf');
        $versionAction->execute($doc, $file2);

        // İlk versiyon hâlâ kayıtlı olmalı
        $this->assertDatabaseHas('hr_employee_document_versions', [
            'employee_document_id' => $doc->id,
            'version_number' => 1,
        ]);
        $this->assertDatabaseHas('hr_employee_document_versions', [
            'employee_document_id' => $doc->id,
            'version_number' => 2,
        ]);
    }

    public function test_new_version_resets_verification(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $tenant = LegalEntity::create(['user_id' => $user->id, 'name' => 'Test', 'tax_number' => '3333333333', 'is_active' => true]);
        app(TenantContext::class)->set($tenant);
        $this->actingAs($user);

        $employee = HrEmployee::withoutGlobalScope('tenant')->create([
            'legal_entity_id' => $tenant->id, 'employee_number' => 'EMP00001',
            'national_id_encrypted' => '33333333333', 'national_id_hash' => hash('sha256', '33333333333'),
            'national_id_last_four' => '3333', 'first_name' => 'Test', 'last_name' => 'User', 'status' => 'active',
        ]);

        $docType = HrDocumentType::create(['legal_entity_id' => $tenant->id, 'code' => 'ID', 'name' => 'Kimlik', 'category' => 'identity', 'sensitivity' => 'standard', 'is_active' => true]);

        $uploadAction = app(UploadDocumentAction::class);
        $file1 = UploadedFile::fake()->create('kimlik1.pdf', 100, 'application/pdf');
        $doc = $uploadAction->execute($employee, $docType->id, $file1);

        $versionAction = app(UploadNewVersionAction::class);
        $file2 = UploadedFile::fake()->create('kimlik2.pdf', 100, 'application/pdf');
        $updated = $versionAction->execute($doc, $file2);

        $this->assertEquals('uploaded', $updated->status->value);
        $this->assertEquals('pending', $updated->verification_status->value);
    }
}
