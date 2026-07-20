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
}
