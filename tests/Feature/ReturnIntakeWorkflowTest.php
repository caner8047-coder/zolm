<?php

namespace Tests\Feature;

use App\Jobs\AnalyzeReturnIntakeItemJob;
use App\Livewire\Returns\ReturnIntake;
use App\Models\ChannelClaim;
use App\Models\ChannelClaimItem;
use App\Models\ChannelOrder;
use App\Models\ChannelOrderItem;
use App\Models\ChannelOrderPackage;
use App\Models\LegalEntity;
use App\Models\MarketplaceStore;
use App\Models\ReturnIntakeItem;
use App\Models\User;
use App\Services\Returns\ReturnIntakeProcessingService;
use App\Services\Returns\ReturnVisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ReturnIntakeWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_intake_component_creates_records_and_dispatches_analysis_job(): void
    {
        Storage::fake('public');
        Bus::fake();

        $user = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->actingAs($user);

        Livewire::test(ReturnIntake::class)
            ->set('intakeType', 'damaged')
            ->set('labelImages', [UploadedFile::fake()->image('label.jpg')])
            ->set('damageImages', [UploadedFile::fake()->image('damage.jpg')])
            ->set('manualReference', 'TF-114504168216')
            ->set('operatorBarcode', '8690 0000 0000 1')
            ->set('warehouseNote', 'Kutuda ezik var')
            ->call('saveIntake')
            ->assertSet('messageType', 'success');

        $item = ReturnIntakeItem::query()->with('media')->firstOrFail();

        $this->assertSame('damaged', $item->intake_type);
        $this->assertSame('queued', $item->intake_status);
        $this->assertSame('8690000000001', $item->operator_barcode);
        $this->assertCount(2, $item->media);
        $this->assertNotNull($item->media->first()?->path);
        Storage::disk('public')->assertExists($item->media->first()->path);
        $this->assertNotNull($item->media->first()->original_size_bytes);
        $this->assertNotNull($item->media->first()->thumbnail_path);
        Storage::disk('public')->assertExists($item->media->first()->thumbnail_path);

        Bus::assertDispatched(AnalyzeReturnIntakeItemJob::class, function (AnalyzeReturnIntakeItemJob $job) use ($item) {
            return $job->returnIntakeItemId === $item->id;
        });
    }

    public function test_processing_service_links_item_to_claim_and_order_using_fake_vision_output(): void
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
            'customer_name' => 'Nuri Arslan',
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
            'customer_name' => 'Nuri Arslan',
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

        $item = ReturnIntakeItem::create([
            'batch_id' => \App\Models\ReturnIntakeBatch::create([
                'user_id' => $user->id,
                'source' => 'zolm_mobile',
                'intake_mode' => 'undamaged',
                'status' => 'submitted',
                'captured_at' => now(),
            ])->id,
            'submitted_by_user_id' => $user->id,
            'intake_type' => 'undamaged',
            'intake_status' => 'queued',
            'condition_status' => 'undamaged',
            'decision_status' => 'pending',
            'manual_reference' => 'TF-114504168216',
            'operator_barcode' => '8690000000001',
            'arrived_at' => now(),
        ]);

        $this->app->instance(ReturnVisionService::class, new class extends ReturnVisionService {
            public function analyze(\App\Models\ReturnIntakeItem $item): array
            {
                return [
                    'provider' => 'fake',
                    'model' => 'fake-model',
                    'prompt_version' => 'returns_v1',
                    'confidence' => 0.91,
                    'ocr' => [
                        'tracking_number' => 'TF-114504168216',
                        'order_number' => 'TY-10001',
                        'product_barcode' => null,
                        'customer_name' => 'Nuri',
                        'cargo_provider' => 'Sürat Kargo',
                        'raw_text' => 'TF-114504168216',
                    ],
                    'classification' => [
                        'condition_status' => 'undamaged',
                        'issue_tags' => ['no_damage_visible'],
                        'summary' => 'Etiketten sipariş ve takip numarası okundu.',
                    ],
                    'raw_response_json' => ['fake' => true],
                ];
            }
        });

        $processed = app(ReturnIntakeProcessingService::class)->process($item);

        $this->assertSame($store->id, $processed->store_id);
        $this->assertSame($claim->id, $processed->channel_claim_id);
        $this->assertSame($order->id, $processed->channel_order_id);
        $this->assertSame($package->id, $processed->channel_order_package_id);
        $this->assertSame('ready_for_decision', $processed->intake_status);
        $this->assertSame('matched', $processed->product_verification_status);
        $this->assertSame('Sürat Kargo', $processed->cargo_provider);
        $this->assertSame('approve_marketplace', $processed->suggested_decision);
        $this->assertNotNull($processed->suggestion_summary);
        $this->assertNotNull($processed->latestAnalysis);
    }
}
