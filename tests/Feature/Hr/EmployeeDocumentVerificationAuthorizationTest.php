<?php

namespace Tests\Feature\Hr;

use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Document\Actions\UploadDocumentAction;
use App\Modules\Hr\Document\Actions\VerifyDocumentAction;
use App\Modules\Hr\Document\Events\EmployeeDocumentRejected;
use App\Modules\Hr\Document\Events\EmployeeDocumentVerified;
use App\Modules\Hr\Document\Models\HrDocumentType;
use App\Modules\Hr\Document\Policies\HrEmployeeDocumentPolicy;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Hr\RefreshHrDatabase;
use Tests\TestCase;

class EmployeeDocumentVerificationAuthorizationTest extends TestCase
{
    use RefreshHrDatabase;

    private LegalEntity $tenant;
    private User $hrAdmin;
    private $doc;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
        (new \Database\Seeders\Hr\HrPermissionSeeder)->run();
        $this->hrAdmin = User::factory()->create(['role' => 'admin']);
        $adminRole = DB::table('roles')->where('slug', 'hr_admin')->first();
        DB::table('model_has_roles')->insert(['role_id' => $adminRole->id, 'model_id' => $this->hrAdmin->id, 'model_type' => User::class]);
        $this->tenant = LegalEntity::create(['user_id' => $this->hrAdmin->id, 'name' => 'Test', 'tax_number' => '1111111111', 'is_active' => true]);
        app(TenantContext::class)->set($this->tenant);
        $this->actingAs($this->hrAdmin);
        $employee = HrEmployee::withoutGlobalScope('tenant')->create([
            'legal_entity_id' => $this->tenant->id, 'employee_number' => 'EMP00001',
            'national_id_encrypted' => '11111111111', 'national_id_hash' => hash('sha256', '11111111111'),
            'national_id_last_four' => '1111', 'first_name' => 'Test', 'last_name' => 'User', 'status' => 'active',
        ]);
        $type = HrDocumentType::create(['legal_entity_id' => $this->tenant->id, 'code' => 'ID', 'name' => 'Kimlik', 'category' => 'identity', 'sensitivity' => 'standard', 'is_active' => true]);
        $this->doc = app(UploadDocumentAction::class)->execute($employee, $type->id, UploadedFile::fake()->create('k.pdf', 100, 'application/pdf'));
    }

    public function test_normal_admin_cannot_bypass_verify_policy(): void
    {
        $normalAdmin = User::factory()->create(['role' => 'admin']);
        app(TenantContext::class)->set($this->tenant);
        $this->assertFalse((new HrEmployeeDocumentPolicy)->verify($normalAdmin, $this->doc));
    }

    public function test_hr_admin_can_verify_via_policy(): void
    {
        $this->assertTrue((new HrEmployeeDocumentPolicy)->verify($this->hrAdmin, $this->doc));
    }

    public function test_cross_tenant_verify_blocked_by_policy(): void
    {
        $tenantB = LegalEntity::create(['user_id' => User::factory()->create(['role' => 'admin'])->id, 'name' => 'B', 'tax_number' => '2222222222', 'is_active' => true]);
        app(TenantContext::class)->set($tenantB);
        $this->assertFalse((new HrEmployeeDocumentPolicy)->verify($this->hrAdmin, $this->doc));
    }

    public function test_verify_dispatches_event(): void
    {
        Event::fake([EmployeeDocumentVerified::class]);
        app(VerifyDocumentAction::class)->verify($this->doc);
        Event::assertDispatched(EmployeeDocumentVerified::class, fn ($e) => $e->employeeDocumentId === $this->doc->id);
    }

    public function test_reject_dispatches_event(): void
    {
        Event::fake([EmployeeDocumentRejected::class]);
        app(VerifyDocumentAction::class)->reject($this->doc, 'Okunamıyor');
        Event::assertDispatched(EmployeeDocumentRejected::class, fn ($e) => $e->reason === 'Okunamıyor');
    }
}
