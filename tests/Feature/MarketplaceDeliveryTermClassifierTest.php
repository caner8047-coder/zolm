<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Marketplace\MarketplaceDeliveryTermClassifier;
use App\Services\MpSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceDeliveryTermClassifierTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_classifies_default_delivery_term_boundaries(): void
    {
        $settings = new MpSettingsService(User::factory()->create()->id);
        $classifier = new MarketplaceDeliveryTermClassifier($settings);

        $this->assertSame('fast', $classifier->classify(0)['key']);
        $this->assertSame('fast', $classifier->classify(1)['key']);
        $this->assertSame('standard', $classifier->classify(2)['key']);
        $this->assertSame('standard', $classifier->classify(3)['key']);
        $this->assertSame('slow', $classifier->classify(4)['key']);
        $this->assertSame('slow', $classifier->classify(7)['key']);
        $this->assertSame('very_slow', $classifier->classify(8)['key']);
    }

    public function test_it_uses_company_specific_delivery_term_thresholds(): void
    {
        $settings = new MpSettingsService(User::factory()->create()->id);
        $settings->set('marketplace_products.delivery_term_thresholds', [
            'fast_max_days' => 2,
            'standard_max_days' => 5,
            'slow_max_days' => 10,
        ]);
        $classifier = new MarketplaceDeliveryTermClassifier($settings);

        $this->assertSame('fast', $classifier->classify(2)['key']);
        $this->assertSame('standard', $classifier->classify(3)['key']);
        $this->assertSame('slow', $classifier->classify(6)['key']);
        $this->assertSame('very_slow', $classifier->classify(11)['key']);
    }
}
