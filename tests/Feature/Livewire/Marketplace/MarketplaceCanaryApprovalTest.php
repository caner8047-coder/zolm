<?php

namespace Tests\Feature\Livewire\Marketplace;

use App\Models\MarketplaceStore;
use App\Models\MpPriceCanaryApproval;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceCanaryApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected MarketplaceStore $store1;
    protected MarketplaceStore $store2;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'operator']);
        $this->store1 = MarketplaceStore::factory()->create([
            'user_id' => $this->user->id,
            'marketplace' => 'trendyol',
            'seller_id' => '9456',
        ]);
        $this->store2 = MarketplaceStore::factory()->create([
            'user_id' => $this->user->id,
            'marketplace' => 'trendyol',
            'seller_id' => '7890',
        ]);
    }

    public function test_canary_approval_valid_initially(): void
    {
        $approval = MpPriceCanaryApproval::create([
            'store_id' => $this->store1->id,
            'approved_by' => $this->user->id,
            'approval_scope' => 'single_product',
            'approved_product_ids' => ['BARCODE1'],
            'expires_at' => now()->addHours(24),
            'status' => 'approved',
        ]);

        $this->assertTrue($approval->isValid());
        $this->assertEquals('approved', $approval->status);
    }

    public function test_canary_approval_fails_if_expired(): void
    {
        $approval = MpPriceCanaryApproval::create([
            'store_id' => $this->store1->id,
            'approved_by' => $this->user->id,
            'approval_scope' => 'single_product',
            'approved_product_ids' => ['BARCODE1'],
            'expires_at' => now()->subMinutes(1),
            'status' => 'approved',
        ]);

        $this->assertFalse($approval->isValid());
        $this->assertEquals('expired', $approval->status);
    }

    public function test_canary_approval_fails_if_revoked(): void
    {
        $approval = MpPriceCanaryApproval::create([
            'store_id' => $this->store1->id,
            'approved_by' => $this->user->id,
            'approval_scope' => 'single_product',
            'approved_product_ids' => ['BARCODE1'],
            'expires_at' => now()->addHours(24),
            'status' => 'revoked',
            'revoked_at' => now(),
            'revoked_by' => $this->user->id,
        ]);

        $this->assertFalse($approval->isValid());
    }

    public function test_cannot_mix_approvals_across_stores(): void
    {
        MpPriceCanaryApproval::create([
            'store_id' => $this->store1->id,
            'approved_by' => $this->user->id,
            'approval_scope' => 'single_product',
            'approved_product_ids' => ['BARCODE1'],
            'expires_at' => now()->addHours(24),
            'status' => 'approved',
        ]);

        $store2Approval = MpPriceCanaryApproval::where('store_id', $this->store2->id)
            ->where('status', 'approved')
            ->first();

        $this->assertNull($store2Approval);
    }
}
