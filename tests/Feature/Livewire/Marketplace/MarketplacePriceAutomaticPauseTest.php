<?php

namespace Tests\Feature\Livewire\Marketplace;

use App\Models\MarketplaceStore;
use App\Models\MpPriceAction;
use App\Models\MpPriceCanaryApproval;
use App\Models\User;
use App\Services\Marketplace\MarketplacePriceActionRevalidatorService;
use App\Services\Marketplace\MarketplacePriceCanaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplacePriceAutomaticPauseTest extends TestCase
{
    use RefreshDatabase;

    protected MarketplaceStore $store;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'operator']);
        $this->store = MarketplaceStore::factory()->create([
            'user_id' => $this->user->id,
            'marketplace' => 'trendyol',
            'seller_id' => '9456',
        ]);
    }

    public function test_canary_auto_pauses_on_revalidation_failure(): void
    {
        // 1. Create active Canary approval
        $approval = MpPriceCanaryApproval::create([
            'store_id' => $this->store->id,
            'approved_by' => $this->user->id,
            'approval_scope' => 'single_product',
            'approved_product_ids' => ['BARCODE1'],
            'expires_at' => now()->addHours(24),
            'status' => 'approved',
        ]);

        // 2. Trigger onStoreCanaryPause directly or via manual invocation to simulate automatic trigger
        app(MarketplacePriceCanaryService::class)->onStoreCanaryPause($this->store->id, 'Revalidation failure: BLOCKED_MARGIN');

        // 3. Verify approval status is revoked
        $approval->refresh();
        $this->assertEquals('revoked', $approval->status);
        $this->assertStringContainsString('Otomatik Durdurma (Pause): Revalidation failure: BLOCKED_MARGIN', $approval->approval_reason);
    }
}
