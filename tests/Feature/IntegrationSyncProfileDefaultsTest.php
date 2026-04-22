<?php

namespace Tests\Feature;

use App\Models\IntegrationSyncProfile;
use Tests\TestCase;

class IntegrationSyncProfileDefaultsTest extends TestCase
{
    public function test_it_uses_low_impact_defaults_for_trendyol(): void
    {
        $defaults = IntegrationSyncProfile::defaultsForMarketplace('trendyol');

        $this->assertSame(15, $defaults['orders_poll_minutes']);
        $this->assertSame(60, $defaults['finance_poll_minutes']);
        $this->assertSame(720, $defaults['products_poll_minutes']);
        $this->assertFalse($defaults['price_push_enabled']);
        $this->assertFalse($defaults['stock_push_enabled']);
        $this->assertTrue($defaults['webhook_enabled']);
        $this->assertSame(1, $defaults['max_parallel_jobs']);
        $this->assertSame(5, $defaults['request_jitter_seconds']);
        $this->assertSame('7_days', $defaults['backfill_mode']);
    }

    public function test_it_uses_low_impact_defaults_for_hepsiburada(): void
    {
        $defaults = IntegrationSyncProfile::defaultsForMarketplace('hepsiburada');

        $this->assertSame(20, $defaults['orders_poll_minutes']);
        $this->assertSame(120, $defaults['finance_poll_minutes']);
        $this->assertSame(720, $defaults['products_poll_minutes']);
        $this->assertFalse($defaults['webhook_enabled']);
        $this->assertFalse($defaults['price_push_enabled']);
        $this->assertFalse($defaults['stock_push_enabled']);
        $this->assertSame(1, $defaults['max_parallel_jobs']);
        $this->assertSame(10, $defaults['request_jitter_seconds']);
        $this->assertSame('7_days', $defaults['backfill_mode']);
    }

    public function test_it_uses_conservative_defaults_for_woocommerce(): void
    {
        $defaults = IntegrationSyncProfile::defaultsForMarketplace('woocommerce');

        $this->assertSame(30, $defaults['orders_poll_minutes']);
        $this->assertSame(720, $defaults['products_poll_minutes']);
        $this->assertFalse($defaults['finance_enabled']);
        $this->assertFalse($defaults['price_push_enabled']);
        $this->assertFalse($defaults['stock_push_enabled']);
        $this->assertSame(1, $defaults['max_parallel_jobs']);
        $this->assertSame(15, $defaults['request_jitter_seconds']);
        $this->assertSame('7_days', $defaults['backfill_mode']);
    }

    public function test_it_uses_conservative_defaults_for_n11(): void
    {
        $defaults = IntegrationSyncProfile::defaultsForMarketplace('n11');

        $this->assertSame(20, $defaults['orders_poll_minutes']);
        $this->assertSame(360, $defaults['products_poll_minutes']);
        $this->assertFalse($defaults['finance_enabled']);
        $this->assertFalse($defaults['webhook_enabled']);
        $this->assertFalse($defaults['price_push_enabled']);
        $this->assertFalse($defaults['stock_push_enabled']);
        $this->assertSame(1, $defaults['max_parallel_jobs']);
        $this->assertSame(10, $defaults['request_jitter_seconds']);
        $this->assertSame('7_days', $defaults['backfill_mode']);
    }

    public function test_it_uses_conservative_defaults_for_koctas(): void
    {
        $defaults = IntegrationSyncProfile::defaultsForMarketplace('koctas');

        $this->assertSame(20, $defaults['orders_poll_minutes']);
        $this->assertSame(720, $defaults['products_poll_minutes']);
        $this->assertFalse($defaults['finance_enabled']);
        $this->assertFalse($defaults['webhook_enabled']);
        $this->assertFalse($defaults['price_push_enabled']);
        $this->assertFalse($defaults['stock_push_enabled']);
        $this->assertSame(1, $defaults['max_parallel_jobs']);
        $this->assertSame(15, $defaults['request_jitter_seconds']);
        $this->assertSame('7_days', $defaults['backfill_mode']);
    }

    public function test_it_uses_conservative_defaults_for_pazarama(): void
    {
        $defaults = IntegrationSyncProfile::defaultsForMarketplace('pazarama');

        $this->assertSame(20, $defaults['orders_poll_minutes']);
        $this->assertSame(720, $defaults['products_poll_minutes']);
        $this->assertFalse($defaults['finance_enabled']);
        $this->assertFalse($defaults['webhook_enabled']);
        $this->assertFalse($defaults['price_push_enabled']);
        $this->assertFalse($defaults['stock_push_enabled']);
        $this->assertSame(1, $defaults['max_parallel_jobs']);
        $this->assertSame(10, $defaults['request_jitter_seconds']);
        $this->assertSame('7_days', $defaults['backfill_mode']);
    }

    public function test_it_uses_conservative_defaults_for_ciceksepeti(): void
    {
        $defaults = IntegrationSyncProfile::defaultsForMarketplace('ciceksepeti');

        $this->assertSame(20, $defaults['orders_poll_minutes']);
        $this->assertSame(720, $defaults['products_poll_minutes']);
        $this->assertFalse($defaults['finance_enabled']);
        $this->assertFalse($defaults['webhook_enabled']);
        $this->assertFalse($defaults['price_push_enabled']);
        $this->assertFalse($defaults['stock_push_enabled']);
        $this->assertSame(1, $defaults['max_parallel_jobs']);
        $this->assertSame(10, $defaults['request_jitter_seconds']);
        $this->assertSame('7_days', $defaults['backfill_mode']);
    }
}
