<?php

namespace Tests\Feature\Hepsiburada;

use App\Models\MarketplaceStore;
use App\Models\IntegrationConnection;
use App\Services\Marketplace\Connectors\HepsiburadaConnector;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HepsiburadaBatchStatusTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['marketplace.hepsiburada.p0_batch_status_sync_enabled' => true]);
    }
    /**
     * @param  array<string, mixed>  $credentials
     */
    protected function makeStore(array $credentials = []): MarketplaceStore
    {
        $store = new MarketplaceStore([
            'marketplace' => 'hepsiburada',
            'store_name' => 'HB Test',
            'seller_id' => '123456',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
        ]);

        $connection = new IntegrationConnection([
            'provider' => 'hepsiburada',
            'auth_type' => 'merchant_id_service_key',
            'credentials_encrypted' => array_merge([
                'api_key' => 'service-key',
                'extra_user' => 'zem_dev',
            ], $credentials),
            'api_base_url' => 'https://oms-external.hepsiburada.com/',
            'status' => 'configured',
        ]);

        $store->setRelation('connection', $connection);

        return $store;
    }

    public function test_it_pulls_batch_status_successfully(): void
    {
        Http::fake([
            'https://listing-external.hepsiburada.com/listings/merchantid/123456/price-uploads/id/batch-abc-123' => Http::response([
                'status' => 'Completed',
                'successCount' => 12,
                'failureCount' => 2,
                'items' => [
                    ['sku' => 'SKU-OK', 'status' => 'Success'],
                    ['sku' => 'SKU-ERR', 'status' => 'Failure', 'reason' => 'Invalid price format'],
                ],
            ], 200),
        ]);

        $store = $this->makeStore();
        $connector = app(HepsiburadaConnector::class);

        $result = $connector->pullBatchStatus($store, 'batch-abc-123', 'price-uploads');

        $this->assertSame('batch-abc-123', $result['batch_request_id']);
        $this->assertSame('price-uploads', $result['operation']);
        $this->assertSame('Completed', $result['status']);
        $this->assertSame(12, $result['success_count']);
        $this->assertSame(2, $result['failure_count']);
        $this->assertCount(2, $result['items']);

        // Verify request contract (URL, method, basic auth and user-agent presence)
        Http::assertSent(function ($request) {
            return $request->method() === 'GET'
                && str_contains($request->url(), 'listings/merchantid/123456/price-uploads/id/batch-abc-123')
                && $request->hasHeader('Authorization')
                && $request->hasHeader('User-Agent');
        });
    }

    public function test_it_throws_exception_for_invalid_operation(): void
    {
        $store = $this->makeStore();
        $connector = app(HepsiburadaConnector::class);

        $this->expectException(\InvalidArgumentException::class);
        $connector->pullBatchStatus($store, 'batch-abc-123', 'invalid-operation');
    }
}
