<?php

namespace Tests\Feature\Livewire\Marketplace;

use App\Livewire\Marketplace\TrendyolHealthCenter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TrendyolHealthCenterTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function renders_successfully()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(TrendyolHealthCenter::class)
            ->assertStatus(200)
            ->assertViewIs('livewire.marketplace.trendyol-health-center');
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

        Livewire::test(TrendyolHealthCenter::class)
            ->set('selectedStoreId', $storeUser2->id)
            ->assertSet('selectedStoreId', 0); // Should be reset to 0 by the isolation hook
    }
}
