<?php

namespace Tests\Feature;

use App\Models\ChannelClaim;
use App\Models\ChannelClaimItem;
use App\Models\ChannelOrder;
use App\Models\ChannelOrderItem;
use App\Models\ChannelOrderPackage;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\ReturnIntakeBatch;
use App\Models\ReturnIntakeItem;
use App\Models\User;
use App\Services\Returns\ReturnMatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReturnMatchingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_matches_claim_and_order_using_tracking_and_barcode_signals(): void
    {
        $user = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $entity = LegalEntity::create([
            'user_id' => $user->id,
            'name' => 'Zolm Test',
            'tax_number' => '1234567890',
            'tax_office' => 'Pamukkale',
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $store = MarketplaceStore::create([
            'user_id' => $user->id,
            'legal_entity_id' => $entity->id,
            'marketplace' => 'trendyol',
            'store_name' => 'Test Store',
            'seller_id' => '12345',
            'status' => 'connected',
            'is_active' => true,
        ]);

        $order = ChannelOrder::create([
            'store_id' => $store->id,
            'legal_entity_id' => $entity->id,
            'external_order_id' => 'EXT-1',
            'order_number' => 'TY-10001',
            'order_status' => 'created',
            'customer_name' => 'Ramazan Depocu',
            'last_synced_at' => now(),
        ]);

        $package = ChannelOrderPackage::create([
            'store_id' => $store->id,
            'channel_order_id' => $order->id,
            'external_package_id' => 'PK-1',
            'package_number' => 'PK-1',
            'package_status' => 'delivered',
            'cargo_tracking_number' => 'TF-114504168216',
            'cargo_barcode' => 'TF-114504168216',
            'last_synced_at' => now(),
        ]);

        ChannelOrderItem::create([
            'store_id' => $store->id,
            'channel_order_id' => $order->id,
            'channel_order_package_id' => $package->id,
            'external_line_id' => 'LINE-1',
            'stock_code' => 'SKU-1',
            'barcode' => '8690000000001',
            'product_name' => 'Berjer Kılıfı',
            'quantity' => 1,
            'line_status' => 'delivered',
            'last_synced_at' => now(),
        ]);

        $claim = ChannelClaim::create([
            'store_id' => $store->id,
            'external_claim_id' => 'CLM-1',
            'order_number' => 'TY-10001',
            'cargo_tracking_number' => 'TF-114504168216',
            'status' => 'delivered',
            'type' => 'return',
            'customer_name' => 'Ramazan Depocu',
            'created_date' => now(),
            'last_synced_at' => now(),
        ]);

        ChannelClaimItem::create([
            'claim_id' => $claim->id,
            'external_item_id' => 'CLI-1',
            'product_name' => 'Berjer Kılıfı',
            'barcode' => '8690000000001',
            'stock_code' => 'SKU-1',
            'quantity' => 1,
            'status' => 'delivered',
        ]);

        $batch = ReturnIntakeBatch::create([
            'user_id' => $user->id,
            'source' => 'zolm_mobile',
            'intake_mode' => 'undamaged',
            'status' => 'submitted',
            'captured_at' => now(),
        ]);

        $item = ReturnIntakeItem::create([
            'batch_id' => $batch->id,
            'submitted_by_user_id' => $user->id,
            'intake_type' => 'undamaged',
            'intake_status' => 'queued',
            'condition_status' => 'undamaged',
            'decision_status' => 'pending',
            'manual_reference' => 'TY-10001',
            'operator_barcode' => '8690000000001',
            'arrived_at' => now(),
        ]);

        $result = app(ReturnMatchingService::class)->match($item, [
            'tracking_number' => 'TF-114504168216',
            'order_number' => 'TY-10001',
            'product_barcode' => null,
            'customer_name' => 'Ramazan',
        ]);

        $this->assertSame($store->id, $result['store_id']);
        $this->assertSame($claim->id, $result['channel_claim_id']);
        $this->assertSame($order->id, $result['channel_order_id']);
        $this->assertSame($package->id, $result['channel_order_package_id']);
        $this->assertSame('tracking', $result['matched_by']);
        $this->assertSame('matched', $result['product_verification_status']);
        $this->assertSame('ready_for_decision', $result['intake_status']);
        $this->assertGreaterThanOrEqual(78, (float) $result['matching_confidence']);
    }
}
