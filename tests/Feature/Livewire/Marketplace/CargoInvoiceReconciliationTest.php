<?php

namespace Tests\Feature\Livewire\Marketplace;

use App\Livewire\Marketplace\CargoInvoiceReconciliation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CargoInvoiceReconciliationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function renders_successfully()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(CargoInvoiceReconciliation::class)
            ->assertStatus(200)
            ->assertViewIs('livewire.marketplace.cargo-invoice-reconciliation');
    }
}
