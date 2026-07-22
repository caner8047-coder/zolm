<?php

namespace App\Services\Demo;

use App\Models\AdAccount;
use App\Models\AdCampaign;
use App\Models\CargoCarrierAccount;
use App\Models\ChannelClaim;
use App\Models\ChannelClaimItem;
use App\Models\ChannelListing;
use App\Models\ChannelOrder;
use App\Models\ChannelOrderItem;
use App\Models\ChannelOrderPackage;
use App\Models\ChannelProduct;
use App\Models\CrmCase;
use App\Models\CrmContact;
use App\Models\CrmContactIdentity;
use App\Models\IntegrationConnection;
use App\Models\IntegrationSyncProfile;
use App\Models\IntegrationSyncRun;
use App\Models\LegalEntity;
use App\Models\MarketplaceQuestion;
use App\Models\MarketplaceStore;
use App\Models\Material;
use App\Models\MpProduct;
use App\Models\OrderFinancialEvent;
use App\Models\OrderProfitSnapshot;
use App\Models\Recipe;
use App\Models\RecipeLine;
use App\Models\ReturnIntakeAnalysis;
use App\Models\ReturnIntakeBatch;
use App\Models\ReturnIntakeDecision;
use App\Models\ReturnIntakeItem;
use App\Models\Shipment;
use App\Models\SupportArtifactVersion;
use App\Models\SupportChannel;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\SupportOrganizationMembership;
use App\Models\SupportReleaseEvent;
use App\Models\SupportReleasePackage;
use App\Models\SupportReleasePackageItem;
use App\Models\SupportRoleAssignment;
use App\Models\TrendyolBoosterProduct;
use App\Models\User;
use App\Models\WaAccount;
use App\Models\WaConsentEvent;
use App\Models\WaContact;
use App\Models\WaContactPreference;
use App\Models\WaConversation;
use App\Models\WaInboundMessage;
use App\Models\WaKnowledgeArticle;
use App\Models\WaSetting;
use App\Models\WaTemplate;
use App\Services\Marketplace\MarketplaceProviderRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * ZOLM'un ana modüllerini aynı tenant veri omurgasında buluşturan demo veri üreticisi.
 *
 * Bu servis dış servis çağrısı yapmaz. Bağlantılar `demo`, otomasyonlar ise kapalı
 * oluşturulur. Amaç ekranları ve uygulama-içi ilişkileri güvenle doğrulamaktır;
 * gerçek pazaryeri sandbox/API doğrulaması değildir.
 */
class ZolmDemoTenantSeeder
{
    public const VERSION = 'mockdata1_full_v1';

    public const MARKER = 'ZOLM-DEMO';

    private CarbonImmutable $anchor;

    /** @var array<string, MarketplaceStore> */
    private array $stores = [];

    /** @var array<string, ChannelOrder> */
    private array $orders = [];

    /** @var array<string, ChannelOrderPackage> */
    private array $packages = [];

    /** @var array<string, ChannelOrderItem> */
    private array $orderItems = [];

    /** @var array<string, ChannelClaim> */
    private array $claims = [];

    /** @var array<int, MpProduct> */
    private array $products = [];

    public function __construct()
    {
        $this->anchor = CarbonImmutable::parse('2026-07-01 09:00:00', 'Europe/Istanbul');
    }

    /**
     * @return array<string, array{status: string, records: int, detail: string}>
     */
    public function seed(User $user, LegalEntity $legalEntity): array
    {
        $this->stores = [];
        $this->orders = [];
        $this->packages = [];
        $this->orderItems = [];
        $this->claims = [];
        $this->products = [];

        $modules = [
            'urun_ve_uretim' => fn (): int => $this->seedProductsAndProduction($user),
            'pazaryeri_omurgasi' => fn (): int => $this->seedMarketplaceGraph($user, $legalEntity),
            'crm' => fn (): int => $this->seedCrm($user),
            'kargo' => fn (): int => $this->seedCargo($user, $legalEntity),
            'iade' => fn (): int => $this->seedReturns($user),
            'reklam_ve_booster' => fn (): int => $this->seedAdsAndBooster($user),
            'whatsapp' => fn (): int => $this->seedWhatsApp($user),
            'customer_care' => fn (): int => $this->seedCustomerCare($user, $legalEntity),
        ];

        $results = [];

        foreach ($modules as $name => $callback) {
            try {
                $records = DB::transaction($callback);
                $results[$name] = [
                    'status' => 'ok',
                    'records' => $records,
                    'detail' => 'Deterministik demo verisi hazır.',
                ];
            } catch (Throwable $exception) {
                report($exception);
                $results[$name] = [
                    'status' => 'failed',
                    'records' => 0,
                    'detail' => $exception->getMessage(),
                ];
            }
        }

        return $results;
    }

    private function seedProductsAndProduction(User $user): int
    {
        $this->requireTables(['mp_products', 'materials', 'recipes', 'recipe_lines']);

        $productRows = [
            [
                'stock_code' => self::MARKER.'-BERJER-01',
                'barcode' => '8690000001001',
                'product_name' => 'Luna Berjer - Krem',
                'category_name' => 'Berjer',
                'sale_price' => 8990,
                'cogs' => 4200,
                'packaging_cost' => 180,
                'cargo_cost' => 420,
                'stock_quantity' => 18,
            ],
            [
                'stock_code' => self::MARKER.'-KANEPE-01',
                'barcode' => '8690000001002',
                'product_name' => 'Mira Üçlü Kanepe - Antrasit',
                'category_name' => 'Kanepe',
                'sale_price' => 24990,
                'cogs' => 13200,
                'packaging_cost' => 350,
                'cargo_cost' => 980,
                'stock_quantity' => 7,
            ],
            [
                'stock_code' => self::MARKER.'-PUF-01',
                'barcode' => '8690000001003',
                'product_name' => 'Luna Puf - Krem',
                'category_name' => 'Puf',
                'sale_price' => 3490,
                'cogs' => 1450,
                'packaging_cost' => 90,
                'cargo_cost' => 210,
                'stock_quantity' => 3,
            ],
        ];

        MpProduct::withoutEvents(function () use ($productRows, $user): void {
            foreach ($productRows as $row) {
                $product = MpProduct::updateOrCreate(
                    ['user_id' => $user->id, 'stock_code' => $row['stock_code']],
                    array_merge($row, [
                        'user_id' => $user->id,
                        'model_code' => str_replace(self::MARKER.'-', '', $row['stock_code']),
                        'brand' => 'ZOLM Demo',
                        'unit_name' => 'Adet',
                        'vat_rate' => 20,
                        'cost_vat_rate' => 20,
                        'commission_rate' => 18,
                        'critical_stock_threshold' => 5,
                        'return_rate' => 4.5,
                        'status' => 'active',
                        'product_type' => 'single',
                        'cost_source' => 'demo',
                        'logistics_source' => 'demo',
                        'import_source' => self::VERSION,
                        'last_synced_at' => $this->anchor,
                    ])
                );

                $this->products[] = $product;
            }
        });

        $materials = [
            ['code' => self::MARKER.'-KUMAS', 'name' => 'Demo Keten Kumaş', 'category' => 'fabric', 'base_unit' => 'm', 'unit_price' => 245, 'fabric_width_cm' => 140],
            ['code' => self::MARKER.'-SUNGER', 'name' => 'Demo 32 DNS Sünger', 'category' => 'foam', 'base_unit' => 'm3', 'unit_price' => 3100, 'density_kg_m3' => 32],
            ['code' => self::MARKER.'-AYAK', 'name' => 'Demo Gürgen Ayak', 'category' => 'wood', 'base_unit' => 'pcs', 'unit_price' => 175],
        ];

        $materialModels = [];
        foreach ($materials as $row) {
            $materialModels[] = Material::updateOrCreate(
                ['user_id' => $user->id, 'code' => $row['code']],
                array_merge($row, [
                    'user_id' => $user->id,
                    'default_waste_rate' => 0.08,
                    'rounding_mode' => 'ceil_step',
                    'rounding_step' => 0.1,
                    'currency' => 'TRY',
                    'supplier' => 'ZOLM Demo Tedarikci',
                    'notes' => self::VERSION,
                    'is_active' => true,
                    'tags' => ['demo', 'mockdata1'],
                    'last_price_updated_at' => $this->anchor,
                ])
            );
        }

        $recipe = Recipe::updateOrCreate(
            [
                'user_id' => $user->id,
                'stock_code' => $this->products[0]->stock_code,
                'version' => 'demo-v1',
            ],
            [
                'mp_product_id' => $this->products[0]->id,
                'name' => 'Luna Berjer Demo Recetesi',
                'status' => 'active',
                'notes' => self::VERSION,
            ]
        );

        foreach ($materialModels as $index => $material) {
            RecipeLine::updateOrCreate(
                [
                    'recipe_id' => $recipe->id,
                    'material_id' => $material->id,
                    'operation' => ['terzihane', 'sunger', 'marangoz'][$index],
                ],
                [
                    'calc_type' => $index === 2 ? 'piece' : 'fixed_qty',
                    'constant_qty' => [4.2, 0.12, 4][$index],
                    'calculated_qty' => [4.2, 0.12, 4][$index],
                    'calculated_unit' => $material->base_unit,
                    'notes' => self::VERSION,
                    'sort_order' => $index + 1,
                ]
            );
        }

        return count($this->products) + count($materialModels) + 1 + count($materialModels);
    }

    private function seedMarketplaceGraph(User $user, LegalEntity $legalEntity): int
    {
        $this->requireTables([
            'marketplace_stores', 'integration_connections', 'integration_sync_profiles',
            'integration_sync_runs', 'channel_products', 'channel_listings', 'channel_orders',
            'channel_order_packages', 'channel_order_items', 'order_financial_events',
            'order_profit_snapshots', 'channel_claims', 'channel_claim_items', 'marketplace_questions',
        ]);

        $count = 0;

        foreach (MarketplaceProviderRegistry::providers() as $provider => $providerConfig) {
            $index = count($this->stores);
            $at = $this->anchor->addDays($index);
            $product = $this->products[$index % count($this->products)];
            $providerCode = strtoupper($provider);
            $sellerId = 'demo-'.$this->tenantKey($user).'-'.$provider;
            $legacySellerId = 'demo-'.Str::slug(Str::before((string) $user->email, '@')).'-'.$provider;

            $store = MarketplaceStore::where('marketplace', $provider)
                ->where('seller_id', $sellerId)
                ->first();

            if (! $store && $legacySellerId !== $sellerId) {
                $store = MarketplaceStore::where('user_id', $user->id)
                    ->where('marketplace', $provider)
                    ->where('seller_id', $legacySellerId)
                    ->where('store_code', self::MARKER.'-'.$providerCode)
                    ->first();

                $store?->forceFill(['seller_id' => $sellerId])->save();
            }

            if ($store && (int) $store->user_id !== (int) $user->id) {
                throw new \RuntimeException("Demo seller ID başka kullanıcıya ait: {$provider} / {$sellerId}");
            }

            $store ??= new MarketplaceStore([
                'marketplace' => $provider,
                'seller_id' => $sellerId,
            ]);
            $store->fill([
                'user_id' => $user->id,
                'legal_entity_id' => $legalEntity->id,
                'store_name' => ($providerConfig['label'] ?? $provider).' Demo Mağazası',
                'store_code' => self::MARKER.'-'.$providerCode,
                'status' => 'demo',
                'timezone' => 'Europe/Istanbul',
                'currency' => 'TRY',
                'is_active' => true,
                'uses_own_cargo' => false,
                'last_synced_at' => $at,
            ])->save();
            $this->assertTenantInvariant($user, $legalEntity, $store);
            $this->stores[$provider] = $store;
            $count++;

            IntegrationConnection::updateOrCreate(
                ['store_id' => $store->id, 'provider' => $provider],
                [
                    'auth_type' => 'demo',
                    'credentials_encrypted' => $this->demoCredentials($provider, $store),
                    'webhook_secret' => 'demo-webhook-secret-'.$provider,
                    'webhook_url' => 'https://mock.invalid/webhooks/'.$provider,
                    'api_base_url' => 'https://mock.invalid/'.$provider,
                    'status' => 'demo',
                    'last_verified_at' => $at,
                    'last_error' => null,
                ]
            );
            $count++;

            $syncDefaults = IntegrationSyncProfile::defaultsForMarketplace($provider);
            IntegrationSyncProfile::updateOrCreate(
                ['store_id' => $store->id],
                array_replace($syncDefaults, [
                    'orders_enabled' => false,
                    'finance_enabled' => false,
                    'products_enabled' => false,
                    'claims_enabled' => false,
                    'questions_enabled' => false,
                    'webhook_enabled' => false,
                    'price_push_enabled' => false,
                    'stock_push_enabled' => false,
                    'nightly_repair_sync_enabled' => false,
                    'max_parallel_jobs' => 1,
                    'extra_settings' => [
                        'demo' => true,
                        'demo_version' => self::VERSION,
                        'network_access' => 'blocked',
                    ],
                ])
            );
            $count++;

            foreach (['orders', 'products', 'finance'] as $syncType) {
                IntegrationSyncRun::updateOrCreate(
                    [
                        'store_id' => $store->id,
                        'sync_type' => $syncType,
                        'trigger_type' => 'demo',
                    ],
                    [
                        'status' => 'completed',
                        'started_at' => $at,
                        'finished_at' => $at->addSeconds(2),
                        'duration_ms' => 2000,
                        'items_received' => 1,
                        'items_created' => 1,
                        'items_updated' => 0,
                        'items_skipped' => 0,
                        'rate_limit_hits' => 0,
                        'error_count' => 0,
                        'notes_json' => [
                            'demo' => true,
                            'demo_version' => self::VERSION,
                            'network_request' => false,
                        ],
                    ]
                );
                $count++;
            }

            $channelProduct = ChannelProduct::updateOrCreate(
                ['store_id' => $store->id, 'external_product_id' => self::MARKER.'-'.$providerCode.'-P1'],
                [
                    'external_parent_id' => self::MARKER.'-'.$providerCode.'-MODEL-1',
                    'stock_code' => $product->stock_code,
                    'barcode' => $product->barcode,
                    'title' => $product->product_name,
                    'brand' => $product->brand,
                    'category_name' => $product->category_name,
                    'vat_rate' => 20,
                    'raw_payload' => $this->markerPayload($provider),
                    'last_synced_at' => $at,
                ]
            );
            $count++;

            $listing = ChannelListing::updateOrCreate(
                ['store_id' => $store->id, 'listing_id' => self::MARKER.'-'.$providerCode.'-L1'],
                [
                    'channel_product_id' => $channelProduct->id,
                    'mp_product_id' => $product->id,
                    'listing_status' => 'active',
                    'sale_price' => (float) $product->sale_price,
                    'list_price' => (float) $product->sale_price * 1.1,
                    'commission_rate' => 18,
                    'commission_source' => 'demo',
                    'commission_synced_at' => $at,
                    'currency' => 'TRY',
                    'stock_quantity' => $product->stock_quantity,
                    'shipping_days' => 2,
                    'shipping_type' => 'normal',
                    'published_at' => $at->subMonth(),
                    'last_price_sync_at' => $at,
                    'last_stock_sync_at' => $at,
                    'last_synced_at' => $at,
                ]
            );
            $count++;

            $orderStatus = ['delivered', 'new', 'shipped', 'returned', 'cancelled'][$index % 5];
            $externalOrderId = self::MARKER.'-'.$providerCode.'-O1';
            $order = ChannelOrder::updateOrCreate(
                ['store_id' => $store->id, 'external_order_id' => $externalOrderId],
                [
                    'legal_entity_id' => $legalEntity->id,
                    'order_number' => $externalOrderId,
                    'order_status' => $orderStatus,
                    'commercial_type' => 'b2c',
                    'currency' => 'TRY',
                    'exchange_rate' => 1,
                    'customer_name' => 'Demo Müşteri '.($index + 1),
                    'customer_email' => 'musteri'.($index + 1).'@example.test',
                    'customer_phone' => '+905550000'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                    'billing_name' => 'Demo Müşteri '.($index + 1),
                    'shipment_country' => 'Turkiye',
                    'shipment_city' => ['Istanbul', 'Ankara', 'Izmir'][$index % 3],
                    'shipment_district' => 'Demo İlçe',
                    'ordered_at' => $at,
                    'approved_at' => $orderStatus === 'cancelled' ? null : $at->addHour(),
                    'delivered_at' => $orderStatus === 'delivered' ? $at->addDays(3) : null,
                    'cancelled_at' => $orderStatus === 'cancelled' ? $at->addHours(4) : null,
                    'returned_at' => $orderStatus === 'returned' ? $at->addDays(5) : null,
                    'last_synced_at' => $at->addDays(6),
                    'raw_payload' => $this->markerPayload($provider),
                ]
            );
            $this->orders[$provider] = $order;
            $count++;

            $package = ChannelOrderPackage::updateOrCreate(
                ['channel_order_id' => $order->id, 'external_package_id' => self::MARKER.'-'.$providerCode.'-PK1'],
                [
                    'store_id' => $store->id,
                    'package_number' => self::MARKER.'-'.$providerCode.'-PK1',
                    'package_status' => $orderStatus,
                    'cargo_company' => 'Demo Kargo',
                    'cargo_tracking_number' => 'DEMO-TRK-'.$providerCode.'-1',
                    'cargo_barcode' => 'DEMO-BAR-'.$providerCode.'-1',
                    'cargo_desi' => 8.5,
                    'shipment_provider' => 'demo',
                    'shipped_at' => in_array($orderStatus, ['shipped', 'delivered', 'returned'], true) ? $at->addDay() : null,
                    'delivered_at' => $orderStatus === 'delivered' ? $at->addDays(3) : null,
                    'last_synced_at' => $at->addDays(6),
                    'raw_payload' => $this->markerPayload($provider),
                ]
            );
            $this->packages[$provider] = $package;
            $count++;

            $grossAmount = (float) $product->sale_price;
            $item = ChannelOrderItem::updateOrCreate(
                ['store_id' => $store->id, 'external_line_id' => self::MARKER.'-'.$providerCode.'-OL1'],
                [
                    'channel_order_id' => $order->id,
                    'channel_order_package_id' => $package->id,
                    'channel_listing_id' => $listing->id,
                    'mp_product_id' => $product->id,
                    'stock_code' => $product->stock_code,
                    'barcode' => $product->barcode,
                    'product_name' => $product->product_name,
                    'quantity' => 1,
                    'unit_price' => $grossAmount,
                    'gross_amount' => $grossAmount,
                    'discount_amount' => 0,
                    'marketplace_discount_amount' => 250,
                    'billable_amount' => $grossAmount - 250,
                    'commission_rate' => 18,
                    'vat_rate' => 20,
                    'line_status' => $orderStatus,
                    'is_matched' => true,
                    'match_source' => 'stock_code',
                    'last_synced_at' => $at,
                    'raw_payload' => $this->markerPayload($provider),
                ]
            );
            $this->orderItems[$provider] = $item;
            $count++;

            OrderFinancialEvent::updateOrCreate(
                [
                    'store_id' => $store->id,
                    'event_source' => 'demo',
                    'external_event_id' => self::MARKER.'-'.$providerCode.'-FIN1',
                ],
                [
                    'legal_entity_id' => $legalEntity->id,
                    'channel_order_id' => $order->id,
                    'channel_order_package_id' => $package->id,
                    'channel_order_item_id' => $item->id,
                    'event_type' => 'sale',
                    'reference_number' => $order->order_number,
                    'event_date' => $at,
                    'due_date' => $at->addDays(14),
                    'settlement_date' => $orderStatus === 'delivered' ? $at->addDays(14) : null,
                    'amount' => $grossAmount * 0.82,
                    'currency' => 'TRY',
                    'direction' => 'credit',
                    'status' => $orderStatus === 'delivered' ? 'settled' : 'pending',
                    'notes' => self::VERSION,
                    'raw_payload' => $this->markerPayload($provider),
                ]
            );
            $count++;

            $net = $grossAmount * 0.82;
            $profit = $net - (float) $product->cogs - (float) $product->cargo_cost - (float) $product->packaging_cost;
            OrderProfitSnapshot::updateOrCreate(
                [
                    'store_id' => $store->id,
                    'channel_order_id' => $order->id,
                    'channel_order_item_id' => null,
                    'version' => 1,
                ],
                [
                    'profit_state' => $orderStatus === 'delivered' ? 'confirmed' : 'estimated',
                    'gross_revenue' => $grossAmount,
                    'net_receivable' => $net,
                    'commission_total' => $grossAmount * 0.18,
                    'cargo_total' => $product->cargo_cost,
                    'service_fee_total' => 0,
                    'packaging_cost' => $product->packaging_cost,
                    'cogs_cost' => $product->cogs,
                    'return_effect' => $orderStatus === 'returned' ? -$grossAmount : 0,
                    'estimated_profit' => $profit,
                    'confirmed_profit' => $orderStatus === 'delivered' ? $profit : 0,
                    'margin_percent' => $grossAmount > 0 ? ($profit / $grossAmount) * 100 : 0,
                    'calculated_at' => $at->addDays(6),
                    'currency' => 'TRY',
                    'exchange_rate' => 1,
                    'profit_try' => $profit,
                ]
            );
            $count++;

            if (($providerConfig['supports']['claims'] ?? false) === true) {
                $claim = ChannelClaim::updateOrCreate(
                    ['store_id' => $store->id, 'external_claim_id' => self::MARKER.'-'.$providerCode.'-CL1'],
                    [
                        'order_number' => $order->order_number,
                        'cargo_tracking_number' => $package->cargo_tracking_number,
                        'cargo_provider' => 'Demo Kargo',
                        'status' => $index % 2 === 0 ? 'delivered' : 'pending',
                        'type' => 'return',
                        'reason' => 'Ürün beklentiyi karşılamadı',
                        'reason_detail' => 'Tamamen sentetik demo talebidir.',
                        'customer_note' => 'Renk tonunu degistirmek istiyorum.',
                        'customer_name' => $order->customer_name,
                        'created_date' => $at->addDays(4),
                        'last_synced_at' => $at->addDays(6),
                        'raw_payload' => $this->markerPayload($provider),
                    ]
                );
                $this->claims[$provider] = $claim;
                ChannelClaimItem::updateOrCreate(
                    ['claim_id' => $claim->id, 'external_item_id' => self::MARKER.'-'.$providerCode.'-CLI1'],
                    [
                        'external_order_line_id' => $item->external_line_id,
                        'product_name' => $product->product_name,
                        'barcode' => $product->barcode,
                        'stock_code' => $product->stock_code,
                        'quantity' => 1,
                        'price' => $grossAmount,
                        'status' => $claim->status,
                        'raw_payload' => $this->markerPayload($provider),
                    ]
                );
                $count += 2;
            }

            if (($providerConfig['supports']['questions'] ?? false) === true) {
                MarketplaceQuestion::updateOrCreate(
                    ['store_id' => $store->id, 'external_question_id' => self::MARKER.'-'.$providerCode.'-Q1'],
                    [
                        'channel_product_id' => $channelProduct->id,
                        'channel_listing_id' => $listing->id,
                        'channel_order_id' => $order->id,
                        'question_type' => 'product',
                        'status' => $index % 2 === 0 ? 'open' : 'answered',
                        'customer_name' => $order->customer_name,
                        'customer_external_id' => 'demo-customer-'.$index,
                        'product_name' => $product->product_name,
                        'product_sku' => $product->stock_code,
                        'product_barcode' => $product->barcode,
                        'question_text' => 'Bu ürünün ölçüleri ve tahmini kargo süresi nedir?',
                        'answer_text' => $index % 2 === 0 ? null : 'Ölçüler ürün kartında yer alır; demo teslim süresi 2 iş günüdür.',
                        'ai_suggested_answer' => 'Demo yanit taslagi: teslim suresi 2 is gunudur.',
                        'ai_confidence' => 91,
                        'ai_status' => 'drafted',
                        'asked_at' => $at->addHours(2),
                        'answered_at' => $index % 2 === 0 ? null : $at->addHours(3),
                        'last_synced_at' => $at->addDays(6),
                        'raw_payload' => $this->markerPayload($provider),
                    ]
                );
                $count++;
            }
        }

        return $count;
    }

    private function seedCrm(User $user): int
    {
        $this->requireTables(['crm_contacts', 'crm_contact_identities', 'crm_cases']);

        $store = $this->stores['trendyol'];
        $order = $this->orders['trendyol'];
        $contact = CrmContact::updateOrCreate(
            ['user_id' => $user->id, 'primary_email' => 'musteri1@example.test'],
            [
                'display_name' => 'Demo Müşteri 1',
                'normalized_name' => 'demo musteri 1',
                'primary_phone' => '+905550000001',
                'normalized_phone' => '905550000001',
                'city' => 'Istanbul',
                'district' => 'Kadıköy',
                'first_order_at' => $this->anchor,
                'last_order_at' => $this->anchor,
                'last_event_at' => $this->anchor->addDays(6),
                'last_event_type' => 'return',
                'last_event_title' => 'Demo iade talebi oluşturuldu',
                'order_count' => 3,
                'gross_revenue_total' => 33470,
                'return_count' => 1,
                'question_count' => 2,
                'open_case_count' => 1,
                'risk_score' => 45,
                'value_score' => 78,
                'status' => 'active',
                'tags_json' => ['vip', 'demo', 'iade-takip'],
                'meta_json' => ['demo' => true, 'demo_version' => self::VERSION],
            ]
        );

        CrmContactIdentity::updateOrCreate(
            [
                'user_id' => $user->id,
                'source_type' => 'marketplace_order',
                'store_id' => $store->id,
                'external_customer_id' => 'demo-customer-1',
            ],
            [
                'contact_id' => $contact->id,
                'marketplace' => 'trendyol',
                'email' => $contact->primary_email,
                'phone' => $contact->primary_phone,
                'normalized_phone' => $contact->normalized_phone,
                'name' => $contact->display_name,
                'normalized_name' => $contact->normalized_name,
                'city' => $contact->city,
                'district' => $contact->district,
                'confidence' => 98,
                'raw_payload' => $this->markerPayload('trendyol'),
            ]
        );

        CrmCase::updateOrCreate(
            ['user_id' => $user->id, 'case_key' => self::VERSION.'-return-case'],
            [
                'contact_id' => $contact->id,
                'store_id' => $store->id,
                'owner_user_id' => $user->id,
                'source_type' => 'channel_claim',
                'category' => 'return',
                'priority' => 'high',
                'status' => 'open',
                'subject_type' => ChannelOrder::class,
                'subject_id' => $order->id,
                'title' => 'Demo iade süreci takibi',
                'summary' => 'Pazaryeri, CRM ve iade modülleri arasındaki bağlantıyı test eder.',
                'sla_due_at' => $this->anchor->addDays(8),
                'meta_json' => ['demo' => true, 'demo_version' => self::VERSION],
            ]
        );

        return 3;
    }

    private function seedCargo(User $user, LegalEntity $legalEntity): int
    {
        $this->requireTables(['cargo_carrier_accounts', 'shipments']);

        $store = $this->stores['trendyol'];
        $order = $this->orders['trendyol'];
        $package = $this->packages['trendyol'];
        $account = CargoCarrierAccount::updateOrCreate(
            [
                'user_id' => $user->id,
                'carrier_code' => 'demo',
                'customer_code' => 'MOCKDATA1',
            ],
            [
                'legal_entity_id' => $legalEntity->id,
                'carrier_name' => 'Demo Kargo',
                'account_name' => 'Mockdata1 Güvenli Kargo Hesabı',
                'sender_username' => 'demo-no-network',
                'sender_password_encrypted' => 'demo-no-network',
                'query_password_encrypted' => 'demo-no-network',
                'api_base_url' => 'https://mock.invalid/cargo',
                'query_base_url' => 'https://mock.invalid/cargo-query',
                'branch_code' => 'DEMO',
                'origin_city' => 'Denizli',
                'origin_district' => 'Merkezefendi',
                'origin_address' => 'ZOLM demo depo adresi',
                'contact_name' => 'Demo Operasyon',
                'contact_phone' => '+905550000099',
                'is_default' => false,
                'is_active' => false,
                'status' => 'demo',
                'last_verified_at' => null,
                'settings_json' => [
                    'demo' => true,
                    'network_access' => 'blocked',
                    'tracking_enabled' => false,
                ],
            ]
        );

        $shipmentNo = self::MARKER.'-'.strtoupper($this->tenantKey($user)).'-SHIP-0001';
        $legacyShipmentNo = self::MARKER.'-'.strtoupper(Str::slug(Str::before((string) $user->email, '@'))).'-SHIP-0001';
        $shipment = Shipment::where('shipment_no', $shipmentNo)->first();
        if (! $shipment && $legacyShipmentNo !== $shipmentNo) {
            $shipment = Shipment::where('user_id', $user->id)
                ->where('shipment_no', $legacyShipmentNo)
                ->where('source_type', 'demo')
                ->first();
            $shipment?->forceFill(['shipment_no' => $shipmentNo])->save();
        }
        if ($shipment && (int) $shipment->user_id !== (int) $user->id) {
            throw new \RuntimeException("Demo gönderi numarası başka kullanıcıya ait: {$shipmentNo}");
        }

        $shipment ??= new Shipment(['shipment_no' => $shipmentNo]);
        $shipment->fill(
            [
                'user_id' => $user->id,
                'legal_entity_id' => $legalEntity->id,
                'store_id' => $store->id,
                'channel_order_id' => $order->id,
                'channel_order_package_id' => $package->id,
                'cargo_carrier_account_id' => $account->id,
                'source_type' => 'demo',
                'direction' => 'outgoing',
                'flow_type' => 'order',
                'carrier_code' => 'demo',
                'carrier_name' => 'Demo Kargo',
                'external_shipment_id' => self::MARKER.'-EXT-SHIP-0001',
                'reference_number' => $order->order_number,
                'order_number' => $order->order_number,
                'package_number' => $package->package_number,
                'tracking_number' => 'DEMO-TRACK-0001',
                'barcode' => 'DEMO-CARGO-BAR-0001',
                'status' => 'delivered',
                'status_label' => 'Teslim edildi (sentetik)',
                'customer_name' => $order->customer_name,
                'customer_phone' => $order->customer_phone,
                'destination_city' => $order->shipment_city,
                'destination_district' => $order->shipment_district,
                'destination_address' => 'Sentetik adres; teslimat yapılmaz.',
                'sender_name' => $legalEntity->name,
                'origin_city' => 'Denizli',
                'origin_district' => 'Merkezefendi',
                'parcel_count' => 1,
                'total_desi' => 8.5,
                'total_weight' => 6.2,
                'expected_cost' => 420,
                'actual_cost' => 430,
                'invoice_cost' => 430,
                'cost_delta' => 10,
                'currency' => 'TRY',
                'shipped_at' => $this->anchor->addDay(),
                'delivered_at' => $this->anchor->addDays(3),
                'last_tracked_at' => $this->anchor->addDays(3),
                'last_event_at' => $this->anchor->addDays(3),
                'raw_payload' => $this->markerPayload('cargo'),
                'meta_json' => ['demo' => true, 'tracking_enabled' => false],
            ]
        )->save();

        return 2;
    }

    private function seedReturns(User $user): int
    {
        $this->requireTables(['return_intake_batches', 'return_intake_items', 'return_intake_analyses', 'return_intake_decisions']);

        $provider = array_key_exists('trendyol', $this->claims) ? 'trendyol' : array_key_first($this->claims);
        if ($provider === null) {
            throw new \RuntimeException('İade senaryosu için demo claim bulunamadı.');
        }

        $batch = ReturnIntakeBatch::updateOrCreate(
            ['user_id' => $user->id, 'source' => self::VERSION],
            [
                'intake_mode' => 'damaged',
                'status' => 'completed',
                'captured_at' => $this->anchor->addDays(7),
            ]
        );

        $item = ReturnIntakeItem::updateOrCreate(
            ['batch_id' => $batch->id, 'manual_reference' => self::MARKER.'-RETURN-0001'],
            [
                'submitted_by_user_id' => $user->id,
                'store_id' => $this->stores[$provider]->id,
                'channel_claim_id' => $this->claims[$provider]->id,
                'channel_order_id' => $this->orders[$provider]->id,
                'channel_order_package_id' => $this->packages[$provider]->id,
                'intake_type' => 'damaged',
                'intake_status' => 'decisioned',
                'condition_status' => 'damaged',
                'product_verification_status' => 'matched',
                'decision_status' => 'approved',
                'matching_confidence' => 96,
                'matched_by' => 'demo',
                'detected_tracking_number' => $this->packages[$provider]->cargo_tracking_number,
                'detected_order_number' => $this->orders[$provider]->order_number,
                'detected_barcode' => $this->orderItems[$provider]->barcode,
                'detected_customer_name' => $this->orders[$provider]->customer_name,
                'cargo_provider' => 'Demo Kargo',
                'warehouse_note' => 'Sentetik ürün: sağ ön köşede demo hasar bulgusu.',
                'arrived_at' => $this->anchor->addDays(7),
                'analysis_started_at' => $this->anchor->addDays(7)->addMinutes(1),
                'analysis_completed_at' => $this->anchor->addDays(7)->addMinutes(2),
                'raw_summary_json' => ['demo' => true, 'demo_version' => self::VERSION],
            ]
        );

        ReturnIntakeAnalysis::updateOrCreate(
            ['return_intake_item_id' => $item->id, 'prompt_version' => self::VERSION],
            [
                'provider' => 'demo',
                'model' => 'offline-fixture',
                'confidence' => 94,
                'ocr_json' => ['tracking_number' => $item->detected_tracking_number],
                'classification_json' => ['condition' => 'damaged', 'severity' => 'medium'],
                'raw_response_json' => ['network_request' => false],
            ]
        );

        ReturnIntakeDecision::updateOrCreate(
            ['return_intake_item_id' => $item->id, 'decision_mode' => 'demo'],
            [
                'user_id' => $user->id,
                'decision' => 'approve_marketplace',
                'reason_code' => 'demo_damage_confirmed',
                'note' => 'Demo kararı; pazaryerine gönderilmez.',
                'marketplace_pushed_at' => null,
                'raw_payload' => ['demo' => true, 'network_request' => false],
            ]
        );

        return 4;
    }

    private function seedAdsAndBooster(User $user): int
    {
        $this->requireTables(['ad_accounts', 'ad_campaigns', 'trendyol_booster_products']);

        $account = AdAccount::updateOrCreate(
            ['user_id' => $user->id, 'external_account_id' => self::MARKER.'-ADS-TR'],
            [
                'marketplace' => 'trendyol',
                'account_name' => 'Trendyol Demo Reklam Hesabi',
                'currency_code' => 'TRY',
                'timezone' => 'Europe/Istanbul',
                'is_active' => false,
            ]
        );

        $identityHash = hash('sha256', self::VERSION.'-campaign-1');
        AdCampaign::updateOrCreate(
            ['ad_account_id' => $account->id, 'campaign_identity_hash' => $identityHash],
            [
                'user_id' => $user->id,
                'channel_code' => 'trendyol',
                'external_campaign_id' => self::MARKER.'-CAMPAIGN-1',
                'campaign_key' => self::VERSION.'-campaign-1',
                'name' => 'Luna Koleksiyonu Demo Kampanyasi',
                'status' => 'paused',
                'targeting_type' => 'product',
                'start_at' => $this->anchor,
                'end_at' => $this->anchor->addMonth(),
                'daily_budget' => 750,
                'total_budget' => 22500,
                'remaining_budget' => 17250,
                'bid_strategy' => 'manual',
                'selected_gbm' => 0.18,
                'recommended_gbm' => 0.16,
                'actual_gbm' => 0.17,
                'actual_cpc' => 4.2,
                'redirect_url' => 'https://example.invalid/demo-product',
                'metadata' => ['demo' => true, 'network_access' => 'blocked'],
            ]
        );

        $product = $this->products[0];
        $listing = ChannelListing::where('store_id', $this->stores['trendyol']->id)->firstOrFail();
        $sourceUrl = 'https://example.invalid/trendyol/'.strtolower($product->stock_code);
        TrendyolBoosterProduct::updateOrCreate(
            ['user_id' => $user->id, 'source_url_hash' => hash('sha256', $sourceUrl)],
            [
                'mp_product_id' => $product->id,
                'channel_listing_id' => $listing->id,
                'source_url' => $sourceUrl,
                'trendyol_product_id' => self::MARKER.'-BOOSTER-1',
                'title' => $product->product_name,
                'brand' => $product->brand,
                'category_name' => $product->category_name,
                'sale_price' => $product->sale_price,
                'currency' => 'TRY',
                'commission_rate' => 18,
                'cogs' => $product->cogs,
                'packaging_cost' => $product->packaging_cost,
                'cargo_cost' => $product->cargo_cost,
                'return_rate' => 4.5,
                'vat_rate' => 20,
                'cost_vat_rate' => 20,
                'net_profit' => 2370,
                'profit_margin_percent' => 26.36,
                'break_even_price' => 6350,
                'target_price' => 9290,
                'opportunity_score' => 82,
                'decision_status' => 'watch',
                'decision_reasons' => ['Marj sağlıklı', 'Stok kritik eşiğe yaklaşıyor'],
                'simulation_json' => ['demo' => true, 'network_request' => false],
                'watch_price' => false,
                'watch_stock' => false,
                'watch_keyword' => false,
                'tracking_status' => 'paused',
                'analysis_auto_refresh_enabled' => false,
                'last_checked_at' => $this->anchor,
            ]
        );

        return 3;
    }

    private function seedWhatsApp(User $user): int
    {
        $this->requireTables([
            'wa_accounts', 'wa_settings', 'wa_contacts', 'wa_contact_preferences',
            'wa_consent_events', 'wa_templates', 'wa_conversations', 'wa_inbound_messages',
        ]);

        $store = $this->stores['woocommerce'];
        $account = WaAccount::updateOrCreate(
            ['store_id' => $store->id],
            [
                'brand_id' => self::MARKER.'-BRAND',
                'waba_id' => self::MARKER.'-WABA',
                'phone_number_id' => self::MARKER.'-PHONE',
                'display_phone_number' => '+90 555 000 00 01',
                'access_token_encrypted' => 'demo-no-network',
                'status' => 'demo',
                'is_active' => false,
            ]
        );

        WaSetting::set('test_mode', true, $store->id);
        WaSetting::set('automation_enabled', false, $store->id);
        WaSetting::set('network_access', 'blocked', $store->id);

        $phone = '+905550000001';
        $contact = WaContact::updateOrCreate(
            ['store_id' => $store->id, 'phone_hash' => WaContact::hashPhone($phone)],
            [
                'wc_customer_id' => self::MARKER.'-WC-CUSTOMER-1',
                'phone_e164_encrypted' => $phone,
                'first_name' => 'Demo',
                'last_name' => 'Müşteri',
                'status' => 'active',
                'last_seen_at' => $this->anchor->addDays(6),
            ]
        );

        WaContactPreference::updateOrCreate(
            ['contact_id' => $contact->id, 'store_id' => $store->id, 'purpose' => 'transactional'],
            ['status' => 'granted']
        );

        WaConsentEvent::firstOrCreate(
            [
                'contact_id' => $contact->id,
                'store_id' => $store->id,
                'purpose' => 'transactional',
                'action' => 'opt_in',
                'source' => self::VERSION,
            ],
            [
                'consent_text_version' => 'demo-v1',
                'consent_timestamp' => $this->anchor,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'ZOLM Demo Seeder',
            ]
        );

        WaTemplate::updateOrCreate(
            ['wa_account_id' => $account->id, 'name' => 'demo_siparis_bildirimi', 'language' => 'tr'],
            [
                'category' => 'utility',
                'status' => 'approved',
                'components_json' => [['type' => 'BODY', 'text' => 'Demo siparişiniz hazırlandı.']],
                'variable_schema_json' => [],
                'synced_at' => $this->anchor,
            ]
        );

        $conversation = WaConversation::updateOrCreate(
            ['contact_id' => $contact->id, 'store_id' => $store->id],
            [
                'status' => 'open',
                'ai_status' => 'idle',
                'handoff_status' => 'none',
                'priority' => 'normal',
                'last_ai_summary' => 'Müşteri teslimat zamanını soruyor. Tamamen sentetik görüşme.',
                'last_intent' => 'order_status',
                'last_message_at' => $this->anchor->addDays(6),
                'assigned_user_id' => $user->id,
            ]
        );

        $metaMessageId = self::MARKER.'-'.strtoupper($this->tenantKey($user)).'-WA-MSG-1';
        $existingInbound = WaInboundMessage::with('conversation:id,store_id')
            ->where('meta_message_id', $metaMessageId)
            ->first();
        if (! $existingInbound) {
            $legacyInbound = WaInboundMessage::with('conversation:id,store_id')
                ->where('meta_message_id', self::MARKER.'-WA-MSG-1')
                ->first();
            if ($legacyInbound && (int) $legacyInbound->conversation?->store_id === (int) $store->id) {
                $legacyInbound->forceFill(['meta_message_id' => $metaMessageId])->save();
                $existingInbound = $legacyInbound;
            }
        }
        if ($existingInbound && (int) $existingInbound->conversation?->store_id !== (int) $store->id) {
            throw new \RuntimeException("Demo WhatsApp mesaj ID başka mağazaya ait: {$metaMessageId}");
        }

        WaInboundMessage::updateOrCreate(
            ['meta_message_id' => $metaMessageId],
            [
                'conversation_id' => $conversation->id,
                'contact_id' => $contact->id,
                'message_type' => 'text',
                'body' => 'Demo siparişim ne zaman teslim edilir?',
                'payload_json' => ['demo' => true, 'network_request' => false],
                'received_at' => $this->anchor->addDays(6),
            ]
        );

        return 9;
    }

    private function seedCustomerCare(User $user, LegalEntity $legalEntity): int
    {
        $this->requireTables([
            'support_channels', 'support_conversations', 'support_messages',
            'support_organization_memberships', 'support_role_assignments',
            'wa_knowledge_articles', 'support_release_packages',
            'support_release_package_items', 'support_release_events',
            'support_artifact_versions',
        ]);

        SupportOrganizationMembership::updateOrCreate(
            ['legal_entity_id' => $legalEntity->id, 'user_id' => $user->id],
            ['role' => 'admin']
        );

        $count = 1;
        $channels = [];
        foreach ($this->stores as $provider => $store) {
            $channels[$provider] = SupportChannel::updateOrCreate(
                ['store_id' => $store->id, 'key' => $provider],
                [
                    'public_key' => null,
                    'name' => MarketplaceProviderRegistry::get($provider)['label'].' Demo Destek',
                    'status' => 'demo',
                    'is_enabled' => false,
                    'config_json' => [
                        'demo' => true,
                        'demo_version' => self::VERSION,
                        'automation_settings' => [
                            'ai_mode' => 'manual',
                            'auto_reply' => false,
                            'outbound_enabled' => false,
                        ],
                    ],
                    'last_sync_at' => $this->anchor,
                    'last_health_check_at' => $this->anchor,
                    'last_health_status' => 'demo',
                    'last_health_error' => null,
                ]
            );

            SupportRoleAssignment::updateOrCreate(
                ['user_id' => $user->id, 'store_id' => $store->id],
                ['role' => 'owner']
            );
            $count += 2;
        }

        $channel = $channels['trendyol'];
        $conversation = SupportConversation::updateOrCreate(
            [
                'support_channel_id' => $channel->id,
                'external_conversation_id' => self::MARKER.'-SUPPORT-CONV-1',
            ],
            [
                'external_customer_id' => self::MARKER.'-CUSTOMER-1',
                'store_id' => $this->stores['trendyol']->id,
                'source_type' => 'trendyol',
                'status' => 'open',
                'priority' => 'high',
                'assigned_user_id' => $user->id,
                'last_message_at' => $this->anchor->addDays(6),
                'last_inbound_at' => $this->anchor->addDays(6),
                'ai_mode' => 'suggestion_only',
                'ownership_status' => 'human',
                'version' => 1,
                'source_reference_json' => [
                    'demo' => true,
                    'question_id' => MarketplaceQuestion::where('store_id', $this->stores['trendyol']->id)->value('id'),
                ],
            ]
        );

        SupportMessage::updateOrCreate(
            ['conversation_id' => $conversation->id, 'external_message_id' => self::MARKER.'-SUPPORT-MSG-1'],
            [
                'direction' => 'inbound',
                'sender_type' => 'customer',
                'message_type' => 'text',
                'body_encrypted' => 'Demo ürünün kargo süresini öğrenebilir miyim?',
                'body_preview' => 'Demo ürünün kargo süresini öğrenebilir miyim?',
                'payload_json' => ['demo' => true, 'network_request' => false],
                'received_at' => $this->anchor->addDays(6),
                'delivery_status' => 'received',
                'source_reference_type' => 'marketplace_question',
                'source_reference_id' => (string) MarketplaceQuestion::where('store_id', $this->stores['trendyol']->id)->value('id'),
            ]
        );

        $knowledgeContent = [
            'title' => 'ZOLM Demo Kargo ve İade Rehberi',
            'content' => 'Demo ürünler iki iş günü içinde kargoya verilir. Teslimattan sonra 14 gün içinde iade talebi oluşturulabilir.',
        ];
        $knowledgeArticle = WaKnowledgeArticle::updateOrCreate(
            [
                'store_id' => $this->stores['trendyol']->id,
                'slug' => 'zolm-demo-kargo-ve-iade-rehberi',
            ],
            [
                ...$knowledgeContent,
                'category' => 'shipping_and_returns',
                'status' => 'published',
                'version' => 1,
                'effective_from' => $this->anchor,
                'effective_until' => null,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]
        );

        $releasePackage = SupportReleasePackage::updateOrCreate(
            [
                'store_id' => $this->stores['trendyol']->id,
                'title' => self::MARKER.' Customer Care Bilgi Paketi',
            ],
            [
                'status' => 'published',
                'created_by' => $user->id,
                'approved_by' => null,
                'published_at' => $this->anchor,
            ]
        );

        SupportReleasePackageItem::updateOrCreate(
            [
                'package_id' => $releasePackage->id,
                'artifact_type' => 'knowledge_article',
                'artifact_id' => $knowledgeArticle->id,
            ],
            [
                'action' => 'create',
                'diff_json' => ['demo' => true, 'demo_version' => self::VERSION],
                'new_content_json' => $knowledgeContent,
            ]
        );

        $currentArtifactVersion = SupportArtifactVersion::where('store_id', $this->stores['trendyol']->id)
            ->where('artifact_type', 'knowledge_article')
            ->where('artifact_id', $knowledgeArticle->id)
            ->where('is_current', true)
            ->orderByDesc('version_number')
            ->first();
        $useBaselineVersion = ! $currentArtifactVersion || (int) $currentArtifactVersion->version_number <= 1;

        $artifactVersion = SupportArtifactVersion::updateOrCreate(
            [
                'store_id' => $this->stores['trendyol']->id,
                'artifact_type' => 'knowledge_article',
                'artifact_id' => $knowledgeArticle->id,
                'version_number' => 1,
            ],
            [
                'content_json' => $knowledgeContent,
                'is_current' => $useBaselineVersion,
                'release_package_id' => $releasePackage->id,
            ]
        );
        if ($useBaselineVersion) {
            SupportArtifactVersion::where('store_id', $this->stores['trendyol']->id)
                ->where('artifact_type', 'knowledge_article')
                ->where('artifact_id', $knowledgeArticle->id)
                ->whereKeyNot($artifactVersion->id)
                ->update(['is_current' => false]);
        }

        SupportReleaseEvent::updateOrCreate(
            [
                'package_id' => $releasePackage->id,
                'event_type' => 'package_published',
            ],
            [
                'details_json' => [
                    'demo' => true,
                    'demo_version' => self::VERSION,
                    'actor_id' => $user->id,
                    'approval_mode' => 'synthetic_fixture_bypass',
                ],
            ]
        );

        return $count + 7;
    }

    /**
     * @param  array<int, string>  $tables
     */
    private function requireTables(array $tables): void
    {
        $missing = array_values(array_filter($tables, fn (string $table): bool => ! Schema::hasTable($table)));

        if ($missing !== []) {
            throw new \RuntimeException('Eksik migration tabloları: '.implode(', ', $missing));
        }
    }

    private function assertTenantInvariant(User $user, LegalEntity $legalEntity, MarketplaceStore $store): void
    {
        if ((int) $legalEntity->user_id !== (int) $user->id || (int) $store->user_id !== (int) $user->id) {
            throw new \LogicException('Store, firma ve kullanıcı tenant zinciri uyuşmuyor.');
        }
    }

    /** @return array<string, string> */
    private function demoCredentials(string $provider, MarketplaceStore $store): array
    {
        return [
            'demo' => 'true',
            'demo_version' => self::VERSION,
            'provider' => $provider,
            'seller_id' => (string) $store->seller_id,
            'merchant_id' => (string) $store->seller_id,
            'api_key' => 'demo-no-network',
            'api_secret' => 'demo-no-network',
            'client_id' => 'demo-no-network',
            'client_secret' => 'demo-no-network',
            'username' => 'demo-no-network',
            'password' => 'demo-no-network',
            'consumer_key' => 'demo-no-network',
            'consumer_secret' => 'demo-no-network',
            'shop_domain' => 'mockdata1.example.invalid',
            'access_token' => 'demo-no-network',
        ];
    }

    /** @return array<string, mixed> */
    private function markerPayload(string $source): array
    {
        return [
            'demo' => true,
            'demo_version' => self::VERSION,
            'source' => $source,
            'network_request' => false,
        ];
    }

    private function tenantKey(User $user): string
    {
        $email = Str::lower(trim((string) $user->email));
        $localPart = Str::slug(Str::before($email, '@')) ?: 'tenant';

        return Str::limit($localPart, 24, '').'-'.substr(hash('sha256', $email), 0, 10);
    }
}
