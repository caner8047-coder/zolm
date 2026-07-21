<?php

namespace Tests\Feature\Hr;

use App\Models\HrFile;
use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Hr\Core\Services\HrFileService;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EmployeePhotoTest extends TestCase
{
    use RefreshHrDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
        (new \Database\Seeders\Hr\HrPermissionSeeder)->run();
    }

    public function test_valid_photo_can_be_uploaded(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $tenant = LegalEntity::create(['user_id' => $user->id, 'name' => 'Test', 'tax_number' => '1111111111', 'is_active' => true]);
        $this->actingAs($user);
        app(TenantContext::class)->set($tenant);

        $employee = HrEmployee::withoutGlobalScope('tenant')->create([
            'legal_entity_id' => $tenant->id, 'employee_number' => 'EMP00001',
            'national_id_encrypted' => '11111111111', 'national_id_hash' => hash('sha256', '11111111111'),
            'national_id_last_four' => '1111', 'first_name' => 'Test', 'last_name' => 'User', 'status' => 'active',
        ]);

        $service = app(HrFileService::class);
        $file = UploadedFile::fake()->image('photo.jpg', 100, 100);
        $hrFile = $service->upload($file, 'photos', $employee->id, HrEmployee::class);

        $employee->update(['photo_file_id' => $hrFile->id]);

        $this->assertNotNull($employee->fresh()->photo_file_id);
        $this->assertEquals($hrFile->id, $employee->fresh()->photo_file_id);
    }

    public function test_photo_saved_in_private_tenant_directory(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $tenant = LegalEntity::create(['user_id' => $user->id, 'name' => 'Test', 'tax_number' => '2222222222', 'is_active' => true]);
        $this->actingAs($user);
        app(TenantContext::class)->set($tenant);

        $service = app(HrFileService::class);
        $file = UploadedFile::fake()->image('photo.jpg', 100, 100);
        $hrFile = $service->upload($file, 'photos', 1, HrEmployee::class);

        $this->assertStringContainsString("hr/{$tenant->id}/photos/", $hrFile->disk_path);
    }

    public function test_wrong_mime_rejected(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $tenant = LegalEntity::create(['user_id' => $user->id, 'name' => 'Test', 'tax_number' => '3333333333', 'is_active' => true]);
        $this->actingAs($user);
        app(TenantContext::class)->set($tenant);

        $service = app(HrFileService::class);
        $file = UploadedFile::fake()->create('malware.exe', 100, 'application/x-msdownload');

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $service->upload($file, 'photos', 1, HrEmployee::class);
    }

    public function test_large_file_rejected(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $tenant = LegalEntity::create(['user_id' => $user->id, 'name' => 'Test', 'tax_number' => '4444444444', 'is_active' => true]);
        $this->actingAs($user);
        app(TenantContext::class)->set($tenant);

        $service = app(HrFileService::class);
        $file = UploadedFile::fake()->create('large.jpg', 25 * 1024, 'image/jpeg');

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $service->upload($file, 'photos', 1, HrEmployee::class);
    }

    public function test_photo_file_id_relationship_works(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $tenant = LegalEntity::create(['user_id' => $user->id, 'name' => 'Test', 'tax_number' => '5555555555', 'is_active' => true]);
        $this->actingAs($user);
        app(TenantContext::class)->set($tenant);

        $employee = HrEmployee::withoutGlobalScope('tenant')->create([
            'legal_entity_id' => $tenant->id, 'employee_number' => 'EMP00001',
            'national_id_encrypted' => '55555555555', 'national_id_hash' => hash('sha256', '55555555555'),
            'national_id_last_four' => '5555', 'first_name' => 'Test', 'last_name' => 'Photo', 'status' => 'active',
        ]);

        $service = app(HrFileService::class);
        $file = UploadedFile::fake()->image('photo.jpg', 100, 100);
        $hrFile = $service->upload($file, 'photos', $employee->id, HrEmployee::class);
        $employee->update(['photo_file_id' => $hrFile->id]);

        $fresh = $employee->fresh()->load('photo');
        $this->assertNotNull($fresh->photo);
        $this->assertEquals($hrFile->id, $fresh->photo->id);
    }

    public function test_photo_display_fallback_to_initials(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $tenant = LegalEntity::create(['user_id' => $user->id, 'name' => 'Test', 'tax_number' => '6666666666', 'is_active' => true]);

        $employee = HrEmployee::withoutGlobalScope('tenant')->create([
            'legal_entity_id' => $tenant->id, 'employee_number' => 'EMP00001',
            'national_id_encrypted' => '66666666666', 'national_id_hash' => hash('sha256', '66666666666'),
            'national_id_last_four' => '6666', 'first_name' => 'Ahmet', 'last_name' => 'Yılmaz', 'status' => 'active',
        ]);

        $this->assertNull($employee->photo_file_id);
        $this->assertNull($employee->photo);
    }

    public function test_unauthorized_user_cannot_change_photo(): void
    {
        $user = User::factory()->create(['role' => 'operator']);
        $tenant = LegalEntity::create(['user_id' => $user->id, 'name' => 'Test', 'tax_number' => '7777777777', 'is_active' => true]);

        $this->actingAs($user);
        app(TenantContext::class)->set($tenant);

        $this->assertFalse($user->hasHrPermission('hr.employees.update'));
    }

    public function test_tenant_a_cannot_see_tenant_b_photo(): void
    {
        $tenantA = LegalEntity::create(['user_id' => User::factory()->create(['role' => 'admin'])->id, 'name' => 'A', 'tax_number' => '8888888888', 'is_active' => true]);
        $tenantB = LegalEntity::create(['user_id' => User::factory()->create(['role' => 'admin'])->id, 'name' => 'B', 'tax_number' => '9999999999', 'is_active' => true]);

        $employeeB = HrEmployee::withoutGlobalScope('tenant')->create([
            'legal_entity_id' => $tenantB->id, 'employee_number' => 'EMP00001',
            'national_id_encrypted' => '99999999999', 'national_id_hash' => hash('sha256', '99999999999'),
            'national_id_last_four' => '9999', 'first_name' => 'B', 'last_name' => 'User', 'status' => 'active',
        ]);

        app(TenantContext::class)->set($tenantA);
        $found = HrEmployee::find($employeeB->id);
        $this->assertNull($found);
    }

    public function test_photo_change_is_audited(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $tenant = LegalEntity::create(['user_id' => $user->id, 'name' => 'Test', 'tax_number' => '1010101010', 'is_active' => true]);
        $this->actingAs($user);
        app(TenantContext::class)->set($tenant);

        $employee = HrEmployee::withoutGlobalScope('tenant')->create([
            'legal_entity_id' => $tenant->id, 'employee_number' => 'EMP00001',
            'national_id_encrypted' => '10101010101', 'national_id_hash' => hash('sha256', '10101010101'),
            'national_id_last_four' => '1010', 'first_name' => 'Test', 'last_name' => 'Audit', 'status' => 'active',
        ]);

        $service = app(HrFileService::class);
        $file = UploadedFile::fake()->image('photo.jpg', 100, 100);
        $hrFile = $service->upload($file, 'photos', $employee->id, HrEmployee::class);
        $employee->update(['photo_file_id' => $hrFile->id]);

        $this->assertDatabaseHas('hr_files', [
            'legal_entity_id' => $tenant->id,
            'category' => 'photos',
        ]);
    }

    public function test_failed_upload_does_not_break_existing_photo(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $tenant = LegalEntity::create(['user_id' => $user->id, 'name' => 'Test', 'tax_number' => '1212121212', 'is_active' => true]);
        $this->actingAs($user);
        app(TenantContext::class)->set($tenant);

        $employee = HrEmployee::withoutGlobalScope('tenant')->create([
            'legal_entity_id' => $tenant->id, 'employee_number' => 'EMP00001',
            'national_id_encrypted' => '12121212121', 'national_id_hash' => hash('sha256', '12121212121'),
            'national_id_last_four' => '1212', 'first_name' => 'Test', 'last_name' => 'Fail', 'status' => 'active',
        ]);

        $service = app(HrFileService::class);

        $file1 = UploadedFile::fake()->image('photo1.jpg', 100, 100);
        $hrFile1 = $service->upload($file1, 'photos', $employee->id, HrEmployee::class);
        $employee->update(['photo_file_id' => $hrFile1->id]);
        $this->assertEquals($hrFile1->id, $employee->fresh()->photo_file_id);
    }
}
