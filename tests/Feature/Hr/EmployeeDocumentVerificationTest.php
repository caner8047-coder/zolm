<?php

namespace Tests\Feature\Hr;

use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Document\Actions\UploadDocumentAction;
use App\Modules\Hr\Document\Actions\VerifyDocumentAction;
use App\Modules\Hr\Document\Enums\DocumentStatus;
use App\Modules\Hr\Document\Enums\VerificationStatus;
use App\Modules\Hr\Document\Models\HrDocumentType;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Hr\RefreshHrDatabase;
use Tests\TestCase;

class EmployeeDocumentVerificationTest extends TestCase
{
    use RefreshHrDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
        (new \Database\Seeders\Hr\HrPermissionSeeder)->run();
    }

    public function test_verify_sets_status_active(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $tenant = LegalEntity::create(['user_id' => $user->id, 'name' => 'Test', 'tax_number' => '1111111111', 'is_active' => true]);
        app(TenantContext::class)->set($tenant);
        $this->actingAs($user);

        $employee = HrEmployee::withoutGlobalScope('tenant')->create([
            'legal_entity_id' => $tenant->id, 'employee_number' => 'EMP00001',
            'national_id_encrypted' => '11111111111', 'national_id_hash' => hash('sha256', '11111111111'),
            'national_id_last_four' => '1111', 'first_name' => 'Test', 'last_name' => 'User', 'status' => 'active',
        ]);

        $docType = HrDocumentType::create(['legal_entity_id' => $tenant->id, 'code' => 'ID', 'name' => 'Kimlik', 'category' => 'identity', 'sensitivity' => 'standard', 'is_active' => true]);

        $uploadAction = app(UploadDocumentAction::class);
        $file = UploadedFile::fake()->create('kimlik.pdf', 100, 'application/pdf');
        $doc = $uploadAction->execute($employee, $docType->id, $file);

        $verifyAction = app(VerifyDocumentAction::class);
        $verified = $verifyAction->verify($doc, 'Doğrulandı');

        $this->assertEquals(DocumentStatus::Active, $verified->status);
        $this->assertEquals(VerificationStatus::Verified, $verified->verification_status);
        $this->assertEquals($user->id, $verified->verified_by);
        $this->assertNotNull($verified->verified_at);
    }

    public function test_reject_requires_reason(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $tenant = LegalEntity::create(['user_id' => $user->id, 'name' => 'Test', 'tax_number' => '2222222222', 'is_active' => true]);
        app(TenantContext::class)->set($tenant);
        $this->actingAs($user);

        $employee = HrEmployee::withoutGlobalScope('tenant')->create([
            'legal_entity_id' => $tenant->id, 'employee_number' => 'EMP00001',
            'national_id_encrypted' => '22222222222', 'national_id_hash' => hash('sha256', '22222222222'),
            'national_id_last_four' => '2222', 'first_name' => 'Test', 'last_name' => 'User', 'status' => 'active',
        ]);

        $docType = HrDocumentType::create(['legal_entity_id' => $tenant->id, 'code' => 'ID', 'name' => 'Kimlik', 'category' => 'identity', 'sensitivity' => 'standard', 'is_active' => true]);

        $uploadAction = app(UploadDocumentAction::class);
        $file = UploadedFile::fake()->create('kimlik.pdf', 100, 'application/pdf');
        $doc = $uploadAction->execute($employee, $docType->id, $file);

        $verifyAction = app(VerifyDocumentAction::class);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $verifyAction->reject($doc, '');
    }

    public function test_reject_sets_rejection_reason(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $tenant = LegalEntity::create(['user_id' => $user->id, 'name' => 'Test', 'tax_number' => '3333333333', 'is_active' => true]);
        app(TenantContext::class)->set($tenant);
        $this->actingAs($user);

        $employee = HrEmployee::withoutGlobalScope('tenant')->create([
            'legal_entity_id' => $tenant->id, 'employee_number' => 'EMP00001',
            'national_id_encrypted' => '33333333333', 'national_id_hash' => hash('sha256', '33333333333'),
            'national_id_last_four' => '3333', 'first_name' => 'Test', 'last_name' => 'User', 'status' => 'active',
        ]);

        $docType = HrDocumentType::create(['legal_entity_id' => $tenant->id, 'code' => 'ID', 'name' => 'Kimlik', 'category' => 'identity', 'sensitivity' => 'standard', 'is_active' => true]);

        $uploadAction = app(UploadDocumentAction::class);
        $file = UploadedFile::fake()->create('kimlik.pdf', 100, 'application/pdf');
        $doc = $uploadAction->execute($employee, $docType->id, $file);

        $verifyAction = app(VerifyDocumentAction::class);
        $rejected = $verifyAction->reject($doc, 'Okunamıyor');

        $this->assertEquals(VerificationStatus::Rejected, $rejected->verification_status);
        $this->assertEquals('Okunamıyor', $rejected->rejection_reason);
    }
}
