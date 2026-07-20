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

    /** @test */
    public function prevents_accessing_other_users_store()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        
        $storeUser2 = \App\Models\MarketplaceStore::factory()->create([
            'user_id' => $user2->id,
            'marketplace' => 'trendyol',
        ]);

        $this->actingAs($user1);

        Livewire::test(ClaimReasonMapping::class)
            ->set('selectedStoreId', $storeUser2->id)
            ->assertSet('selectedStoreId', 0);
    }
}
