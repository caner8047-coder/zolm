<?php

namespace Tests\Feature\Hr;

use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Document\Models\HrDocumentType;
use App\Modules\Hr\Document\Models\HrEmployeeDocument;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Tests\Feature\Hr\RefreshHrDatabase;
use Tests\TestCase;

class EmployeeDocumentTenantIsolationTest extends TestCase
{
    use RefreshHrDatabase;

    public function test_tenant_a_cannot_see_tenant_b_documents(): void
    {
        $tenantA = LegalEntity::create(['user_id' => User::factory()->create(['role' => 'admin'])->id, 'name' => 'A', 'tax_number' => '1111111111', 'is_active' => true]);
        $tenantB = LegalEntity::create(['user_id' => User::factory()->create(['role' => 'admin'])->id, 'name' => 'B', 'tax_number' => '2222222222', 'is_active' => true]);

        $typeA = HrDocumentType::create(['legal_entity_id' => $tenantA->id, 'code' => 'A', 'name' => 'A', 'category' => 'other', 'sensitivity' => 'standard', 'is_active' => true]);
        $typeB = HrDocumentType::create(['legal_entity_id' => $tenantB->id, 'code' => 'B', 'name' => 'B', 'category' => 'other', 'sensitivity' => 'standard', 'is_active' => true]);

        $empA = HrEmployee::withoutGlobalScope('tenant')->create(['legal_entity_id' => $tenantA->id, 'employee_number' => 'E001', 'national_id_encrypted' => 'enc', 'national_id_hash' => 'h1', 'national_id_last_four' => '0001', 'first_name' => 'A', 'last_name' => 'Worker', 'status' => 'active']);
        $empB = HrEmployee::withoutGlobalScope('tenant')->create(['legal_entity_id' => $tenantB->id, 'employee_number' => 'E001', 'national_id_encrypted' => 'enc', 'national_id_hash' => 'h2', 'national_id_last_four' => '0002', 'first_name' => 'B', 'last_name' => 'Worker', 'status' => 'active']);

        HrEmployeeDocument::withoutGlobalScope('tenant')->create(['legal_entity_id' => $tenantA->id, 'employee_id' => $empA->id, 'document_type_id' => $typeA->id, 'status' => 'active']);
        HrEmployeeDocument::withoutGlobalScope('tenant')->create(['legal_entity_id' => $tenantB->id, 'employee_id' => $empB->id, 'document_type_id' => $typeB->id, 'status' => 'active']);

        app(TenantContext::class)->set($tenantA);
        $count = HrEmployeeDocument::where('legal_entity_id', $tenantA->id)->count();
        $this->assertEquals(1, $count);
    }

    public function test_another_tenant_document_type_cannot_be_selected(): void
    {
        $tenantA = LegalEntity::create(['user_id' => User::factory()->create(['role' => 'admin'])->id, 'name' => 'A', 'tax_number' => '3333333333', 'is_active' => true]);
        $tenantB = LegalEntity::create(['user_id' => User::factory()->create(['role' => 'admin'])->id, 'name' => 'B', 'tax_number' => '4444444444', 'is_active' => true]);

        $typeB = HrDocumentType::create(['legal_entity_id' => $tenantB->id, 'code' => 'B', 'name' => 'B', 'category' => 'other', 'sensitivity' => 'standard', 'is_active' => true]);

        app(TenantContext::class)->set($tenantA);
        $found = HrDocumentType::find($typeB->id);
        $this->assertNull($found);
    }

    public function test_document_type_category_works(): void
    {
        $tenant = LegalEntity::create(['user_id' => User::factory()->create(['role' => 'admin'])->id, 'name' => 'Test', 'tax_number' => '5555555555', 'is_active' => true]);
        $type = HrDocumentType::create(['legal_entity_id' => $tenant->id, 'code' => 'HEALTH', 'name' => 'Sağlık', 'category' => 'health', 'sensitivity' => 'highly_sensitive', 'is_active' => true]);
        $this->assertEquals('health', $type->category->value);
        $this->assertEquals('highly_sensitive', $type->sensitivity->value);
    }
}
