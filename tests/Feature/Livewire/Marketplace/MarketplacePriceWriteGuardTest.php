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

        $adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin']);

        $this->adminUser = User::factory()->create([
            'email' => 'admin@zolm.test',
            'role_id' => $adminRole->id,
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

    public function test_push_stock_without_context_is_blocked_fail_closed(): void
    {
        $this->expectException(\App\Exceptions\MarketplacePriceWriteBlockedException::class);
        $this->expectExceptionMessage("Stok push işlemi engellendi: Doğrulanmış stok yazma bağlamı (stock_update) eksik.");

        app(TrendyolConnector::class)->pushStock($this->listing, 15, []);
    }

    public function test_push_stock_with_price_change_during_pure_stock_update_is_blocked(): void
    {
        $this->expectException(\App\Exceptions\MarketplacePriceWriteBlockedException::class);
        $this->expectExceptionMessage("Stok push işlemi engellendi: Stok güncellenirken fiyat değiştirilemez.");

        app(TrendyolConnector::class)->pushStock($this->listing, 15, [
            'write_context_type' => 'stock_update',
            'sale_price' => 85.00, // listing has 100.0
        ]);
    }
}
