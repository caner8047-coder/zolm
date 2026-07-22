<?php

namespace Tests\Feature\Hr;

use App\Models\User;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Lifecycle\Livewire\SeveranceCalculator;
use Livewire\Livewire;
use Tests\TestCase;

class SeveranceCalculatorTest extends TestCase
{
    public function test_severance_calculator_page_renders_for_authorized_user(): void
    {
        $admin = User::where('email', 'admin@zolm.test')->first()
            ?? User::where('role', 'admin')->first()
            ?? User::factory()->create(['role' => 'admin']);

        $legalEntity = \App\Models\LegalEntity::firstOrCreate(
            ['user_id' => $admin->id],
            ['name' => 'Test Şirketi', 'tax_number' => '1234567890', 'tax_office' => 'Kadıköy', 'company_type' => 'A.Ş.']
        );

        app(TenantContext::class)->set($legalEntity);

        Livewire::actingAs($admin)
            ->test(SeveranceCalculator::class)
            ->assertStatus(200)
            ->assertSee('Kıdem ve İhbar Tazminatı Motoru')
            ->assertSee('Ödenecek Toplam Net Tazminat');
    }
}
