<?php

namespace Tests\Feature\Hr;

use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Document\Models\HrDocumentType;
use App\Modules\Hr\Document\Models\HrEmployeeDocument;
use App\Modules\Hr\Document\Services\DocumentDashboardMetricsService;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Tests\Feature\Hr\RefreshHrDatabase;
use Tests\TestCase;

class DocumentDashboardMetricsTest extends TestCase
{
    use RefreshHrDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new \Database\Seeders\Hr\HrPermissionSeeder)->run();
    }

    public function test_metrics_return_real_counts(): void
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
            'document_type_id' => $docType->id, 'status' => 'requested', 'version_number' => 1,
        ]);

        $service = app(DocumentDashboardMetricsService::class);
        $metrics = $service->getMetrics();

        $this->assertEquals(1, $metrics['missing_mandatory']);
        $this->assertEquals(0, $metrics['expired']);
        $this->assertEquals(0, $metrics['pending_verification']);
    }

    public function test_tenant_isolation_in_metrics(): void
    {
        $tenantA = LegalEntity::create(['user_id' => User::factory()->create(['role' => 'admin'])->id, 'name' => 'A', 'tax_number' => '1111111111', 'is_active' => true]);
        $tenantB = LegalEntity::create(['user_id' => User::factory()->create(['role' => 'admin'])->id, 'name' => 'B', 'tax_number' => '2222222222', 'is_active' => true]);

        $empA = HrEmployee::withoutGlobalScope('tenant')->create([
            'legal_entity_id' => $tenantA->id, 'employee_number' => 'E001',
            'national_id_encrypted' => 'enc', 'national_id_hash' => 'h1', 'national_id_last_four' => '0001',
            'first_name' => 'A', 'last_name' => 'Worker', 'status' => 'active',
        ]);

        $typeA = HrDocumentType::create(['legal_entity_id' => $tenantA->id, 'code' => 'A', 'name' => 'A', 'category' => 'other', 'sensitivity' => 'standard', 'is_active' => true]);

        HrEmployeeDocument::withoutGlobalScope('tenant')->create([
            'legal_entity_id' => $tenantA->id, 'employee_id' => $empA->id,
            'document_type_id' => $typeA->id, 'status' => 'requested', 'version_number' => 1,
        ]);

        app(TenantContext::class)->set($tenantA);
        $metricsA = app(DocumentDashboardMetricsService::class)->getMetrics();
        $this->assertEquals(1, $metricsA['missing_mandatory']);

        app(TenantContext::class)->set($tenantB);
        $metricsB = app(DocumentDashboardMetricsService::class)->getMetrics();
        $this->assertEquals(0, $metricsB['missing_mandatory']);
    }
}
