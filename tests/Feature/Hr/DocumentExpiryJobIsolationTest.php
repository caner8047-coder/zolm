<?php

namespace Tests\Feature\Hr;

use App\Models\ActivityLog;
use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Document\Enums\DocumentStatus;
use App\Modules\Hr\Document\Events\EmployeeDocumentExpired;
use App\Modules\Hr\Document\Jobs\MarkExpiredEmployeeDocumentsJob;
use App\Modules\Hr\Document\Jobs\NotifyExpiringEmployeeDocumentsJob;
use App\Modules\Hr\Document\Models\HrDocumentType;
use App\Modules\Hr\Document\Models\HrEmployeeDocument;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Illuminate\Support\Facades\Event;
use Tests\Feature\Hr\RefreshHrDatabase;
use Tests\TestCase;

class DocumentExpiryJobIsolationTest extends TestCase
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

    private function makeEmployee(LegalEntity $t, string $national): HrEmployee
    {
        return HrEmployee::withoutGlobalScope('tenant')->create([
            'legal_entity_id' => $t->id, 'employee_number' => 'EMP' . substr($national, -5),
            'national_id_encrypted' => $national, 'national_id_hash' => hash('sha256', $national),
            'national_id_last_four' => substr($national, -4), 'first_name' => 'Test', 'last_name' => 'User', 'status' => 'active',
        ]);
    }

    public function test_expired_job_only_affects_current_tenant(): void
    {
        $tenantB = LegalEntity::create(['user_id' => User::factory()->create(['role' => 'admin'])->id, 'name' => 'B', 'tax_number' => '2222222222', 'is_active' => true]);
        $typeA = HrDocumentType::create(['legal_entity_id' => $this->tenant->id, 'code' => 'A', 'name' => 'A', 'category' => 'other', 'sensitivity' => 'standard', 'is_active' => true]);
        $typeB = HrDocumentType::create(['legal_entity_id' => $tenantB->id, 'code' => 'B', 'name' => 'B', 'category' => 'other', 'sensitivity' => 'standard', 'is_active' => true]);
        $empA = $this->makeEmployee($this->tenant, '11111111111');
        $empB = $this->makeEmployee($tenantB, '22222222222');
        HrEmployeeDocument::withoutGlobalScope('tenant')->create(['legal_entity_id' => $this->tenant->id, 'employee_id' => $empA->id, 'document_type_id' => $typeA->id, 'status' => DocumentStatus::Active, 'expiry_date' => now()->subDay(), 'version_number' => 1]);
        HrEmployeeDocument::withoutGlobalScope('tenant')->create(['legal_entity_id' => $tenantB->id, 'employee_id' => $empB->id, 'document_type_id' => $typeB->id, 'status' => DocumentStatus::Active, 'expiry_date' => now()->subDay(), 'version_number' => 1]);

        app(TenantContext::class)->set($this->tenant);
        (new MarkExpiredEmployeeDocumentsJob)->handle();

        $this->assertDatabaseHas('hr_employee_documents', ['legal_entity_id' => $this->tenant->id, 'status' => DocumentStatus::Expired->value]);
        $this->assertDatabaseHas('hr_employee_documents', ['legal_entity_id' => $tenantB->id, 'status' => DocumentStatus::Active->value]);
    }

    public function test_expired_job_does_not_emit_event_on_rerun(): void
    {
        Event::fake([EmployeeDocumentExpired::class]);
        $type = HrDocumentType::create(['legal_entity_id' => $this->tenant->id, 'code' => 'A', 'name' => 'A', 'category' => 'other', 'sensitivity' => 'standard', 'is_active' => true]);
        $emp = $this->makeEmployee($this->tenant, '33333333333');
        HrEmployeeDocument::withoutGlobalScope('tenant')->create(['legal_entity_id' => $this->tenant->id, 'employee_id' => $emp->id, 'document_type_id' => $type->id, 'status' => DocumentStatus::Active, 'expiry_date' => now()->subDay(), 'version_number' => 1]);

        (new MarkExpiredEmployeeDocumentsJob)->handle();
        Event::assertDispatchedTimes(EmployeeDocumentExpired::class, 1);
        // Job finally context'i temizler; ikinci çalıştırma öncesi yeniden kurulmalı.
        app(TenantContext::class)->set($this->tenant);
        (new MarkExpiredEmployeeDocumentsJob)->handle();
        Event::assertDispatchedTimes(EmployeeDocumentExpired::class, 1);
    }

    public function test_expiring_reminder_is_idempotent(): void
    {
        $type = HrDocumentType::create(['legal_entity_id' => $this->tenant->id, 'code' => 'H', 'name' => 'Health', 'category' => 'health', 'sensitivity' => 'highly_sensitive', 'is_active' => true]);
        $emp = $this->makeEmployee($this->tenant, '44444444444');
        HrEmployeeDocument::withoutGlobalScope('tenant')->create(['legal_entity_id' => $this->tenant->id, 'employee_id' => $emp->id, 'document_type_id' => $type->id, 'status' => DocumentStatus::Active, 'expiry_date' => now()->addDays(7), 'version_number' => 1]);

        (new NotifyExpiringEmployeeDocumentsJob)->handle();
        $this->assertEquals(1, ActivityLog::where('action', 'document_expiry_reminder')->count());
        app(TenantContext::class)->set($this->tenant);
        (new NotifyExpiringEmployeeDocumentsJob)->handle();
        $this->assertEquals(1, ActivityLog::where('action', 'document_expiry_reminder')->count());
    }
}
