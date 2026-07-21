<?php

namespace Tests\Feature\Hr;

use App\Models\ActivityLog;
use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Document\Jobs\MarkOverdueDocumentRequestsJob;
use App\Modules\Hr\Document\Jobs\SendPendingDocumentRequestRemindersJob;
use App\Modules\Hr\Document\Models\HrDocumentRequest;
use App\Modules\Hr\Document\Models\HrDocumentType;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Tests\Feature\Hr\RefreshHrDatabase;
use Tests\TestCase;

class DocumentRequestJobTest extends TestCase
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

    private function makeEmployee(): HrEmployee
    {
        return HrEmployee::withoutGlobalScope('tenant')->create([
            'legal_entity_id' => $this->tenant->id, 'employee_number' => 'EMP' . random_int(10000, 99999),
            'national_id_encrypted' => 'enc', 'national_id_hash' => hash('sha256', uniqid()), 'national_id_last_four' => '0001',
            'first_name' => 'Test', 'last_name' => 'User', 'status' => 'active',
        ]);
    }

    public function test_mark_overdue_updates_pending_requests(): void
    {
        $type = HrDocumentType::create(['legal_entity_id' => $this->tenant->id, 'code' => 'A', 'name' => 'A', 'category' => 'other', 'sensitivity' => 'standard', 'is_active' => true]);
        $emp = $this->makeEmployee();
        HrDocumentRequest::create(['legal_entity_id' => $this->tenant->id, 'employee_id' => $emp->id, 'document_type_id' => $type->id, 'requested_by' => User::factory()->create(['role' => 'admin'])->id, 'due_date' => now()->subDay(), 'status' => 'pending']);

        (new MarkOverdueDocumentRequestsJob)->handle();
        $this->assertDatabaseHas('hr_document_requests', ['legal_entity_id' => $this->tenant->id, 'status' => 'overdue']);
    }

    public function test_pending_request_reminders_are_idempotent(): void
    {
        $type = HrDocumentType::create(['legal_entity_id' => $this->tenant->id, 'code' => 'A', 'name' => 'A', 'category' => 'other', 'sensitivity' => 'standard', 'is_active' => true]);
        $emp = $this->makeEmployee();
        HrDocumentRequest::create(['legal_entity_id' => $this->tenant->id, 'employee_id' => $emp->id, 'document_type_id' => $type->id, 'requested_by' => User::factory()->create(['role' => 'admin'])->id, 'status' => 'pending']);

        (new SendPendingDocumentRequestRemindersJob)->handle();
        $this->assertEquals(1, ActivityLog::where('action', 'document_request_reminder')->count());
        app(TenantContext::class)->set($this->tenant);
        (new SendPendingDocumentRequestRemindersJob)->handle();
        $this->assertEquals(1, ActivityLog::where('action', 'document_request_reminder')->count());
    }
}
