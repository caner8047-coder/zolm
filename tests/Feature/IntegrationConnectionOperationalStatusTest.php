<?php

namespace Tests\Feature;

use App\Models\IntegrationConnection;
use App\Models\MarketplaceStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntegrationConnectionOperationalStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_operational_scope_includes_all_live_connection_states(): void
    {
        foreach (IntegrationConnection::OPERATIONAL_STATUSES as $index => $status) {
            IntegrationConnection::factory()->create([
                'store_id' => MarketplaceStore::factory()->create(['seller_id' => 'operational-'.$index])->id,
                'status' => $status,
            ]);
        }

        foreach (['draft', 'inactive', IntegrationConnection::STATUS_DEMO] as $index => $status) {
            IntegrationConnection::factory()->create([
                'store_id' => MarketplaceStore::factory()->create(['seller_id' => 'excluded-'.$index])->id,
                'status' => $status,
            ]);
        }

        $this->assertCount(
            count(IntegrationConnection::OPERATIONAL_STATUSES),
            IntegrationConnection::query()->operational()->get()
        );

        $this->assertSame(
            IntegrationConnection::OPERATIONAL_STATUSES,
            IntegrationConnection::query()->operational()->orderBy('id')->pluck('status')->all()
        );
    }

    public function test_connection_reports_whether_it_is_operational(): void
    {
        foreach (IntegrationConnection::OPERATIONAL_STATUSES as $status) {
            $this->assertTrue((new IntegrationConnection(['status' => $status]))->isOperational());
        }

        $this->assertFalse((new IntegrationConnection(['status' => 'inactive']))->isOperational());
        $this->assertFalse((new IntegrationConnection(['status' => IntegrationConnection::STATUS_DEMO]))->isOperational());
    }
}
