<?php

namespace Tests\Feature;

use App\Livewire\MarketplaceOrders;
use App\Models\CargoCarrierAccount;
use App\Models\ChannelOrder;
use App\Models\ChannelOrderPackage;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\Shipment;
use App\Models\User;
use App\Services\Cargo\WooCommerceSuratTrackingSyncService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WooCommerceSuratTrackingSyncServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createMinimalSchema();
    }

    public function test_it_matches_woocommerce_package_by_customer_name_and_writes_surat_tracking(): void
    {
        [$account, $package] = $this->createWooPackage([
            'customer_name' => 'Meltem Ünal',
            'shipment_city' => 'İzmir',
            'shipment_district' => 'Güzelbahçe',
        ]);

        Http::fake($this->suratReportFake([
            [
                'AliciUnvan' => 'Meltem Ünal',
                'KargoTakipNo' => 'SRK-16701',
                'KargonunDurumu' => 'Gönderi Yolda',
                'KargonunDurumuSayi' => 3,
                'KargonunBulunduguYer' => 'Transfer Merkezi',
                'Evraktarihi' => now()->toDateTimeString(),
                'SonHareketTarihi' => now()->toDateTimeString(),
                'ToplamDesiKg' => 8,
                'Tutar' => '100,00',
            ],
        ]));

        $summary = app(WooCommerceSuratTrackingSyncService::class)->sync([
            'limit' => 10,
            'lookback_days' => 14,
            'archive_report' => false,
        ]);

        $this->assertSame(1, $summary['matched'], json_encode($summary));
        $this->assertSame(1, $summary['updated'], json_encode($summary));

        $package->refresh();
        $this->assertSame('Sürat Kargo', $package->cargo_company);
        $this->assertSame('SRK-16701', $package->cargo_tracking_number);
        $this->assertSame('In transit', $package->package_status);
        $this->assertNotNull($package->shipped_at);
        $this->assertSame('In transit', $package->order()->first()->order_status);

        $shipment = Shipment::query()->where('channel_order_package_id', $package->id)->firstOrFail();
        $this->assertSame($account->id, $shipment->cargo_carrier_account_id);
        $this->assertSame('SRK-16701', $shipment->tracking_number);
        $this->assertSame('in_transit', $shipment->status);
        $this->assertSame('Gönderi Yolda', $shipment->status_label);

        $component = new MarketplaceOrders();
        $this->assertSame('Yolda', $component->humanStatus($package->package_status));
        $this->assertSame('info', $component->statusTone($package->package_status));
    }

    public function test_it_skips_ambiguous_customer_name_matches(): void
    {
        [, $package] = $this->createWooPackage([
            'customer_name' => 'Ada Müşteri',
            'shipment_city' => 'İstanbul',
            'shipment_district' => 'Kadıköy',
        ]);

        Http::fake($this->suratReportFake([
            [
                'AliciUnvan' => 'Ada Müşteri',
                'KargoTakipNo' => 'SRK-1',
                'KargonunDurumu' => 'Gönderi Yolda',
                'KargonunDurumuSayi' => 3,
                'Evraktarihi' => now()->toDateTimeString(),
            ],
            [
                'AliciUnvan' => 'Ada Müşteri',
                'KargoTakipNo' => 'SRK-2',
                'KargonunDurumu' => 'Gönderi Yolda',
                'KargonunDurumuSayi' => 3,
                'Evraktarihi' => now()->addHour()->toDateTimeString(),
            ],
        ]));

        $summary = app(WooCommerceSuratTrackingSyncService::class)->sync([
            'limit' => 10,
            'lookback_days' => 14,
            'archive_report' => false,
        ]);

        $this->assertSame(0, $summary['matched']);
        $this->assertSame(1, $summary['ambiguous']);

        $package->refresh();
        $this->assertNull($package->cargo_tracking_number);
        $this->assertSame('processing', $package->package_status);
    }

    /**
     * @param  array<string, mixed>  $orderOverrides
     * @return array{0: CargoCarrierAccount, 1: ChannelOrderPackage}
     */
    protected function createWooPackage(array $orderOverrides = []): array
    {
        $user = User::query()->create([
            'name' => 'Test User',
            'email' => 'test-' . uniqid() . '@example.com',
            'password' => 'password',
        ]);
        $legalEntity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'ZOLM Test',
            'tax_number' => '1234567890',
            'currency' => 'TRY',
        ]);
        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => 'woocommerce',
            'store_name' => 'Woo Test',
            'seller_id' => 'woo-test-' . $user->id,
            'status' => 'active',
            'currency' => 'TRY',
            'is_active' => true,
            'uses_own_cargo' => true,
        ]);
        $account = CargoCarrierAccount::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'carrier_code' => 'surat',
            'carrier_name' => 'Sürat Kargo',
            'account_name' => 'ZOLM Sürat',
            'customer_code' => 'TEST-CUSTOMER',
            'sender_username' => 'TEST-USER',
            'sender_password_encrypted' => 'TEST-PASS',
            'query_password_encrypted' => 'QUERY-PASS',
            'query_base_url' => 'https://api01.suratkargo.com.tr',
            'api_base_url' => 'https://api01.suratkargo.com.tr',
            'is_default' => true,
            'is_active' => true,
        ]);
        $order = ChannelOrder::query()->create(array_merge([
            'store_id' => $store->id,
            'legal_entity_id' => $legalEntity->id,
            'external_order_id' => '16701',
            'order_number' => '16701',
            'order_status' => 'processing',
            'customer_name' => 'Meltem Ünal',
            'customer_phone' => '05551234567',
            'billing_name' => 'Meltem Ünal',
            'shipment_city' => 'İzmir',
            'shipment_district' => 'Güzelbahçe',
            'ordered_at' => now()->subDays(2),
            'raw_payload' => [
                'shipping' => [
                    'address_1' => 'Test Mahallesi',
                ],
            ],
        ], $orderOverrides));
        $package = ChannelOrderPackage::query()->create([
            'store_id' => $store->id,
            'channel_order_id' => $order->id,
            'external_package_id' => '16701',
            'package_number' => '16701',
            'package_status' => 'processing',
            'cargo_company' => 'Ücretsiz gönderim',
            'shipment_provider' => 'free_shipping',
            'raw_payload' => [],
        ]);

        return [$account, $package];
    }

    /**
     * @param  array<int, array<string, mixed>>  $trackingRows
     */
    protected function suratReportFake(array $trackingRows): callable
    {
        return function (Request $request) use ($trackingRows) {
            if (str_contains($request->url(), '/api/BarkodDetay/GonderilenKargoDetayi')) {
                return Http::response([
                    'IsError' => false,
                    'GonderilenKargoDetayi' => [],
                ], 200);
            }

            if (
                str_contains($request->url(), '/api/KargoTakipHareketCoklu')
                && str_contains($request->url(), 'KargonunDurumuSayi=3')
            ) {
                return Http::response([
                    'IsError' => false,
                    'Gonderiler' => $trackingRows,
                ], 200);
            }

            return Http::response([
                'IsError' => false,
                'Gonderiler' => [],
            ], 200);
        };
    }

    protected function createMinimalSchema(): void
    {
        foreach ([
            'shipment_events',
            'shipment_costs',
            'shipment_parcels',
            'shipment_items',
            'shipments',
            'channel_order_items',
            'channel_order_packages',
            'channel_orders',
            'cargo_carrier_accounts',
            'marketplace_stores',
            'legal_entities',
            'users',
            'wa_suppressions',
            'wa_contacts',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });

        Schema::create('legal_entities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->string('name');
            $table->string('tax_number');
            $table->string('currency', 3)->default('TRY');
            $table->timestamps();
        });

        Schema::create('marketplace_stores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->foreignId('legal_entity_id');
            $table->string('marketplace', 50);
            $table->string('store_name', 150);
            $table->string('seller_id', 100)->nullable();
            $table->string('status', 30)->default('active');
            $table->string('currency', 3)->default('TRY');
            $table->boolean('is_active')->default(true);
            $table->boolean('uses_own_cargo')->default(false);
            $table->timestamps();
        });

        Schema::create('cargo_carrier_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->foreignId('legal_entity_id')->nullable();
            $table->string('carrier_code', 30)->default('surat');
            $table->string('carrier_name', 120)->default('Sürat Kargo');
            $table->string('account_name', 150)->nullable();
            $table->string('customer_code', 80)->nullable();
            $table->string('sender_username', 120)->nullable();
            $table->text('sender_password_encrypted')->nullable();
            $table->text('query_password_encrypted')->nullable();
            $table->string('api_base_url')->nullable();
            $table->string('query_base_url')->nullable();
            $table->string('origin_city', 120)->nullable();
            $table->string('origin_district', 120)->nullable();
            $table->text('origin_address')->nullable();
            $table->string('contact_phone', 40)->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('settings_json')->nullable();
            $table->timestamps();
        });

        Schema::create('channel_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id');
            $table->foreignId('legal_entity_id');
            $table->string('external_order_id', 120);
            $table->string('order_number', 100);
            $table->string('order_status', 50)->default('new');
            $table->string('customer_name', 150)->nullable();
            $table->string('customer_phone', 32)->nullable();
            $table->string('billing_name', 150)->nullable();
            $table->string('shipment_city', 120)->nullable();
            $table->string('shipment_district', 120)->nullable();
            $table->timestamp('ordered_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
        });

        Schema::create('channel_order_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id');
            $table->foreignId('channel_order_id');
            $table->string('external_package_id', 120);
            $table->string('package_number', 120)->nullable();
            $table->string('package_status', 50)->default('new');
            $table->string('cargo_company', 120)->nullable();
            $table->string('cargo_tracking_number', 120)->nullable();
            $table->string('cargo_barcode', 120)->nullable();
            $table->decimal('cargo_desi', 8, 2)->nullable();
            $table->string('shipment_provider', 120)->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
        });

        Schema::create('channel_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id');
            $table->foreignId('channel_order_id');
            $table->foreignId('channel_order_package_id')->nullable();
            $table->foreignId('mp_product_id')->nullable();
            $table->string('external_line_id', 120);
            $table->string('stock_code', 100)->nullable();
            $table->string('barcode', 100)->nullable();
            $table->string('product_name')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 12, 2)->nullable();
            $table->decimal('gross_amount', 12, 2)->nullable();
            $table->string('line_status', 50)->default('new');
            $table->boolean('is_matched')->default(false);
            $table->timestamps();
        });

        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->foreignId('legal_entity_id')->nullable();
            $table->foreignId('store_id')->nullable();
            $table->foreignId('channel_order_id')->nullable();
            $table->foreignId('channel_order_package_id')->nullable();
            $table->foreignId('cargo_carrier_account_id')->nullable();
            $table->string('shipment_no', 80)->unique();
            $table->string('source_type', 50)->default('manual');
            $table->string('direction', 20)->default('outgoing');
            $table->string('flow_type', 40)->default('order');
            $table->string('carrier_code', 30)->default('surat');
            $table->string('carrier_name', 120)->default('Sürat Kargo');
            $table->string('external_shipment_id', 120)->nullable();
            $table->string('reference_number', 120)->nullable();
            $table->string('order_number', 120)->nullable();
            $table->string('package_number', 120)->nullable();
            $table->string('tracking_number', 120)->nullable();
            $table->string('barcode', 160)->nullable();
            $table->string('status', 40)->default('draft');
            $table->string('status_label', 160)->nullable();
            $table->string('customer_name', 200)->nullable();
            $table->string('customer_phone', 40)->nullable();
            $table->string('destination_city', 120)->nullable();
            $table->string('destination_district', 120)->nullable();
            $table->text('destination_address')->nullable();
            $table->string('sender_name', 200)->nullable();
            $table->string('sender_phone', 40)->nullable();
            $table->string('origin_city', 120)->nullable();
            $table->string('origin_district', 120)->nullable();
            $table->text('origin_address')->nullable();
            $table->unsignedSmallInteger('parcel_count')->default(1);
            $table->decimal('total_desi', 10, 2)->default(0);
            $table->decimal('total_weight', 10, 2)->default(0);
            $table->decimal('expected_cost', 12, 2)->default(0);
            $table->decimal('actual_cost', 12, 2)->default(0);
            $table->decimal('invoice_cost', 12, 2)->default(0);
            $table->decimal('cost_delta', 12, 2)->default(0);
            $table->string('currency', 3)->default('TRY');
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('last_tracked_at')->nullable();
            $table->timestamp('last_event_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('raw_payload')->nullable();
            $table->json('meta_json')->nullable();
            $table->timestamps();
        });

        Schema::create('shipment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id');
            $table->foreignId('channel_order_item_id')->nullable();
            $table->foreignId('mp_product_id')->nullable();
            $table->string('stock_code', 120)->nullable();
            $table->string('barcode', 120)->nullable();
            $table->string('product_name')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->unsignedSmallInteger('expected_pieces')->default(1);
            $table->decimal('expected_desi', 10, 2)->default(0);
            $table->decimal('expected_cost', 12, 2)->default(0);
            $table->json('meta_json')->nullable();
            $table->timestamps();
        });

        Schema::create('shipment_parcels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id');
            $table->unsignedSmallInteger('parcel_index')->default(1);
            $table->string('tracking_number', 120)->nullable();
            $table->string('barcode', 160)->nullable();
            $table->decimal('desi', 10, 2)->default(0);
            $table->decimal('weight', 10, 2)->default(0);
            $table->unsignedSmallInteger('piece_count')->default(1);
            $table->string('status', 40)->default('draft');
            $table->timestamps();
        });

        Schema::create('shipment_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id');
            $table->string('cost_source', 40);
            $table->string('cost_type', 60);
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('direction', 10)->default('debit');
            $table->string('currency', 3)->default('TRY');
            $table->timestamp('cost_date')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
        });

        Schema::create('shipment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id');
            $table->string('carrier_code', 30)->default('surat');
            $table->string('event_code', 80)->nullable();
            $table->string('event_status', 80)->nullable();
            $table->text('event_description')->nullable();
            $table->timestamp('event_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->boolean('is_terminal')->default(false);
            $table->json('raw_payload')->nullable();
            $table->timestamps();
        });

        Schema::create('wa_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id');
            $table->string('wc_customer_id', 80)->nullable();
            $table->foreignId('zolm_customer_id')->nullable();
            $table->text('phone_e164_encrypted');
            $table->string('phone_hash', 64);
            $table->string('first_name', 120)->nullable();
            $table->string('last_name', 120)->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->unique(['store_id', 'phone_hash']);
        });

        Schema::create('wa_suppressions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id');
            $table->string('reason', 40);
            $table->text('details')->nullable();
            $table->timestamp('suppressed_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }
}
