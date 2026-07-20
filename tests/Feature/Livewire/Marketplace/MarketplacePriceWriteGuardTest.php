<?php

namespace Tests\Feature\Livewire\Marketplace;

use App\Models\MarketplaceStore;
use App\Models\ChannelProduct;
use App\Models\ChannelListing;
use App\Models\IntegrationConnection;
use App\Models\MpPriceAction;
use App\Models\MpPriceCanaryApproval;
use App\Models\MpProduct;
use App\Models\User;
use App\Models\Role;
use App\Services\Marketplace\Connectors\TrendyolConnector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplacePriceWriteGuardTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected MarketplaceStore $store;
    protected ChannelListing $listing;

    protected function setUp(): void
    {
        parent::setUp();

        config(['marketplace.trendyol.dry_run_enabled' => false]);
        config(['marketplace.trendyol.manual_price_actions_enabled' => true]);

        $adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin']);

        $this->adminUser = User::factory()->create([
            'email' => 'admin@zolm.test',
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]);

        $this->store = MarketplaceStore::factory()->create([
            'user_id' => $this->adminUser->id,
            'marketplace' => 'trendyol',
            'seller_id' => '9456',
            'status' => 'active',
        ]);

        IntegrationConnection::create([
            'store_id' => $this->store->id,
            'provider' => 'trendyol',
            'auth_type' => 'api_key',
            'credentials_encrypted' => [
                'api_key' => 'test_key',
                'api_secret' => 'test_secret',
            ],
            'status' => 'connected',
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
            'sale_price' => 100.0,
            'list_price' => 120.0,
            'stock_quantity' => 10,
        ]);

        MpProduct::create([
            'user_id' => $this->adminUser->id,
            'barcode' => 'BARCODE1',
            'stock_code' => 'BARCODE1-STK',
            'product_name' => 'Admin Test Product 1',
            'sale_price' => 100.00,
            'stock_quantity' => 10,
            'status' => 'active',
            'product_type' => 'single',
        ]);
    }

    public function test_push_price_without_context_is_blocked_fail_closed(): void
    {
        $this->expectException(\App\Exceptions\MarketplacePriceWriteBlockedException::class);
        $this->expectExceptionMessage("Fiyat push işlemi engellendi: Doğrulanmış yazma bağlamı bulunamadı.");

        app(TrendyolConnector::class)->pushPrice($this->listing, 95.0, []);
    }

    public function test_push_price_with_invalid_price_action_id_is_blocked(): void
    {
        $this->expectException(\App\Exceptions\MarketplacePriceWriteBlockedException::class);
        $this->expectExceptionMessage("Fiyat push işlemi engellendi: Kayıtlı fiyat aksiyonu bulunamadı.");

        app(TrendyolConnector::class)->pushPrice($this->listing, 95.0, [
            'price_action_id' => 99999,
        ]);
    }

    public function test_push_price_with_mismatching_store_is_blocked(): void
    {
        $otherStore = MarketplaceStore::factory()->create([
            'user_id' => $this->adminUser->id,
            'marketplace' => 'trendyol',
            'status' => 'active',
        ]);
        $action = MpPriceAction::create([
            'store_id' => $otherStore->id,
            'barcode' => 'BARCODE1',
            'status' => 'pending',
            'old_price' => 100.0,
            'requested_price' => 95.0,
            'action_type' => 'price_change',
            'trigger_type' => 'manual',
        ]);

        $this->expectException(\App\Exceptions\MarketplacePriceWriteBlockedException::class);
        $this->expectExceptionMessage("Fiyat push işlemi engellendi: Mağaza uyuşmazlığı.");

        app(TrendyolConnector::class)->pushPrice($this->listing, 95.0, [
            'price_action_id' => $action->id,
        ]);
    }

    public function test_push_price_with_mismatching_barcode_is_blocked(): void
    {
        $action = MpPriceAction::create([
            'store_id' => $this->store->id,
            'barcode' => 'DIFFERENT_BARCODE',
            'status' => 'pending',
            'old_price' => 100.0,
            'requested_price' => 95.0,
            'action_type' => 'price_change',
            'trigger_type' => 'manual',
        ]);

        $this->expectException(\App\Exceptions\MarketplacePriceWriteBlockedException::class);
        $this->expectExceptionMessage("Fiyat push işlemi engellendi: Barkod uyuşmazlığı.");

        app(TrendyolConnector::class)->pushPrice($this->listing, 95.0, [
            'price_action_id' => $action->id,
        ]);
    }

    public function test_push_price_with_mismatching_price_is_blocked(): void
    {
        $action = MpPriceAction::create([
            'store_id' => $this->store->id,
            'barcode' => 'BARCODE1',
            'status' => 'pending',
            'old_price' => 100.0,
            'requested_price' => 95.0,
            'action_type' => 'price_change',
            'trigger_type' => 'manual',
        ]);

        $this->expectException(\App\Exceptions\MarketplacePriceWriteBlockedException::class);
        $this->expectExceptionMessage("Fiyat push işlemi engellendi: Talep edilen fiyat ile gönderilen fiyat uyuşmuyor.");

        app(TrendyolConnector::class)->pushPrice($this->listing, 80.0, [
            'price_action_id' => $action->id,
        ]);
    }

    public function test_push_price_with_invalid_legacy_manual_context_is_blocked(): void
    {
        $this->expectException(\App\Exceptions\MarketplacePriceWriteBlockedException::class);
        $this->expectExceptionMessage("Fiyat push işlemi engellendi: Geçersiz manuel yazma bağlamı.");

        app(TrendyolConnector::class)->pushPrice($this->listing, 95.0, [
            'write_context_type' => 'legacy_manual',
            // missing actor_id, permission, reason etc.
        ]);
    }

    public function test_push_price_with_unauthorized_actor_is_blocked(): void
    {
        $unauthorizedUser = User::factory()->create([
            'is_active' => true,
        ]);
        $otherRole = Role::create(['name' => 'Manager', 'slug' => 'manager']);
        $unauthorizedUser->update([
            'role_id' => $otherRole->id,
            'role' => 'manager',
        ]);

        $this->expectException(\App\Exceptions\MarketplacePriceWriteBlockedException::class);
        $this->expectExceptionMessage("Kullanıcının fiyat güncelleme yetkisi yok.");

        app(TrendyolConnector::class)->pushPrice($this->listing, 95.0, [
            'write_context_type' => 'legacy_manual',
            'actor_type' => 'user',
            'actor_id' => $unauthorizedUser->id,
            'permission' => 'update_price',
            'store_id' => $this->listing->store_id,
            'correlation_id' => '123',
            'idempotency_key' => 'abc',
            'reason' => 'test',
        ]);
    }

    public function test_no_fallback_to_user_1_when_auth_id_is_null(): void
    {
        $this->expectException(\App\Exceptions\MarketplacePriceWriteBlockedException::class);
        $this->expectExceptionMessage("Fiyat push işlemi engellendi: Geçersiz manuel yazma bağlamı.");

        app(TrendyolConnector::class)->pushPrice($this->listing, 95.0, [
            'write_context_type' => 'legacy_manual',
            'actor_type' => 'user',
            'actor_id' => null,
            'permission' => 'update_price',
            'store_id' => $this->listing->store_id,
            'correlation_id' => '123',
            'idempotency_key' => 'abc',
            'reason' => 'test',
        ]);
    }

    public function test_push_stock_without_context_is_blocked_fail_closed(): void
    {
        $this->expectException(\App\Exceptions\MarketplacePriceWriteBlockedException::class);
        $this->expectExceptionMessage("Stok push işlemi engellendi: Doğrulanmış stok yazma bağlamı (stock_update) eksik.");

        app(TrendyolConnector::class)->pushStock($this->listing, 15, []);
    }

    public function test_push_stock_with_wrong_context_type_is_blocked(): void
    {
        $this->expectException(\App\Exceptions\MarketplacePriceWriteBlockedException::class);
        $this->expectExceptionMessage("Stok push işlemi engellendi: Doğrulanmış stok yazma bağlamı (stock_update) eksik.");

        app(TrendyolConnector::class)->pushStock($this->listing, 15, [
            'write_context_type' => 'wrong_type',
        ]);
    }

    public function test_push_stock_with_price_change_during_pure_stock_update_is_blocked(): void
    {
        $this->expectException(\App\Exceptions\MarketplacePriceWriteBlockedException::class);
        $this->expectExceptionMessage("Stok push işlemi engellendi: Stok güncellenirken fiyat değiştirilemez.");

        app(TrendyolConnector::class)->pushStock($this->listing, 15, [
            'write_context_type' => 'stock_update',
            'store_id' => $this->store->id,
            'correlation_id' => 'stock-123',
            'idempotency_key' => 'stock-key-123',
            'actor_type' => 'system',
            'actor_id' => 'system',
            'reason' => 'System sync',
            'sale_price' => 85.00, // listing has 100.0
            'list_price' => $this->listing->list_price,
        ]);
    }

    public function test_push_stock_with_correct_context_succeeds(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            "*" => \Illuminate\Support\Facades\Http::response(['batchRequestId' => 'batch-123'], 200)
        ]);

        $response = app(TrendyolConnector::class)->pushStock($this->listing, 15, [
            'write_context_type' => 'stock_update',
            'store_id' => $this->store->id,
            'correlation_id' => 'stock-123',
            'idempotency_key' => 'stock-key-123',
            'actor_type' => 'system',
            'actor_id' => 'system',
            'reason' => 'System sync',
            'sale_price' => $this->listing->sale_price,
            'list_price' => $this->listing->list_price,
        ]);

        $this->assertEquals('queued', $response['status']);
        $this->assertEquals('batch-123', $response['batch_request_id']);
    }

    public function test_trigger_type_manipulation_is_blocked(): void
    {
        $this->expectException(\App\Exceptions\MarketplacePriceWriteBlockedException::class);
        $this->expectExceptionMessage("Fiyat push işlemi engellendi: Doğrulanmış yazma bağlamı bulunamadı.");

        app(TrendyolConnector::class)->pushPrice($this->listing, 95.0, [
            'trigger_type' => 'canary',
        ]);
    }
}
