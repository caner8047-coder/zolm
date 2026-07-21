<?php

namespace Tests\Feature\Hr;

use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Document\Models\HrDocumentRequest;
use App\Modules\Hr\Document\Models\HrDocumentType;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Tests\Feature\Hr\RefreshHrDatabase;
use Tests\TestCase;

class DocumentRequestTest extends TestCase
{
    use RefreshHrDatabase;

    private LegalEntity $tenant;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        (new \Database\Seeders\Hr\HrPermissionSeeder)->run();
        $this->user = User::factory()->create(['role' => 'admin']);
        $this->tenant = LegalEntity::create(['user_id' => $this->user->id, 'name' => 'Test', 'tax_number' => '1111111111', 'is_active' => true]);
        app(TenantContext::class)->set($this->tenant);
    }

    public function test_document_request_can_be_created(): void
    {
        $type = HrDocumentType::create(['legal_entity_id' => $this->tenant->id, 'code' => 'ID', 'name' => 'Kimlik', 'category' => 'identity', 'sensitivity' => 'standard', 'is_active' => true]);
        $emp = HrEmployee::withoutGlobalScope('tenant')->create(['legal_entity_id' => $this->tenant->id, 'employee_number' => 'E001', 'national_id_encrypted' => 'enc', 'national_id_hash' => 'h', 'national_id_last_four' => '0001', 'first_name' => 'Test', 'last_name' => 'User', 'status' => 'active']);

        $request = HrDocumentRequest::create([
            'legal_entity_id' => $this->tenant->id, 'employee_id' => $emp->id,
            'document_type_id' => $type->id, 'requested_by' => $this->user->id,
            'due_date' => now()->addDays(7), 'message' => 'Lütfen kimlik fotokopisi yükleyin.',
            'status' => 'pending',
        ]);

        $this->assertNotNull($request);
        $this->assertEquals('pending', $request->status);
    }

    public function test_request_fulfillment(): void
    {
        $type = HrDocumentType::create(['legal_entity_id' => $this->tenant->id, 'code' => 'ID', 'name' => 'Kimlik', 'category' => 'identity', 'sensitivity' => 'standard', 'is_active' => true]);
        $emp = HrEmployee::withoutGlobalScope('tenant')->create(['legal_entity_id' => $this->tenant->id, 'employee_number' => 'E001', 'national_id_encrypted' => 'enc', 'national_id_hash' => 'h', 'national_id_last_four' => '0001', 'first_name' => 'Test', 'last_name' => 'User', 'status' => 'active']);

        $request = HrDocumentRequest::create([
            'legal_entity_id' => $this->tenant->id, 'employee_id' => $emp->id,
            'document_type_id' => $type->id, 'requested_by' => $this->user->id, 'status' => 'pending',
        ]);

        $request->update(['status' => 'fulfilled', 'completed_at' => now()]);
        $this->assertEquals('fulfilled', $request->fresh()->status);
        $this->assertNotNull($request->fresh()->completed_at);
    }

    public function test_request_cancellation(): void
    {
        $type = HrDocumentType::create(['legal_entity_id' => $this->tenant->id, 'code' => 'ID', 'name' => 'Kimlik', 'category' => 'identity', 'sensitivity' => 'standard', 'is_active' => true]);
        $emp = HrEmployee::withoutGlobalScope('tenant')->create(['legal_entity_id' => $this->tenant->id, 'employee_number' => 'E001', 'national_id_encrypted' => 'enc', 'national_id_hash' => 'h', 'national_id_last_four' => '0001', 'first_name' => 'Test', 'last_name' => 'User', 'status' => 'active']);

        $request = HrDocumentRequest::create([
            'legal_entity_id' => $this->tenant->id, 'employee_id' => $emp->id,
            'document_type_id' => $type->id, 'requested_by' => $this->user->id, 'status' => 'pending',
        ]);

        $request->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        $this->assertEquals('cancelled', $request->fresh()->status);
    }

    public function test_tenant_isolation(): void
    {
        $tenantB = LegalEntity::create(['user_id' => User::factory()->create(['role' => 'admin'])->id, 'name' => 'B', 'tax_number' => '2222222222', 'is_active' => true]);
        $typeB = HrDocumentType::create(['legal_entity_id' => $tenantB->id, 'code' => 'B', 'name' => 'B', 'category' => 'other', 'sensitivity' => 'standard', 'is_active' => true]);
        $empB = HrEmployee::withoutGlobalScope('tenant')->create(['legal_entity_id' => $tenantB->id, 'employee_number' => 'E001', 'national_id_encrypted' => 'enc', 'national_id_hash' => 'h2', 'national_id_last_four' => '0002', 'first_name' => 'B', 'last_name' => 'Worker', 'status' => 'active']);

        HrDocumentRequest::create(['legal_entity_id' => $tenantB->id, 'employee_id' => $empB->id, 'document_type_id' => $typeB->id, 'requested_by' => $userB = User::factory()->create(['role' => 'admin'])->id, 'status' => 'pending']);

        $count = HrDocumentRequest::where('legal_entity_id', $this->tenant->id)->count();
        $this->assertEquals(0, $count);
    }
}
