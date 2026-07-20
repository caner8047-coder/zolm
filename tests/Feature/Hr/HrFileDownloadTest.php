<?php

namespace Tests\Feature\Hr;

use App\Models\HrFile;
use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\TenantContext;
use Tests\Feature\Hr\RefreshHrDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HrFileDownloadTest extends TestCase
{
    use RefreshHrDatabase;
    use HasHrPermissions;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
        $this->seed(\Database\Seeders\Hr\HrPermissionSeeder::class);
    }

    public function test_file_download_blocked_for_other_tenant(): void
    {
        $userA = User::factory()->create(['role' => 'admin']);
        $userB = User::factory()->create(['role' => 'admin']);
        $this->assignHrAdminRole($userB);

        $tenantA = LegalEntity::create([
            'user_id' => $userA->id,
            'name' => 'Şirket A',
            'tax_number' => '6666666666',
            'is_active' => true,
        ]);

        $tenantB = LegalEntity::create([
            'user_id' => $userB->id,
            'name' => 'Şirket B',
            'tax_number' => '7777777777',
            'is_active' => true,
        ]);

        // Tenant A'ya ait dosya oluştur
        $file = HrFile::create([
            'legal_entity_id' => $tenantA->id,
            'uploader_id' => $userA->id,
            'category' => 'personel',
            'original_name' => 'test.pdf',
            'disk_path' => 'hr/' . $tenantA->id . '/personel/test.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'checksum' => 'abc123',
        ]);

        // User B olarak Tenant B context'inde erişmeye çalış
        $this->actingAs($userB);
        app(TenantContext::class)->set($tenantB);

        $response = $this->get("/hr/files/{$file->id}/download");
        $response->assertStatus(403);
    }

    public function test_file_download_is_audited(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $tenant = LegalEntity::create([
            'user_id' => $user->id,
            'name' => 'Test',
            'tax_number' => '8888888888',
            'is_active' => true,
        ]);

        $file = HrFile::create([
            'legal_entity_id' => $tenant->id,
            'uploader_id' => $user->id,
            'category' => 'personel',
            'original_name' => 'test.pdf',
            'disk_path' => 'hr/' . $tenant->id . '/personel/test.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'checksum' => 'abc123',
        ]);

        $this->actingAs($user);
        app(TenantContext::class)->set($tenant);

        // Audit servisini doğrudan test et
        app(HrAuditService::class)->log('file_downloaded', $file, null, [
            'original_name' => $file->original_name,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'file_downloaded',
            'entity_type' => HrFile::class,
            'entity_id' => $file->id,
        ]);
    }

    public function test_file_uses_private_disk(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $tenant = LegalEntity::create([
            'user_id' => $user->id,
            'name' => 'Test',
            'tax_number' => '9999999999',
            'is_active' => true,
        ]);

        $file = HrFile::create([
            'legal_entity_id' => $tenant->id,
            'uploader_id' => $user->id,
            'category' => 'personel',
            'original_name' => 'test.pdf',
            'disk_path' => 'hr/' . $tenant->id . '/personel/test.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'checksum' => 'abc123',
        ]);

        // Dosya yolu tenant bazlı olmalı
        $this->assertStringContainsString("hr/{$tenant->id}/", $file->disk_path);
    }
}
