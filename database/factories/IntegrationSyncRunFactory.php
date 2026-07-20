<?php

namespace Database\Factories;

use App\Models\IntegrationSyncRun;
use App\Models\MarketplaceStore;
use Illuminate\Database\Eloquent\Factories\Factory;

class IntegrationSyncRunFactory extends Factory
{
    protected $model = IntegrationSyncRun::class;

    public function definition(): array
    {
        return [
            'store_id' => MarketplaceStore::factory(),
            'sync_type' => $this->faker->randomElement(['orders', 'products', 'finance', 'buybox', 'cargo_invoice', 'reference']),
            'trigger_type' => 'schedule',
            'status' => 'completed',
            'cursor_before' => null,
            'cursor_after' => null,
            'started_at' => now()->subMinutes(10),
            'finished_at' => now()->subMinutes(5),
            'duration_ms' => $this->faker->numberBetween(500, 30000),
            'items_received' => $this->faker->numberBetween(0, 500),
            'items_created' => $this->faker->numberBetween(0, 100),
            'items_updated' => $this->faker->numberBetween(0, 200),
            'items_skipped' => 0,
            'rate_limit_hits' => 0,
            'error_count' => 0,
            'notes_json' => [],
        ];
    }

    public function failed(): static
    {
        return $this->state([
            'status' => 'failed',
            'error_count' => 1,
            'notes_json' => ['error' => 'API bağlantı hatası'],
        ]);
    }

    public function queued(): static
    {
        return $this->state([
            'status' => 'queued',
            'started_at' => null,
            'finished_at' => null,
        ]);
    }
}
