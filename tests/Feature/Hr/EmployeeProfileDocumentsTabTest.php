<?php

namespace Tests\Feature\Hr;

use App\Models\HrLicense;
use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Document\Enums\DocumentStatus;
use App\Modules\Hr\Document\Enums\VerificationStatus;
use App\Modules\Hr\Document\Models\HrDocumentType;
use App\Modules\Hr\Document\Models\HrEmployeeDocument;
use App\Modules\Hr\Personnel\Livewire\EmployeeDetail;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Feature\Hr\RefreshHrDatabase;
use Tests\TestCase;

class EmployeeProfileDocumentsTabTest extends TestCase
{
    use RefreshHrDatabase;

    private LegalEntity $tenant;
    private User $hrAdmin;
    private HrEmployee $employee;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
        (new \Database\Seeders\Hr\HrPermissionSeeder)->run();
        $this->hrAdmin = User::factory()->create(['role' => 'admin']);
        $adminRole = DB::table('roles')->where('slug', 'hr_admin')->first();
        DB::table('model_has_roles')->insert(['role_id' => $adminRole->id, 'model_id' => $this->hrAdmin->id, 'model_type' => User::class]);
        $this->tenant = LegalEntity::create(['user_id' => $this->hrAdmin->id, 'name' => 'Test', 'tax_number' => '1111111111', 'is_active' => true]);
        HrLicense::create(['legal_entity_id' => $this->tenant->id, 'module_key' => 'personel', 'is_active' => true]);
        app(TenantContext::class)->set($this->tenant);
        $this->actingAs($this->hrAdmin);
        $this->employee = HrEmployee::withoutGlobalScope('tenant')->create([
            'legal_entity_id' => $this->tenant->id, 'employee_number' => 'EMP00001',
            'national_id_encrypted' => '11111111111', 'national_id_hash' => hash('sha256', '11111111111'),
            'national_id_last_four' => '1111', 'first_name' => 'Test', 'last_name' => 'User', 'status' => 'active',
        ]);
    }

    public function test_documents_tab_renders_and_shows_missing_mandatory(): void
    {
        HrDocumentType::create(['legal_entity_id' => $this->tenant->id, 'code' => 'SGK', 'name' => 'SGK Bildirimi', 'category' => 'other', 'sensitivity' => 'standard', 'is_mandatory' => true, 'is_active' => true]);

        Livewire::test(EmployeeDetail::class, ['id' => $this->employee->id])
            ->set('activeTab', 'documents')
            ->assertStatus(200)
            ->assertSee('Eksik Zorunlu Belgeler')
            ->assertSee('SGK Bildirimi');
    }

    public function test_hr_admin_can_verify_document_from_tab(): void
    {
        $type = HrDocumentType::create(['legal_entity_id' => $this->tenant->id, 'code' => 'ID', 'name' => 'Kimlik', 'category' => 'identity', 'sensitivity' => 'standard', 'is_active' => true]);
        $doc = HrEmployeeDocument::withoutGlobalScope('tenant')->create(['legal_entity_id' => $this->tenant->id, 'employee_id' => $this->employee->id, 'document_type_id' => $type->id, 'status' => DocumentStatus::Uploaded, 'verification_status' => VerificationStatus::Pending, 'version_number' => 1]);

        Livewire::test(EmployeeDetail::class, ['id' => $this->employee->id])
            ->set('activeTab', 'documents')
            ->call('verifyDocument', $doc->id)
            ->assertHasNoErrors();

        $this->assertEquals(VerificationStatus::Verified, $doc->fresh()->verification_status);
    }

    public function test_hr_admin_can_upload_new_version_from_tab(): void
    {
        $type = HrDocumentType::create(['legal_entity_id' => $this->tenant->id, 'code' => 'ID', 'name' => 'Kimlik', 'category' => 'identity', 'sensitivity' => 'standard', 'is_active' => true]);
        $doc = HrEmployeeDocument::withoutGlobalScope('tenant')->create(['legal_entity_id' => $this->tenant->id, 'employee_id' => $this->employee->id, 'document_type_id' => $type->id, 'status' => DocumentStatus::Active, 'verification_status' => VerificationStatus::Verified, 'version_number' => 1]);

        Livewire::test(EmployeeDetail::class, ['id' => $this->employee->id])
            ->set('activeTab', 'documents')
            ->set('newVersionDocId', $doc->id)
            ->set('newVersionFile', UploadedFile::fake()->create('v2.pdf', 100, 'application/pdf'))
            ->call('uploadNewVersion')
            ->assertHasNoErrors();

        $this->assertEquals(2, $doc->fresh()->version_number);
    }

    public function test_sensitive_document_hidden_without_permission(): void
    {
        $sensitive = HrDocumentType::create(['legal_entity_id' => $this->tenant->id, 'code' => 'HEALTH', 'name' => 'Sağlık Raporu', 'category' => 'health', 'sensitivity' => 'highly_sensitive', 'is_active' => true]);
        HrEmployeeDocument::withoutGlobalScope('tenant')->create(['legal_entity_id' => $this->tenant->id, 'employee_id' => $this->employee->id, 'document_type_id' => $sensitive->id, 'status' => DocumentStatus::Active, 'version_number' => 1]);

        // hr_admin (view_health + view_sensitive) görür
        Livewire::test(EmployeeDetail::class, ['id' => $this->employee->id])
            ->set('activeTab', 'documents')
            ->assertSee('Sağlık Raporu');

        // yetkisiz viewer (sadece hr.documents.view) görmez
        $viewer = User::factory()->create(['role' => 'admin']);
        $viewPerm = DB::table('permissions')->where('name', 'hr.documents.view')->first();
        DB::table('model_has_permissions')->insert(['permission_id' => $viewPerm->id, 'model_id' => $viewer->id, 'model_type' => User::class]);
        $this->actingAs($viewer);
        Livewire::test(EmployeeDetail::class, ['id' => $this->employee->id])
            ->set('activeTab', 'documents')
            ->assertDontSee('Sağlık Raporu');
    }
}
