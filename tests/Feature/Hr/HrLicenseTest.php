<?php

namespace Tests\Feature\Hr;

use App\Models\HrLicense;
use App\Models\LegalEntity;
use App\Models\User;
use App\Modules\Hr\Core\Services\TenantContext;
use Tests\Feature\Hr\RefreshHrDatabase;
use Tests\TestCase;

class HrLicenseTest extends TestCase
{
    use RefreshHrDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\Hr\HrPermissionSeeder::class);
    }

    public function test_inactive_module_cannot_be_opened(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $tenant = LegalEntity::create([
            'user_id' => $user->id,
            'name' => 'Test',
            'tax_number' => '2222222222',
            'is_active' => true,
        ]);

        // Tüm modülleri pasif yap
        HrLicense::where('legal_entity_id', $tenant->id)->delete();

        $this->actingAs($user);
        app(TenantContext::class)->set($tenant);

        $this->assertFalse($tenant->hasHrModule('personel'));
    }

    public function test_active_module_can_be_opened(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $tenant = LegalEntity::create([
            'user_id' => $user->id,
            'name' => 'Test',
            'tax_number' => '3333333333',
            'is_active' => true,
        ]);

        HrLicense::create([
            'legal_entity_id' => $tenant->id,
            'module_key' => 'personel',
            'is_active' => true,
        ]);

        $this->assertTrue($tenant->hasHrModule('personel'));
    }

    public function test_expired_license_is_inactive(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $tenant = LegalEntity::create([
            'user_id' => $user->id,
            'name' => 'Test',
            'tax_number' => '4444444444',
            'is_active' => true,
        ]);

        HrLicense::create([
            'legal_entity_id' => $tenant->id,
            'module_key' => 'personel',
            'is_active' => true,
            'expires_at' => now()->subDay(),
        ]);

        $this->assertFalse($tenant->hasHrModule('personel'));
    }

    public function test_license_is_active_and_valid(): void
    {
        $license = HrLicense::make([
            'is_active' => true,
            'expires_at' => now()->addMonth(),
        ]);

        $this->assertTrue($license->isActiveAndValid());
    }

    public function test_license_is_not_active(): void
    {
        $license = HrLicense::make([
            'is_active' => false,
            'expires_at' => now()->addMonth(),
        ]);

        $this->assertFalse($license->isActiveAndValid());
    }

    public function test_license_is_expired(): void
    {
        $license = HrLicense::make([
            'is_active' => true,
            'expires_at' => now()->subDay(),
        ]);

        $this->assertFalse($license->isActiveAndValid());
    }
}
