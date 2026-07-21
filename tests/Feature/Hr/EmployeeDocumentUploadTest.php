<?php

namespace Tests\Feature\Hr;

use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Hr\Core\Services\HrFileService;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Document\Actions\UploadDocumentAction;
use App\Modules\Hr\Document\Enums\DocumentStatus;
use App\Modules\Hr\Document\Enums\VerificationStatus;
use App\Modules\Hr\Document\Models\HrDocumentType;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Hr\RefreshHrDatabase;
use Tests\TestCase;

class EmployeeDocumentUploadTest extends TestCase
{
    use RefreshHrDatabase;

    private LegalEntity $tenant;
    private User $user;
    private HrEmployee $employee;
    private HrDocumentType $docType;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
        (new \Database\Seeders\Hr\HrPermissionSeeder)->run();

        $this->user = User::factory()->create(['role' => 'admin']);
        $this->tenant = LegalEntity::create(['user_id' => $this->user->id, 'name' => 'Test', 'tax_number' => '1111111111', 'is_active' => true]);
        app(TenantContext::class)->set($this->tenant);

        $this->employee = HrEmployee::withoutGlobalScope('tenant')->create([
            'legal_entity_id' => $this->tenant->id, 'employee_number' => 'EMP00001',
            'national_id_encrypted' => '11111111111', 'national_id_hash' => hash('sha256', '11111111111'),
            'national_id_last_four' => '1111', 'first_name' => 'Test', 'last_name' => 'User', 'status' => 'active',
        ]);

        $this->docType = HrDocumentType::create(['legal_entity_id' => $this->tenant->id, 'code' => 'ID', 'name' => 'Kimlik', 'category' => 'identity', 'sensitivity' => 'standard', 'is_active' => true]);
    }

    public function test_valid_upload_creates_document(): void
    {
        $this->actingAs($this->user);

        $action = app(UploadDocumentAction::class);
        $file = UploadedFile::fake()->create('kimlik.pdf', 100, 'application/pdf');

        $doc = $action->execute($this->employee, $this->docType->id, $file);

        $this->assertNotNull($doc);
        $this->assertEquals(DocumentStatus::Uploaded, $doc->status);
        $this->assertEquals(VerificationStatus::Pending, $doc->verification_status);
        $this->assertEquals(1, $doc->version_number);
    }

    public function test_tenant_determined_by_context(): void
    {
        $this->actingAs($this->user);

        $action = app(UploadDocumentAction::class);
        $file = UploadedFile::fake()->create('test.pdf', 100, 'application/pdf');

        $doc = $action->execute($this->employee, $this->docType->id, $file);

        $this->assertEquals($this->tenant->id, $doc->legal_entity_id);
    }

    public function test_version_tracking(): void
    {
        $this->actingAs($this->user);

        $action = app(UploadDocumentAction::class);
        $file = UploadedFile::fake()->create('test.pdf', 100, 'application/pdf');

        $doc = $action->execute($this->employee, $this->docType->id, $file);

        $this->assertEquals(1, $doc->version_number);
        $this->assertDatabaseHas('hr_employee_document_versions', [
            'employee_document_id' => $doc->id,
            'version_number' => 1,
        ]);
    }

    public function test_file_saved_in_private_tenant_directory(): void
    {
        $this->actingAs($this->user);

        $action = app(UploadDocumentAction::class);
        $file = UploadedFile::fake()->create('test.pdf', 100, 'application/pdf');

        $doc = $action->execute($this->employee, $this->docType->id, $file);

        $this->assertStringContainsString("hr/{$this->tenant->id}/documents/", $doc->currentFile->disk_path);
    }
}
