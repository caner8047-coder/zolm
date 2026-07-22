<?php

namespace Tests\Unit;

use App\Livewire\TrendyolBooster;
use ReflectionClass;
use Tests\TestCase;

class TrendyolBoosterComparisonHandoffTest extends TestCase
{
    public function test_comparison_handoff_query_state_and_auto_start_are_wired(): void
    {
        $defaults = (new ReflectionClass(TrendyolBooster::class))->getDefaultProperties();
        $queryString = $defaults['queryString'];

        $this->assertSame('compare', $queryString['comparisonUrls']['as']);
        $this->assertSame('compare_now', $queryString['comparisonAutoStart']['as']);
        $this->assertFalse($defaults['comparisonAutoStart']);

        $view = file_get_contents(resource_path('views/livewire/partials/trendyol-booster-comparison.blade.php'));

        $this->assertIsString($view);
        $this->assertStringContainsString('maybeAutoRun()', $view);
        $this->assertStringContainsString("wire.set('comparisonAutoStart', false)", $view);
    }

    public function test_listing_sell_decision_handoff_is_wired(): void
    {
        $defaults = (new ReflectionClass(TrendyolBooster::class))->getDefaultProperties();
        $queryString = $defaults['queryString'];

        $this->assertSame('decision_product', $queryString['decisionTrackedProductId']['as']);
        $this->assertNull($defaults['decisionTrackedProductId']);

        $contentScript = file_get_contents(base_path('browser-extensions/trendyol-booster-companion/content.js'));
        $backgroundScript = file_get_contents(base_path('browser-extensions/trendyol-booster-companion/background.js'));

        $this->assertIsString($contentScript);
        $this->assertIsString($backgroundScript);
        $this->assertStringContainsString('Karar merkezine al', $contentScript);
        $this->assertStringContainsString('ZOLM_BOOSTER_DECIDE_LISTING_PRODUCT', $contentScript);
        $this->assertStringContainsString('renderListingDecisionSummary', $contentScript);
        $this->assertStringContainsString('startListingDecisionQueue', $contentScript);
        $this->assertStringContainsString('Kuyruğu temizle', $contentScript);
        $this->assertStringContainsString("url.searchParams.set('decision_product'", $backgroundScript);
        $this->assertStringContainsString('processDecisionQueue', $backgroundScript);
        $this->assertStringContainsString('ZOLM_BOOSTER_CLEAR_DECISION_QUEUE', $backgroundScript);
    }
}
