<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\IntegrationSyncRun;
use App\Models\LegalEntity;
use App\Models\MarketplaceQuestion;
use App\Models\MarketplaceStore;
use App\Models\MpProduct;
use App\Models\User;
use App\Services\NotificationCenterService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class NotificationCenterServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'mysql');
        config()->set('database.connections.mysql.host', 'mysql');
        config()->set('database.connections.mysql.port', '3306');
        config()->set('database.connections.mysql.database', $this->mysqlTestDatabaseName());
        config()->set('database.connections.mysql.username', 'sail');
        config()->set('database.connections.mysql.password', 'password');
        DB::purge('mysql');
        DB::reconnect('mysql');
        DB::setDefaultConnection('mysql');

        config()->set('marketplace.features.notifications_enabled', true);
    }

    public function test_integration_failure_notifications_are_debounced_within_cooldown_window(): void
    {
        [, $store] = $this->createStoreGraph('trendyol');
        $service = app(NotificationCenterService::class);
        $baseTime = Carbon::parse('2026-04-26 12:00:00');

        try {
            Carbon::setTestNow($baseTime);

            $firstRun = $this->createFailedRun($store, 'questions');
            $service->notifyIntegrationFailure($firstRun, new RuntimeException('HTTP request returned status code 404'));

            $this->assertSame(1, $this->notificationsQuery($store)->count());

            $firstNotification = $this->notificationsQuery($store)->latest('id')->firstOrFail();
            $this->assertSame('Soru senkronu başarısız', $firstNotification->title);
            $this->assertSame('HTTP 404', $firstNotification->body);

            $secondRun = $this->createFailedRun($store, 'questions');
            $service->notifyIntegrationFailure($secondRun, new RuntimeException('HTTP request returned status code 404'));

            $this->assertSame(1, $this->notificationsQuery($store)->count());

            $thirdRun = $this->createFailedRun($store, 'questions');
            $service->notifyIntegrationFailure($thirdRun, new RuntimeException('HTTP request returned status code 401'));

            $this->assertSame(2, $this->notificationsQuery($store)->count());
            $this->assertEqualsCanonicalizing(
                ['HTTP 404', 'HTTP 401'],
                $this->notificationsQuery($store)->pluck('body')->all(),
            );

            Carbon::setTestNow($baseTime->copy()->addMinutes(6));

            $fourthRun = $this->createFailedRun($store, 'questions');
            $service->notifyIntegrationFailure($fourthRun, new RuntimeException('HTTP request returned status code 404'));

            $this->assertSame(3, $this->notificationsQuery($store)->count());
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_smoke_test_failures_do_not_create_user_notifications(): void
    {
        [, $store] = $this->createStoreGraph('trendyol');
        $run = IntegrationSyncRun::query()->create([
            'store_id' => $store->id,
            'sync_type' => 'questions',
            'trigger_type' => 'smoke_test',
            'status' => 'failed',
            'notes_json' => [
                'smoke_test' => true,
            ],
        ]);

        app(NotificationCenterService::class)->notifyIntegrationFailure(
            $run,
            new RuntimeException('HTTP request returned status code 404'),
        );

        $this->assertSame(0, $this->notificationsQuery($store)->count());
    }

    public function test_question_notifications_link_to_the_selected_question(): void
    {
        [, $store] = $this->createStoreGraph('trendyol');
        $question = MarketplaceQuestion::query()->create([
            'store_id' => $store->id,
            'external_question_id' => 'TY-Q-9001',
            'status' => 'open',
            'product_name' => 'Liva Puf',
            'product_sku' => 'LIVA-1',
            'question_text' => 'Bu ürünün ölçüsü nedir?',
            'asked_at' => now(),
        ]);

        $notification = app(NotificationCenterService::class)->notifyQuestionReceived($question);

        $this->assertNotNull($notification);
        $this->assertSame('question_received', $notification->type);
        $this->assertStringContainsString('question='.$question->id, (string) $notification->action_url);
        $this->assertStringContainsString('storeFilter='.$store->id, (string) $notification->action_url);
    }

    public function test_muted_notification_types_are_not_created_or_counted(): void
    {
        [$user] = $this->createStoreGraph('trendyol');
        $service = app(NotificationCenterService::class);
        $service->setMutedTypes($user->id, ['booster_price_drop']);

        $muted = $service->createForUser($user->id, [
            'type' => 'booster_price_drop',
            'title' => 'Fiyat düştü',
            'body' => 'Sessize alınmış Booster bildirimi',
            'event_key' => 'booster-price-muted',
        ]);
        $visible = $service->createForUser($user->id, [
            'type' => 'booster_stock_sales',
            'title' => 'Stok eridi',
            'body' => 'Görünür Booster bildirimi',
            'event_key' => 'booster-stock-visible',
        ]);

        $this->assertNull($muted);
        $this->assertNotNull($visible);
        $this->assertSame(1, $service->unreadCountForUser($user->id));
        $this->assertCount(1, $service->feedForUser($user->id));
        $this->assertSame('booster_stock_sales', $service->feedForUser($user->id)[0]['type']);
    }

    public function test_existing_muted_notifications_are_hidden_from_feed(): void
    {
        [$user] = $this->createStoreGraph('trendyol');
        $service = app(NotificationCenterService::class);
        AppNotification::query()->create([
            'user_id' => $user->id,
            'type' => 'booster_price_drop',
            'severity' => 'info',
            'title' => 'Fiyat düştü',
            'body' => 'Eski Booster bildirimi',
            'event_key' => 'booster-price-existing',
            'triggered_at' => now(),
        ]);

        $this->assertSame(1, $service->unreadCountForUser($user->id));

        $service->setMutedTypes($user->id, ['booster_price_drop']);

        $this->assertSame(0, $service->unreadCountForUser($user->id));
        $this->assertSame([], $service->feedForUser($user->id));
    }

    public function test_stock_notification_payload_shows_marketplace_and_links_to_product_edit(): void
    {
        [$user, $store] = $this->createStoreGraph('trendyol');
        $product = MpProduct::query()->create([
            'user_id' => $user->id,
            'barcode' => 'STOCK-900',
            'stock_code' => 'STOCK-900',
            'product_name' => 'Bildirim Test Ürünü',
            'stock_quantity' => 0,
            'cogs' => 0,
            'packaging_cost' => 0,
            'cargo_cost' => 0,
            'vat_rate' => 10,
            'market_price' => 0,
            'sale_price' => 0,
            'commission_rate' => 0,
            'desi' => 0,
            'pieces' => 1,
            'status' => 'active',
        ]);
        $notification = AppNotification::query()->create([
            'user_id' => $user->id,
            'store_id' => $store->id,
            'type' => 'stock_out',
            'severity' => 'critical',
            'title' => 'Stok bitti',
            'body' => 'Bildirim Test Ürünü · STOCK-900 · stok 0',
            'data_json' => [
                'product_id' => $product->id,
                'stock_code' => 'STOCK-900',
            ],
            'action_url' => route('mp.products', ['filterStockLevel' => 'out_of_stock']),
            'triggered_at' => now(),
        ]);

        $payload = app(NotificationCenterService::class)->toPayload($notification);

        $this->assertSame('ZEM NOTIFY TRENDYOL · Trendyol · Stok bitti', $payload['context_label']);
        $this->assertStringContainsString('edit='.$product->id, $payload['action_url']);
        $this->assertStringContainsString('tab=logistics', $payload['action_url']);
    }

    public function test_it_prunes_read_and_unread_notifications_by_retention_windows(): void
    {
        [, $store] = $this->createStoreGraph('trendyol');
        $baseTime = Carbon::parse('2026-04-26 12:00:00');

        config()->set('marketplace.notifications.read_retention_hours', 24);
        config()->set('marketplace.notifications.unread_retention_days', 7);

        try {
            Carbon::setTestNow($baseTime);

            $expiredRead = $this->createNotification($store, 'Okunmuş eski')
                ->forceFill([
                    'read_at' => $baseTime->copy()->subHours(25),
                    'created_at' => $baseTime->copy()->subDays(3),
                    'updated_at' => $baseTime->copy()->subHours(25),
                ]);
            $expiredRead->save();

            $freshRead = $this->createNotification($store, 'Okunmuş yeni')
                ->forceFill([
                    'read_at' => $baseTime->copy()->subHours(23),
                    'created_at' => $baseTime->copy()->subDays(3),
                    'updated_at' => $baseTime->copy()->subHours(23),
                ]);
            $freshRead->save();

            $expiredUnread = $this->createNotification($store, 'Okunmamış eski')
                ->forceFill([
                    'read_at' => null,
                    'created_at' => $baseTime->copy()->subDays(8),
                    'updated_at' => $baseTime->copy()->subDays(8),
                ]);
            $expiredUnread->save();

            $freshUnread = $this->createNotification($store, 'Okunmamış yeni')
                ->forceFill([
                    'read_at' => null,
                    'created_at' => $baseTime->copy()->subDays(6),
                    'updated_at' => $baseTime->copy()->subDays(6),
                ]);
            $freshUnread->save();

            $result = app(NotificationCenterService::class)->pruneExpiredNotifications();

            $this->assertSame([
                'read_deleted' => 1,
                'unread_deleted' => 1,
                'total_deleted' => 2,
            ], $result);
            $this->assertDatabaseMissing('app_notifications', ['id' => $expiredRead->id]);
            $this->assertDatabaseHas('app_notifications', ['id' => $freshRead->id]);
            $this->assertDatabaseMissing('app_notifications', ['id' => $expiredUnread->id]);
            $this->assertDatabaseHas('app_notifications', ['id' => $freshUnread->id]);
        } finally {
            Carbon::setTestNow();
        }
    }

    protected function notificationsQuery(MarketplaceStore $store)
    {
        return AppNotification::query()
            ->where('user_id', $store->user_id)
            ->where('store_id', $store->id);
    }

    protected function createFailedRun(MarketplaceStore $store, string $syncType): IntegrationSyncRun
    {
        return IntegrationSyncRun::query()->create([
            'store_id' => $store->id,
            'sync_type' => $syncType,
            'trigger_type' => 'manual',
            'status' => 'failed',
        ]);
    }

    /**
     * @return array{0: User, 1: MarketplaceStore}
     */
    protected function createStoreGraph(string $marketplace): array
    {
        $suffix = (string) random_int(100000, 999999);
        $user = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
        $legalEntity = LegalEntity::query()->create([
            'user_id' => $user->id,
            'name' => 'Zem Notify Ltd.',
            'tax_number' => '8'.$suffix,
            'company_type' => 'limited',
            'currency' => 'TRY',
            'is_active' => true,
        ]);
        $store = MarketplaceStore::query()->create([
            'user_id' => $user->id,
            'legal_entity_id' => $legalEntity->id,
            'marketplace' => $marketplace,
            'store_name' => 'ZEM NOTIFY '.strtoupper($marketplace),
            'store_code' => 'NF-'.$suffix,
            'seller_id' => 'SELLER-NF-'.$suffix,
            'status' => 'active',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        return [$user, $store];
    }

    protected function createNotification(MarketplaceStore $store, string $title): AppNotification
    {
        return AppNotification::query()->create([
            'user_id' => $store->user_id,
            'store_id' => $store->id,
            'type' => 'new_order',
            'severity' => 'info',
            'title' => $title,
            'body' => $title,
            'triggered_at' => now(),
        ]);
    }
}
