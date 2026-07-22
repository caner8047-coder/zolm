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
                    'questions' => true,
                    'claims' => true,
                    'claim_approve' => true,
                    'claim_reject' => true,
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
                    'questions' => true,
                    'claims' => true,
                    'claim_approve' => true,
                    'claim_reject' => true,
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
                    'finance' => true,
                    'webhooks' => false,
                    'price_push' => true,
                    'stock_push' => true,
                    'questions' => true,
                    'claims' => true,
                    'claim_approve' => true,
                    'claim_reject' => true,
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
                    'finance' => 'excel_only',
                    'webhooks' => false,
                    'price_push' => true,
                    'stock_push' => true,
                    'questions' => true,
                    'claims' => true,
                    'claim_approve' => true,
                    'claim_reject' => true,
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
                    'finance' => 'excel_only',
                    'webhooks' => false,
                    'price_push' => false,
                    'stock_push' => false,
                    'questions' => true,
                    'claims' => true,
                    'claim_approve' => false,
                    'claim_reject' => false,
                ],
            ],
            'amazon' => [
                'label' => 'Amazon',
                'group' => 'Pazaryeri',
                'status' => 'access_required',
                'default_api_base_url' => config('marketplace.amazon.base_url'),
                'availability_note' => 'SP-API uygulama kaydı, LWA yetkilendirmesi, AWS rolü ve bölge yapılandırması gerekir.',
                'supports' => [
                    'orders' => false,
                    'products' => false,
                    'finance' => false,
                    'webhooks' => false,
                    'price_push' => false,
                    'stock_push' => false,
                    'questions' => false,
                    'claims' => false,
                    'claim_approve' => false,
                    'claim_reject' => false,
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
                    'finance' => 'excel_only',
                    'webhooks' => false,
                    'price_push' => false,
                    'stock_push' => false,
                    'questions' => true,
                    'claims' => true,
                    'claim_approve' => false,
                    'claim_reject' => false,
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
                    'questions' => true,
                    'claims' => true,
                    'claim_approve' => false,
                    'claim_reject' => false,
                ],
            ],
            'shopify' => [
                'label' => 'Shopify',
                'group' => 'E-Ticaret',
                'status' => 'ready',
                'default_api_base_url' => config('marketplace.shopify.base_url'),
                'supports' => [
                    'orders' => true,
                    'products' => true,
                    'finance' => true,
                    'webhooks' => true,
                    'price_push' => true,
                    'stock_push' => true,
                    'questions' => false,
                    'claims' => true,
                    'claim_approve' => false,
                    'claim_reject' => false,
                ],
            ],
            'ikas' => [
                'label' => 'ikas',
                'group' => 'E-Ticaret',
                'status' => 'ready',
                'default_api_base_url' => config('marketplace.ikas.base_url'),
                'availability_note' => 'Özel uygulama Client ID/Secret ve mağaza kapsam onayıyla kullanılır.',
                'supports' => [
                    'orders' => true,
                    'products' => true,
                    'finance' => true,
                    'webhooks' => true,
                    'price_push' => true,
                    'stock_push' => true,
                    'questions' => false,
                    'claims' => true,
                    'claim_approve' => false,
                    'claim_reject' => false,
                ],
            ],
            'ideasoft' => [
                'label' => 'IdeaSoft',
                'group' => 'E-Ticaret',
                'status' => 'ready',
                'default_api_base_url' => config('marketplace.ideasoft.base_url'),
                'availability_note' => 'Mağaza yöneticisinin OAuth2 authorization_code onayı gerekir.',
                'supports' => [
                    'orders' => true,
                    'products' => true,
                    'finance' => true,
                    'webhooks' => true,
                    'price_push' => true,
                    'stock_push' => true,
                    'questions' => false,
                    'claims' => true,
                    'claim_approve' => false,
                    'claim_reject' => false,
                ],
            ],
            'ticimax' => [
                'label' => 'Ticimax',
                'group' => 'E-Ticaret',
                'status' => 'ready',
                'default_api_base_url' => config('marketplace.ticimax.base_url'),
                'availability_note' => 'Detaylı Web Servis paketi, mağaza URL ve Ticimax Üye Kodu gerekir.',
                'supports' => [
                    'orders' => true,
                    'products' => true,
                    'finance' => true,
                    'webhooks' => false,
                    'price_push' => true,
                    'stock_push' => true,
                    'questions' => false,
                    'claims' => true,
                    'claim_approve' => false,
                    'claim_reject' => false,
                ],
            ],
            'tsoft' => [
                'label' => 'T-Soft',
                'group' => 'E-Ticaret',
                'status' => 'ready',
                'default_api_base_url' => config('marketplace.tsoft.base_url'),
                'availability_note' => 'REST1 / Gelişmiş Web Servis lisansı, mağaza URL ve yetkili servis kullanıcısı gerekir.',
                'supports' => [
                    'orders' => true,
                    'products' => true,
                    'finance' => true,
                    'webhooks' => false,
                    'price_push' => true,
                    'stock_push' => true,
                    'questions' => false,
                    'claims' => true,
                    'claim_approve' => false,
                    'claim_reject' => false,
                ],
            ],
            'magento' => [
                'label' => 'Adobe Commerce / Magento',
                'group' => 'E-Ticaret',
                'status' => 'ready',
                'default_api_base_url' => config('marketplace.magento.base_url'),
                'availability_note' => 'Magento Open Source veya Adobe Commerce PaaS/on-prem Integration Access Token ve mağaza URL gerekir.',
                'supports' => [
                    'orders' => true,
                    'products' => true,
                    'finance' => true,
                    'webhooks' => false,
                    'price_push' => true,
                    'stock_push' => true,
                    'questions' => false,
                    'claims' => true,
                    'claim_approve' => false,
                    'claim_reject' => false,
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
                'questions' => false,
                'claims' => false,
                'claim_approve' => false,
                'claim_reject' => false,
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
            'adobe_commerce', 'magento_2', 'magento_open_source' => 'magento',
            default => $normalized,
        };
    }
}
