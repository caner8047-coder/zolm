<?php

namespace App\Services\Marketplace;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class MarketplaceProviderRegistry
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function providers(): array
    {
        return [
            'trendyol' => [
                'label' => 'Trendyol',
                'group' => 'Pazaryeri',
                'status' => 'ready',
                'default_api_base_url' => config('marketplace.trendyol.base_url'),
                'supports' => [
                    'orders' => true,
                    'products' => true,
                    'finance' => true,
                    'webhooks' => true,
                    'price_push' => true,
                    'stock_push' => true,
                ],
            ],
            'hepsiburada' => [
                'label' => 'Hepsiburada',
                'group' => 'Pazaryeri',
                'status' => 'ready',
                'default_api_base_url' => config('marketplace.hepsiburada.oms_base_url'),
                'supports' => [
                    'orders' => true,
                    'products' => true,
                    'finance' => true,
                    'webhooks' => false,
                    'price_push' => true,
                    'stock_push' => true,
                ],
            ],
            'n11' => [
                'label' => 'N11',
                'group' => 'Pazaryeri',
                'status' => 'ready',
                'default_api_base_url' => config('marketplace.n11.base_url'),
                'supports' => [
                    'orders' => true,
                    'products' => true,
                    'finance' => false,
                    'webhooks' => false,
                    'price_push' => true,
                    'stock_push' => true,
                ],
            ],
            'koctas' => [
                'label' => 'Koçtaş',
                'group' => 'Pazaryeri',
                'status' => 'ready',
                'default_api_base_url' => config('marketplace.koctas.base_url'),
                'supports' => [
                    'orders' => true,
                    'products' => true,
                    'finance' => false,
                    'webhooks' => false,
                    'price_push' => true,
                    'stock_push' => true,
                ],
            ],
            'pazarama' => [
                'label' => 'Pazarama',
                'group' => 'Pazaryeri',
                'status' => 'ready',
                'default_api_base_url' => config('marketplace.pazarama.base_url'),
                'supports' => [
                    'orders' => true,
                    'products' => true,
                    'finance' => false,
                    'webhooks' => false,
                    'price_push' => false,
                    'stock_push' => false,
                ],
            ],
            'amazon' => [
                'label' => 'Amazon',
                'group' => 'Pazaryeri',
                'status' => 'pilot',
                'default_api_base_url' => config('marketplace.amazon.base_url'),
                'supports' => [
                    'orders' => false,
                    'products' => false,
                    'finance' => false,
                    'webhooks' => false,
                    'price_push' => false,
                    'stock_push' => false,
                ],
            ],
            'ciceksepeti' => [
                'label' => 'Çiçeksepeti',
                'group' => 'Pazaryeri',
                'status' => 'ready',
                'default_api_base_url' => config('marketplace.ciceksepeti.base_url'),
                'supports' => [
                    'orders' => true,
                    'products' => true,
                    'finance' => false,
                    'webhooks' => false,
                    'price_push' => false,
                    'stock_push' => false,
                ],
            ],
            'woocommerce' => [
                'label' => 'WooCommerce',
                'group' => 'E-Ticaret',
                'status' => 'ready',
                'default_api_base_url' => config('marketplace.woocommerce.base_url'),
                'supports' => [
                    'orders' => true,
                    'products' => true,
                    'finance' => false,
                    'webhooks' => true,
                    'price_push' => true,
                    'stock_push' => true,
                ],
            ],
            'shopify' => [
                'label' => 'Shopify',
                'group' => 'E-Ticaret',
                'status' => 'pilot',
                'default_api_base_url' => config('marketplace.shopify.base_url'),
                'supports' => [
                    'orders' => true,
                    'products' => true,
                    'finance' => true,
                    'webhooks' => true,
                    'price_push' => true,
                    'stock_push' => true,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function get(string $provider): array
    {
        return static::providers()[static::normalize($provider)] ?? [
            'label' => Str::headline($provider),
            'group' => 'Diğer',
            'status' => 'planned',
            'default_api_base_url' => null,
            'supports' => [
                'orders' => true,
                'products' => true,
                'finance' => false,
                'webhooks' => false,
                'price_push' => false,
                'stock_push' => false,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return Arr::mapWithKeys(static::providers(), fn (array $config, string $key) => [$key => $config['label']]);
    }

    public static function defaultApiBaseUrl(string $provider): ?string
    {
        return static::get($provider)['default_api_base_url'];
    }

    /**
     * @return array<string, string>
     */
    public static function backfillOptions(): array
    {
        return [
            '7_days' => 'Son 7 gün',
            '30_days' => 'Son 30 gün',
            '90_days' => 'Son 90 gün',
            '180_days' => 'Son 180 gün',
            'max_allowed' => 'Maksimum izin verilen',
            'custom' => 'Özel aralık',
        ];
    }

    public static function normalize(string $provider): string
    {
        $normalized = (string) Str::of($provider)->lower()->replace(' ', '_');

        return match ($normalized) {
            'shoppy' => 'shopify',
            default => $normalized,
        };
    }
}
