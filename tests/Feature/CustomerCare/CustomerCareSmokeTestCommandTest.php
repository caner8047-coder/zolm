<?php

namespace Tests\Feature\CustomerCare;

use Tests\TestCase;
use App\Models\User;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

class CustomerCareSmokeTestCommandTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private MarketplaceStore $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $le = LegalEntity::create([
            'user_id'      => $this->adminUser->id,
            'name'         => 'Test Org',
            'company_name' => 'Co',
            'tax_office'   => 'Kadikoy',
            'tax_number'   => '1234567890',
            'address'      => 'Istanbul',
        ]);

        $this->store = MarketplaceStore::create([
            'store_name'      => 'Trendyol Mağazam',
            'store_key'       => 'store_ty_1',
            'user_id'         => $this->adminUser->id,
            'legal_entity_id' => $le->id,
            'marketplace'     => 'trendyol',
            'is_active'       => true,
        ]);
    }

    #[Test]
    public function smoke_test_command_runs_successfully(): void
    {
        $this->artisan('customer-care:smoke-test', ['--store' => $this->store->id])
            ->assertExitCode(0)
            ->expectsOutputToContain('=== MÜŞTERİ HİZMETLERİ ENTEGRASYON SMOKE TESTİ ===')
            ->expectsOutputToContain('Trendyol')
            ->expectsOutputToContain('Whatsapp')
            ->expectsOutputToContain('Web_chat')
            ->expectsOutputToContain('🟢 BAŞARILI');
    }
}
