<?php

namespace Tests\Feature\Hr;

use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Document\Models\HrDocumentRequirement;
use App\Modules\Hr\Document\Models\HrDocumentType;
use Tests\Feature\Hr\RefreshHrDatabase;
use Tests\TestCase;

class DocumentRequirementTest extends TestCase
{
    use RefreshHrDatabase;

    private LegalEntity $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        (new \Database\Seeders\Hr\HrPermissionSeeder)->run();
        $user = User::factory()->create(['role' => 'admin']);
        $this->tenant = LegalEntity::create(['user_id' => $user->id, 'name' => 'Test', 'tax_number' => '1111111111', 'is_active' => true]);
        app(TenantContext::class)->set($this->tenant);
    }

    public function test_company_wide_requirement(): void
    {
        $type = HrDocumentType::create(['legal_entity_id' => $this->tenant->id, 'code' => 'ID', 'name' => 'Kimlik', 'category' => 'identity', 'sensitivity' => 'standard', 'is_active' => true]);

        $req = HrDocumentRequirement::create([
            'legal_entity_id' => $this->tenant->id,
            'document_type_id' => $type->id,
            'is_required' => true,
            'required_on_hire' => true,
            'effective_from' => now()->toDateString(),
        ]);

        $this->assertTrue($req->is_required);
        $this->assertTrue($req->required_on_hire);
    }

    public function test_branch_level_requirement(): void
    {
        $type = HrDocumentType::create(['legal_entity_id' => $this->tenant->id, 'code' => 'HEALTH', 'name' => 'Sağlık', 'category' => 'health', 'sensitivity' => 'highly_sensitive', 'is_active' => true]);

        $req = HrDocumentRequirement::create([
            'legal_entity_id' => $this->tenant->id,
            'document_type_id' => $type->id,
            'is_required' => true,
            'effective_from' => now()->toDateString(),
        ]);

        $this->assertNotNull($req);
        $this->assertEquals($type->id, $req->document_type_id);
    }

    public function test_tenant_isolation(): void
    {
        $tenantB = LegalEntity::create(['user_id' => User::factory()->create(['role' => 'admin'])->id, 'name' => 'B', 'tax_number' => '2222222222', 'is_active' => true]);

        $typeA = HrDocumentType::create(['legal_entity_id' => $this->tenant->id, 'code' => 'A', 'name' => 'A', 'category' => 'other', 'sensitivity' => 'standard', 'is_active' => true]);
        $typeB = HrDocumentType::create(['legal_entity_id' => $tenantB->id, 'code' => 'B', 'name' => 'B', 'category' => 'other', 'sensitivity' => 'standard', 'is_active' => true]);

        HrDocumentRequirement::create(['legal_entity_id' => $this->tenant->id, 'document_type_id' => $typeA->id, 'is_required' => true, 'effective_from' => now()->toDateString()]);
        HrDocumentRequirement::create(['legal_entity_id' => $tenantB->id, 'document_type_id' => $typeB->id, 'is_required' => true, 'effective_from' => now()->toDateString()]);

        $count = HrDocumentRequirement::where('legal_entity_id', $this->tenant->id)->count();
        $this->assertEquals(1, $count);
    }
}
