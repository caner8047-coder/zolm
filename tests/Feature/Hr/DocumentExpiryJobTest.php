<?php

namespace Tests\Feature\Hr;

use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Document\Enums\DocumentStatus;
use App\Modules\Hr\Document\Jobs\MarkExpiredEmployeeDocumentsJob;
use App\Modules\Hr\Document\Models\HrDocumentType;
use App\Modules\Hr\Document\Models\HrEmployeeDocument;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Tests\Feature\Hr\RefreshHrDatabase;
use Tests\TestCase;

class DocumentExpiryJobTest extends TestCase
{
    use RefreshHrDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new \Database\Seeders\Hr\HrPermissionSeeder)->run();
    }

    public function test_expired_documents_marked(): void
    {
        $tenant = LegalEntity::create(['user_id' => User::factory()->create(['role' => 'admin'])->id, 'name' => 'Test', 'tax_number' => '1111111111', 'is_active' => true]);
        app(TenantContext::class)->set($tenant);

        $employee = HrEmployee::withoutGlobalScope('tenant')->create([
            'legal_entity_id' => $tenant->id, 'employee_number' => 'EMP00001',
            'national_id_encrypted' => '11111111111', 'national_id_hash' => hash('sha256', '11111111111'),
            'national_id_last_four' => '1111', 'first_name' => 'Test', 'last_name' => 'User', 'status' => 'active',
        ]);

        $docType = HrDocumentType::create(['legal_entity_id' => $tenant->id, 'code' => 'ID', 'name' => 'Kimlik', 'category' => 'identity', 'sensitivity' => 'standard', 'is_active' => true]);

        HrEmployeeDocument::withoutGlobalScope('tenant')->create([
            'legal_entity_id' => $tenant->id, 'employee_id' => $employee->id,
            'document_type_id' => $docType->id, 'status' => DocumentStatus::Active,
            'expiry_date' => now()->subDay(), 'version_number' => 1,
        ]);

        $job = new MarkExpiredEmployeeDocumentsJob();
        $job->handle();

        $this->assertDatabaseHas('hr_employee_documents', [
            'legal_entity_id' => $tenant->id,
            'status' => DocumentStatus::Expired->value,
        ]);
    }

    public function test_idempotent_on_rerun(): void
    {
        $tenant = LegalEntity::create(['user_id' => User::factory()->create(['role' => 'admin'])->id, 'name' => 'Test', 'tax_number' => '2222222222', 'is_active' => true]);
        app(TenantContext::class)->set($tenant);

        $employee = HrEmployee::withoutGlobalScope('tenant')->create([
            'legal_entity_id' => $tenant->id, 'employee_number' => 'EMP00001',
            'national_id_encrypted' => '22222222222', 'national_id_hash' => hash('sha256', '22222222222'),
            'national_id_last_four' => '2222', 'first_name' => 'Test', 'last_name' => 'User', 'status' => 'active',
        ]);

        $docType = HrDocumentType::create(['legal_entity_id' => $tenant->id, 'code' => 'ID', 'name' => 'Kimlik', 'category' => 'identity', 'sensitivity' => 'standard', 'is_active' => true]);

        HrEmployeeDocument::withoutGlobalScope('tenant')->create([
            'legal_entity_id' => $tenant->id, 'employee_id' => $employee->id,
            'document_type_id' => $docType->id, 'status' => DocumentStatus::Expired,
            'expiry_date' => now()->subDay(), 'version_number' => 1,
        ]);

        $job = new MarkExpiredEmployeeDocumentsJob();
        $job->handle(); // Zaten expired, değişiklik olmamalı

        $this->assertDatabaseCount('hr_employee_documents', 1);
    }

    public function test_job_sets_and_clears_tenant_context_on_handle(): void
    {
        $tenant = LegalEntity::create(['user_id' => User::factory()->create(['role' => 'admin'])->id, 'name' => 'Test', 'tax_number' => '3333333333', 'is_active' => true]);
        app(TenantContext::class)->set($tenant);

        $job = new MarkExpiredEmployeeDocumentsJob();
        $this->assertEquals($tenant->id, $job->tenantId);

        // Handle çağrıldığında context kurulur, işlem sonunda (finally) temizlenir.
        // Queue worker'ın bir sonraki işe veya başka tenant'a ait veriyi sızdırmaması için.
        $job->handle();
        $this->assertFalse(app(TenantContext::class)->isSet());
    }
}
