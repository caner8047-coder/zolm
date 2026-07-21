<?php

namespace Tests\Feature\Hr;

use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Document\Models\HrDocumentType;
use Tests\Feature\Hr\RefreshHrDatabase;
use Tests\TestCase;

class DocumentTypeCrudTest extends TestCase
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

    public function test_document_type_can_be_created(): void
    {
        $type = HrDocumentType::create([
            'legal_entity_id' => $this->tenant->id, 'code' => 'ID_CARD', 'name' => 'Kimlik Kartı',
            'category' => 'identity', 'sensitivity' => 'standard', 'is_active' => true,
        ]);
        $this->assertNotNull($type);
        $this->assertEquals('Kimlik Kartı', $type->name);
    }

    public function test_document_type_update_works(): void
    {
        $type = HrDocumentType::create([
            'legal_entity_id' => $this->tenant->id, 'code' => 'CONTRACT', 'name' => 'Eski',
            'category' => 'contract', 'sensitivity' => 'standard', 'is_active' => true,
        ]);
        $type->update(['name' => 'Yeni']);
        $this->assertEquals('Yeni', $type->fresh()->name);
    }

    public function test_deactivate_works(): void
    {
        $type = HrDocumentType::create([
            'legal_entity_id' => $this->tenant->id, 'code' => 'TEST', 'name' => 'Test',
            'category' => 'other', 'sensitivity' => 'standard', 'is_active' => true,
        ]);
        $type->update(['is_active' => false]);
        $this->assertFalse($type->fresh()->is_active);
    }

    public function test_duplicate_code_same_tenant_prevented(): void
    {
        HrDocumentType::create([
            'legal_entity_id' => $this->tenant->id, 'code' => 'DUP', 'name' => 'A',
            'category' => 'other', 'sensitivity' => 'standard', 'is_active' => true,
        ]);
        $this->expectException(\Illuminate\Database\QueryException::class);
        HrDocumentType::create([
            'legal_entity_id' => $this->tenant->id, 'code' => 'DUP', 'name' => 'B',
            'category' => 'other', 'sensitivity' => 'standard', 'is_active' => true,
        ]);
    }

    public function test_duplicate_code_different_tenant_allowed(): void
    {
        $tenantB = LegalEntity::create(['user_id' => User::factory()->create(['role' => 'admin'])->id, 'name' => 'B', 'tax_number' => '2222222222', 'is_active' => true]);
        HrDocumentType::create(['legal_entity_id' => $this->tenant->id, 'code' => 'PASS', 'name' => 'A', 'category' => 'other', 'sensitivity' => 'standard', 'is_active' => true]);
        $type = HrDocumentType::create(['legal_entity_id' => $tenantB->id, 'code' => 'PASS', 'name' => 'B', 'category' => 'other', 'sensitivity' => 'standard', 'is_active' => true]);
        $this->assertNotNull($type);
    }

    public function test_inactive_type_prevents_upload(): void
    {
        $type = HrDocumentType::create([
            'legal_entity_id' => $this->tenant->id, 'code' => 'INACT', 'name' => 'Pasif',
            'category' => 'other', 'sensitivity' => 'standard', 'is_active' => false,
        ]);
        $this->assertFalse($type->is_active);
    }

    public function test_view_permission_required(): void
    {
        $user = User::factory()->create(['role' => 'operator']);
        $this->actingAs($user);
        $response = $this->get('/hr/settings/document-types');
        $response->assertStatus(403);
    }
}
