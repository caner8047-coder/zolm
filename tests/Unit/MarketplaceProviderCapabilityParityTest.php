<?php

namespace Tests\Unit;

use App\Services\Marketplace\MarketplaceConnectorManager;
use App\Services\Marketplace\MarketplaceProviderRegistry;
use Tests\TestCase;

class MarketplaceProviderCapabilityParityTest extends TestCase
{
    public function test_registry_boolean_capabilities_match_real_connectors(): void
    {
        $manager = app(MarketplaceConnectorManager::class);
        $mismatches = [];

        foreach (MarketplaceProviderRegistry::providers() as $provider => $definition) {
            $connectorCapabilities = $manager->resolve($provider)->capabilities();

            foreach ($definition['supports'] ?? [] as $capability => $declaredSupport) {
                // excel_only gibi değerler API bağlayıcısı dışında yürüyen destek kipleridir.
                if (! is_bool($declaredSupport)) {
                    continue;
                }

                $actualSupport = (bool) ($connectorCapabilities[$capability] ?? false);

                if ($declaredSupport !== $actualSupport) {
                    $mismatches[] = sprintf(
                        '%s.%s registry=%s connector=%s',
                        $provider,
                        $capability,
                        $declaredSupport ? 'true' : 'false',
                        $actualSupport ? 'true' : 'false',
                    );
                }
            }
        }

        $this->assertSame([], $mismatches, implode(PHP_EOL, $mismatches));
    }

    public function test_access_required_providers_do_not_advertise_live_api_capabilities(): void
    {
        foreach (MarketplaceProviderRegistry::providers() as $provider => $definition) {
            if (($definition['status'] ?? null) !== 'access_required') {
                continue;
            }

            $advertisedCapabilities = collect($definition['supports'] ?? [])
                ->filter(fn ($supported) => $supported === true)
                ->keys()
                ->all();

            $this->assertSame([], $advertisedCapabilities, $provider.' erişim beklerken canlı kabiliyet ilan ediyor.');
            $this->assertNotEmpty($definition['availability_note'] ?? null, $provider.' için erişim açıklaması eksik.');
        }
    }

    public function test_installable_commerce_connectors_are_published_as_ready(): void
    {
        foreach (['shopify', 'ikas', 'ideasoft', 'ticimax', 'tsoft', 'magento'] as $provider) {
            $this->assertSame('ready', MarketplaceProviderRegistry::get($provider)['status'], $provider.' hazır yayınlanmadı.');
        }
    }
}
