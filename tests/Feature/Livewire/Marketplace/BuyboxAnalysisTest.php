<?php

namespace Tests\Feature\Livewire\Marketplace;

use App\Livewire\Marketplace\BuyboxAnalysis;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BuyboxAnalysisTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function renders_successfully()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(BuyboxAnalysis::class)
            ->assertStatus(200)
            ->assertViewIs('livewire.marketplace.buybox-analysis');
    }
}
