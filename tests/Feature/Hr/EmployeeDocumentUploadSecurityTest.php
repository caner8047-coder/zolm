<?php

namespace Tests\Feature\Hr;

use App\Models\HrFile;
use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Hr\Core\Services\MalwareScanner;
use App\Modules\Hr\Core\Services\ScanResult;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Document\Actions\UploadDocumentAction;
use App\Modules\Hr\Document\Events\EmployeeDocumentUploaded;
use App\Modules\Hr\Document\Models\HrDocumentType;
use App\Modules\Hr\Document\Models\HrEmployeeDocument;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Hr\RefreshHrDatabase;
use Tests\TestCase;

class EmployeeDocumentUploadSecurityTest extends TestCase
{
    use RefreshHrDatabase;

    private LegalEntity $tenant;
    private User $user;
    private HrEmployee $employee;
    private HrDocumentType $docType;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
        (new \Database\Seeders\Hr\HrPermissionSeeder)->run();
        $this->user = User::factory()->create(['role' => 'admin']);
        $this->tenant = LegalEntity::create(['user_id' => $this->user->id, 'name' => 'Test', 'tax_number' => '1111111111', 'is_active' => true]);
        app(TenantContext::class)->set($this->tenant);
        $this->actingAs($this->user);
        $this->employee = HrEmployee::withoutGlobalScope('tenant')->create([
            'legal_entity_id' => $this->tenant->id, 'employee_number' => 'EMP00001',
            'national_id_encrypted' => '11111111111', 'national_id_hash' => hash('sha256', '11111111111'),
            'national_id_last_four' => '1111', 'first_name' => 'Test', 'last_name' => 'User', 'status' => 'active',
        ]);
        $this->docType = HrDocumentType::create(['legal_entity_id' => $this->tenant->id, 'code' => 'ID', 'name' => 'Kimlik', 'category' => 'identity', 'sensitivity' => 'standard', 'is_active' => true]);
    }

    public function test_malware_infected_file_rejected_and_no_orphan_left(): void
    {
        $this->app->bind(MalwareScanner::class, fn () => new class implements MalwareScanner {
            public function scan(string $filePath): ScanResult { return ScanResult::Infected; }
        });

        try {
            app(UploadDocumentAction::class)->execute($this->employee, $this->docType->id, UploadedFile::fake()->create('bad.pdf', 100, 'application/pdf'));
            $this->fail('Infected dosya kabul edilmemeliydi.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertEquals(422, $e->getStatusCode());
        }

        $this->assertEquals(0, HrFile::count());
        $this->assertEquals(0, HrEmployeeDocument::count());
    }

    public function test_scanner_unavailable_blocks_upload_when_fail_closed(): void
    {
        $this->app->bind(MalwareScanner::class, fn () => new class implements MalwareScanner {
            public function scan(string $filePath): ScanResult { return ScanResult::Unavailable; }
        });
        config(['hr.malware_scanner.fail_closed' => true]);

        try {
            app(UploadDocumentAction::class)->execute($this->employee, $this->docType->id, UploadedFile::fake()->create('a.pdf', 100, 'application/pdf'));
            $this->fail('Fail-closed modunda unavailable tarayıcı engellemeliydi.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertEquals(422, $e->getStatusCode());
        }

        $this->assertEquals(0, HrEmployeeDocument::count());
    }

    public function test_cross_tenant_employee_is_rejected(): void
    {
        $tenantB = LegalEntity::create(['user_id' => User::factory()->create(['role' => 'admin'])->id, 'name' => 'B', 'tax_number' => '2222222222', 'is_active' => true]);
        $empB = HrEmployee::withoutGlobalScope('tenant')->create(['legal_entity_id' => $tenantB->id, 'employee_number' => 'E002', 'national_id_encrypted' => 'enc', 'national_id_hash' => hash('sha256', 'b'), 'national_id_last_four' => '0002', 'first_name' => 'B', 'last_name' => 'W', 'status' => 'active']);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(UploadDocumentAction::class)->execute($empB, $this->docType->id, UploadedFile::fake()->create('a.pdf', 100, 'application/pdf'));
    }

    public function test_cross_tenant_or_inactive_document_type_rejected(): void
    {
        $tenantB = LegalEntity::create(['user_id' => User::factory()->create(['role' => 'admin'])->id, 'name' => 'B', 'tax_number' => '3333333333', 'is_active' => true]);
        $typeB = HrDocumentType::create(['legal_entity_id' => $tenantB->id, 'code' => 'BID', 'name' => 'B Kimlik', 'category' => 'identity', 'sensitivity' => 'standard', 'is_active' => true]);
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        app(UploadDocumentAction::class)->execute($this->employee, $typeB->id, UploadedFile::fake()->create('a.pdf', 100, 'application/pdf'));
    }

    public function test_upload_dispatches_event_after_commit(): void
    {
        Event::fake([EmployeeDocumentUploaded::class]);
        $doc = app(UploadDocumentAction::class)->execute($this->employee, $this->docType->id, UploadedFile::fake()->create('ok.pdf', 100, 'application/pdf'));
        Event::assertDispatched(EmployeeDocumentUploaded::class, fn ($e) => $e->employeeDocumentId === $doc->id && $e->documentTypeId === $this->docType->id);
    }
}
