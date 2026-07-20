<?php

namespace Tests\Feature\Livewire\Marketplace;

use App\Livewire\Marketplace\ClaimReasonMapping;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClaimReasonMappingTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function renders_successfully()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(ClaimReasonMapping::class)
            ->assertStatus(200)
            ->assertViewIs('livewire.marketplace.claim-reason-mapping');
    }
}
