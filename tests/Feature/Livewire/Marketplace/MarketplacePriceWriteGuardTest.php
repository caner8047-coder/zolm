<?php

namespace Tests\Feature\Livewire\Marketplace;

use App\Models\ChannelListing;
use App\Models\ChannelProduct;
use App\Models\MarketplaceStore;
use App\Models\MpPriceAction;
use App\Models\MpPriceCanaryApproval;
use App\Models\MpPriceEmergencyStop;
use App\Models\User;
use App\Exceptions\MarketplacePriceWriteBlockedException;
use App\Services\Marketplace\Connectors\TrendyolConnector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MarketplacePriceWriteGuardTest extends TestCase
{
    use RefreshDatabase;

    protected MarketplaceStore $store;
    protected User $user;
    protected ChannelListing $listing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'admin']);

        $this->store = MarketplaceStore::factory()->create([
            'user_id' => $this->user->id,
            'marketplace' => 'trendyol',
            'seller_id' => '9456',
        ]);

        \App\Models\IntegrationConnection::factory()->create([
            'store_id' => $this->store->id,
            'provider' => 'trendyol',
            'credentials_encrypted' => ['api_key' => 'test-key', 'api_secret' => 'test-secret', 'seller_id' => '9456'],
            'status' => 'active',
        ]);

        $prod = ChannelProduct::create([
            'store_id' => $this->store->id,
            'barcode' => 'BARCODE1',
            'title' => 'Test Product',
            'external_product_id' => 'EXT-1234',
        ]);

        $this->listing = ChannelListing::create([
            'store_id' => $this->store->id,
            'channel_product_id' => $prod->id,
            'listing_id' => 'LST-1234',
            'price' => 100.0,
            'stock_quantity' => 10,
        ]);
    }

    /** Helper: create a DB-backed automatic MpPriceAction so the guard can read trigger_type from DB. */
    private function createAutomaticAction(): MpPriceAction
    {
        return MpPriceAction::create([
            'store_id' => $this->store->id,
            'barcode' => 'BARCODE1',
            'status' => 'pending',
            'old_price' => 100.0,
            'requested_price' => 95.0,
            'action_type' => 'price_change',
            'trigger_type' => 'automatic',
        ]);
    }

    // ─── 1. Universal guards (affect every pushPrice call) ───────────

    public function test_write_guard_blocks_when_dry_run_active(): void
    {
        config(['marketplace.trendyol.dry_run_enabled' => true]);

        $connector = new TrendyolConnector($this->store);

        $this->expectException(MarketplacePriceWriteBlockedException::class);
        $this->expectExceptionMessage('Dry-run modu aktif.');

        $connector->pushPrice($this->listing, 95.0);
    }

    public function test_write_guard_blocks_when_emergency_stop_active(): void
    {
        config(['marketplace.trendyol.dry_run_enabled' => false]);

        MpPriceEmergencyStop::create([
            'store_id' => $this->store->id,
            'scope' => 'store',
            'is_active' => true,
            'reason' => 'Emergency Stop Triggered',
        ]);

        $connector = new TrendyolConnector($this->store);

        $this->expectException(MarketplacePriceWriteBlockedException::class);
        $this->expectExceptionMessage('Emergency stop aktif.');

        $connector->pushPrice($this->listing, 95.0);
    }

    // ─── 2. Canary-path guards (triggered via DB action record) ──────

    public function test_write_guard_blocks_when_feature_flags_disabled(): void
    {
        config([
            'marketplace.trendyol.dry_run_enabled' => false,
            'marketplace.trendyol.automatic_price_actions_enabled' => false,
            'marketplace.trendyol.canary_enabled' => false,
        ]);

        // Guard reads trigger_type from DB record (not from context)
        $action = $this->createAutomaticAction();

        $connector = new TrendyolConnector($this->store);

        $this->expectException(MarketplacePriceWriteBlockedException::class);
        $this->expectExceptionMessage('Feature flagler kapalı.');

        $connector->pushPrice($this->listing, 95.0, ['price_action_id' => $action->id]);
    }

    public function test_write_guard_blocks_when_no_active_approval(): void
    {
        config([
            'marketplace.trendyol.dry_run_enabled' => false,
            'marketplace.trendyol.automatic_price_actions_enabled' => true,
            'marketplace.trendyol.canary_enabled' => true,
        ]);

        $action = $this->createAutomaticAction();

        $connector = new TrendyolConnector($this->store);

        $this->expectException(MarketplacePriceWriteBlockedException::class);
        $this->expectExceptionMessage('Geçerli Canary onayı yok veya barkod kapsam dışı.');

        $connector->pushPrice($this->listing, 95.0, ['price_action_id' => $action->id]);
    }

    public function test_write_guard_allows_when_all_conditions_met(): void
    {
        config([
            'marketplace.trendyol.dry_run_enabled' => false,
            'marketplace.trendyol.automatic_price_actions_enabled' => true,
            'marketplace.trendyol.canary_enabled' => true,
        ]);

        $action = $this->createAutomaticAction();

        MpPriceCanaryApproval::create([
            'store_id' => $this->store->id,
            'approved_by' => $this->user->id,
            'approval_scope' => 'single_product',
            'approved_product_ids' => ['BARCODE1'],
            'expires_at' => now()->addHours(24),
            'status' => 'approved',
        ]);

        Http::fake([
            '*/products/price-and-inventory' => Http::response(['batchRequestId' => 'BATCH-ABC-123'], 200),
        ]);

        $connector = new TrendyolConnector($this->store);
        $result = $connector->pushPrice($this->listing, 95.0, ['price_action_id' => $action->id]);

        $this->assertEquals('queued', $result['status']);
        $this->assertEquals('BATCH-ABC-123', $result['batch_request_id']);
    }

    // ─── 3. Security: Trigger-type spoof tests ────────────────────────

    /**
     * DB-first guard: caller cannot bypass Canary checks by spoofing trigger_type=manual
     * in context when the DB record has trigger_type=automatic.
     */
    public function test_trigger_type_spoofing_does_not_bypass_canary_guard(): void
    {
        config([
            'marketplace.trendyol.dry_run_enabled' => false,
            'marketplace.trendyol.automatic_price_actions_enabled' => false, // flags off
            'marketplace.trendyol.canary_enabled' => false,
        ]);

        // DB record: automatic trigger
        $action = $this->createAutomaticAction();

        $connector = new TrendyolConnector($this->store);

        // Caller tries to spoof trigger_type=manual — guard ignores context and reads DB
        $this->expectException(MarketplacePriceWriteBlockedException::class);
        $this->expectExceptionMessage('Feature flagler kapalı.');

        $connector->pushPrice($this->listing, 95.0, [
            'price_action_id' => $action->id,
            'trigger_type'    => 'manual', // spoofed — ignored
            'action_type'     => 'manual', // spoofed — ignored
        ]);
    }

    /**
     * Legacy/non-canary pushPrice calls without price_action_id are only subject to
     * dry-run + emergency stop — NOT canary feature flags or approval checks.
     */
    public function test_legacy_push_without_action_id_not_subject_to_canary_guard(): void
    {
        config([
            'marketplace.trendyol.dry_run_enabled' => false,
            'marketplace.trendyol.automatic_price_actions_enabled' => false, // flags off
            'marketplace.trendyol.canary_enabled' => false,
        ]);

        Http::fake([
            '*/products/price-and-inventory' => Http::response(['batchRequestId' => 'LEGACY-BATCH-001'], 200),
        ]);

        $connector = new TrendyolConnector($this->store);

        // No price_action_id → treated as legacy path
        $result = $connector->pushPrice($this->listing, 95.0);

        $this->assertEquals('queued', $result['status']);
        $this->assertEquals('LEGACY-BATCH-001', $result['batch_request_id']);
    }
}
