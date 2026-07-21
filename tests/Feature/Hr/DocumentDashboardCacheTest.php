<?php

namespace Tests\Feature\Hr;

use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Hr\Core\Services\HrCacheKey;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Document\Enums\DocumentStatus;
use App\Modules\Hr\Document\Events\EmployeeDocumentUploaded;
use App\Modules\Hr\Document\Models\HrDocumentType;
use App\Modules\Hr\Document\Models\HrEmployeeDocument;
use App\Modules\Hr\Document\Services\DocumentDashboardMetricsService;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Tests\Feature\Hr\RefreshHrDatabase;
use Tests\TestCase;

class DocumentDashboardCacheTest extends TestCase
{
    use RefreshHrDatabase;

    public function test_each_metric_count_is_correct(): void
    {
        (new \Database\Seeders\Hr\HrPermissionSeeder)->run();
        $tenant = LegalEntity::create(['user_id' => User::factory()->create(['role' => 'admin'])->id, 'name' => 'T', 'tax_number' => '1111111111', 'is_active' => true]);
        app(TenantContext::class)->set($tenant);
        $emp = HrEmployee::withoutGlobalScope('tenant')->create(['legal_entity_id' => $tenant->id, 'employee_number' => 'E1', 'national_id_encrypted' => 'enc', 'national_id_hash' => 'h', 'national_id_last_four' => '0001', 'first_name' => 'A', 'last_name' => 'B', 'status' => 'active']);
        $type = HrDocumentType::create(['legal_entity_id' => $tenant->id, 'code' => 'ID', 'name' => 'Kimlik', 'category' => 'identity', 'sensitivity' => 'standard', 'is_active' => true]);

        HrEmployeeDocument::withoutGlobalScope('tenant')->create(['legal_entity_id' => $tenant->id, 'employee_id' => $emp->id, 'document_type_id' => $type->id, 'status' => DocumentStatus::Requested, 'version_number' => 1]);
        HrEmployeeDocument::withoutGlobalScope('tenant')->create(['legal_entity_id' => $tenant->id, 'employee_id' => $emp->id, 'document_type_id' => $type->id, 'status' => DocumentStatus::Active, 'expiry_date' => now()->addDays(10), 'version_number' => 1]);
        HrEmployeeDocument::withoutGlobalScope('tenant')->create(['legal_entity_id' => $tenant->id, 'employee_id' => $emp->id, 'document_type_id' => $type->id, 'status' => DocumentStatus::Expired, 'version_number' => 1]);

        $m = app(DocumentDashboardMetricsService::class)->getMetrics();
        $this->assertEquals(1, $m['missing_mandatory']);
        $this->assertEquals(1, $m['expiring_soon']);
        $this->assertEquals(1, $m['expired']);
    }

    public function test_metrics_cache_invalidated_after_event(): void
    {
        (new \Database\Seeders\Hr\HrPermissionSeeder)->run();
        $tenant = LegalEntity::create(['user_id' => User::factory()->create(['role' => 'admin'])->id, 'name' => 'T', 'tax_number' => '2222222222', 'is_active' => true]);
        app(TenantContext::class)->set($tenant);

        app(DocumentDashboardMetricsService::class)->getMetrics();
        $this->assertTrue(cache()->has(HrCacheKey::make('document', 'metrics')));

        event(new EmployeeDocumentUploaded(legalEntityId: $tenant->id, employeeDocumentId: 999, employeeId: 999, actorUserId: null, documentTypeId: null));

        $this->assertFalse(cache()->has(HrCacheKey::make('document', 'metrics')));
    }
}
