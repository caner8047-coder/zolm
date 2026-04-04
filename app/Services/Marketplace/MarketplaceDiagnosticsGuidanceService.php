<?php

namespace App\Services\Marketplace;

use App\Models\IntegrationSyncProfile;
use App\Models\MarketplaceStore;

class MarketplaceDiagnosticsGuidanceService
{
    public function __construct(
        protected MarketplaceDiagnosticsReportService $diagnosticsReport,
        protected LegacyFinancialProjectionBacklogService $legacyFinancialBacklog,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{totals: array<string, int>, items: array<int, array<string, mixed>>}
     */
    public function guidanceForUser(int $userId, array $filters = []): array
    {
        $summary = $this->diagnosticsReport->summaryForUser($userId, $filters);
        $baseGuidance = $this->buildGuidance($summary);
        $trendyolSafeProfileGuidance = $this->trendyolSafeProfileGuidanceForUser($userId);
        $hepsiburadaSafeProfileGuidance = $this->hepsiburadaSafeProfileGuidanceForUser($userId);
        $wooGuidance = $this->wooSafeProfileGuidanceForUser($userId);
        $shopifySafeProfileGuidance = $this->shopifySafeProfileGuidanceForUser($userId);
        $shopifyGuidance = $this->shopifyWebhookTopicGuidanceForUser($userId);
        $legacyFinancialGuidance = $this->legacyFinancialProjectionGuidanceForUser($userId);
        $items = array_merge(
            $baseGuidance['items'],
            $trendyolSafeProfileGuidance,
            $hepsiburadaSafeProfileGuidance,
            $wooGuidance,
            $shopifySafeProfileGuidance,
            $shopifyGuidance,
            $legacyFinancialGuidance
        );

        $this->sortGuidanceItems($items);

        return [
            'totals' => [
                'items' => count($items),
                'critical' => count(array_filter($items, fn (array $item) => $item['severity'] === 'critical')),
                'warning' => count(array_filter($items, fn (array $item) => $item['severity'] === 'warning')),
                'info' => count(array_filter($items, fn (array $item) => $item['severity'] === 'info')),
            ],
            'items' => array_values($items),
        ];
    }

    /**
     * @param  array{totals: array<string, int>, rows: array<int, array<string, mixed>>}  $summary
     * @return array{totals: array<string, int>, items: array<int, array<string, mixed>>}
     */
    public function buildGuidance(array $summary): array
    {
        $items = [];

        foreach ($summary['rows'] as $row) {
            $matchRisk = (int) ($row['missing_stock_code_count'] ?? 0) + (int) ($row['missing_barcode_count'] ?? 0);
            $identityRisk = (int) ($row['missing_order_number_count'] ?? 0)
                + (int) ($row['missing_package_id_count'] ?? 0)
                + (int) ($row['missing_item_line_id_count'] ?? 0)
                + (int) ($row['missing_line_id_count'] ?? 0);
            $financeRisk = (int) ($row['missing_amount_count'] ?? 0) + (int) ($row['missing_settlement_date_count'] ?? 0);
            $listingRisk = (int) ($row['missing_listing_id_count'] ?? 0)
                + (int) ($row['missing_sale_price_count'] ?? 0)
                + (int) ($row['missing_stock_quantity_count'] ?? 0);

            if ($matchRisk > 0) {
                $items[] = $this->makeGuidanceItem(
                    $row,
                    'product_matching',
                    $matchRisk >= 10 ? 'critical' : 'warning',
                    $matchRisk,
                    'Ürün eşleşme alanları eksik',
                    'Stok kodu ve barkod mapping alanlarını gözden geçir; gerekiyorsa Eşleştirme Merkezi üzerinden manuel bağlantı kur.',
                    'mp.matching',
                    'Eksik stok kodu veya barkod, kâr hesabı ve master ürün eşleştirmesini bozar.'
                );
            }

            if ($identityRisk > 0) {
                $items[] = $this->makeGuidanceItem(
                    $row,
                    'order_identity',
                    $identityRisk >= 10 ? 'critical' : 'warning',
                    $identityRisk,
                    'Sipariş kimlik alanları eksik',
                    'Connector normalize alanlarını kontrol et; order/package/line id mapping alanlarını sertleştir ve tekrar smoke test çalıştır.',
                    'mp.integrations',
                    'Eksik sipariş veya paket kimliği, dedupe ve iade/mutabakat zincirini zayıflatır.'
                );
            }

            if ($financeRisk > 0) {
                $items[] = $this->makeGuidanceItem(
                    $row,
                    'finance_mapping',
                    $financeRisk >= 10 ? 'critical' : 'warning',
                    $financeRisk,
                    'Finans alanları eksik',
                    'Tutar ve ödeme tarihi mapping alanlarını düzelt; ardından Finans çek ve mutabakat ekranından farkı yeniden kontrol et.',
                    'mp.finance',
                    'Eksik tutar veya settlement bilgisi kesin kâr ve mutabakat kalitesini düşürür.'
                );
            }

            if ($listingRisk > 0) {
                $items[] = $this->makeGuidanceItem(
                    $row,
                    'listing_completeness',
                    $listingRisk >= 10 ? 'warning' : 'info',
                    $listingRisk,
                    'Listing tamlık alanları eksik',
                    'Ürün/listing alanlarını kontrol et; listing id, satış fiyatı ve stok miktarı normalize alanlarını sıkılaştır.',
                    'mp.products',
                    'Eksik listing alanları fiyat/stok push ve ürün paneli doğruluğunu zayıflatır.'
                );
            }
        }

        $this->sortGuidanceItems($items);

        return [
            'totals' => [
                'items' => count($items),
                'critical' => count(array_filter($items, fn (array $item) => $item['severity'] === 'critical')),
                'warning' => count(array_filter($items, fn (array $item) => $item['severity'] === 'warning')),
                'info' => count(array_filter($items, fn (array $item) => $item['severity'] === 'info')),
            ],
            'items' => array_values($items),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function wooSafeProfileGuidanceForUser(int $userId): array
    {
        $defaults = IntegrationSyncProfile::defaultsForMarketplace('woocommerce');

        return MarketplaceStore::query()
            ->with('syncProfile')
            ->where('user_id', $userId)
            ->where('marketplace', 'woocommerce')
            ->get()
            ->map(function (MarketplaceStore $store) use ($defaults): array {
                $safeProfileItem = $this->buildSafeProfileGuidanceItem(
                    $store,
                    'woocommerce_safe_profile',
                    'WooCommerce güvenli profilinden sapma var',
                    'Entegrasyonlar ekranında güvenli WooCommerce profilini uygula; gerekiyorsa gözden geçirip senkron profilini kaydet.',
                    'Aşırı polling, açık push ayarları veya yüksek paralellik WooCommerce mağazasının sunucusuna gereksiz yük bindirebilir.',
                    $defaults
                );

                $profile = $store->syncProfile;
                $topicAudit = IntegrationSyncProfile::auditWooWebhookTopics(
                    data_get($profile?->extra_settings ?? [], 'webhook_topics', data_get($defaults, 'extra_settings.webhook_topics', []))
                );

                $topicItem = null;

                if ($profile && $profile->webhook_enabled && !$topicAudit['matches_recommended']) {
                    $impactCount = max(1, count($topicAudit['missing']) + count($topicAudit['extra']));
                    $severity = $topicAudit['is_empty'] || count($topicAudit['missing']) >= 3 || $topicAudit['extra'] !== []
                        ? 'critical'
                        : 'warning';

                    $reason = match (true) {
                        $topicAudit['is_empty'] => 'Webhook açık ama topic seti boş bırakılmış.',
                        $topicAudit['extra'] !== [] => 'Önerilen set dışında topic seçildiği için gereksiz event yükü oluşabilir.',
                        default => 'Önerilen topiclerin bir kısmı kapalı olduğu için webhook-first akış eksik çalışır.',
                    };

                    $topicItem = [
                        'store_id' => $store->id,
                        'store_name' => $store->store_name,
                        'marketplace' => $store->marketplace,
                        'sync_type' => 'profile',
                        'category' => 'woocommerce_webhook_topics',
                        'severity' => $severity,
                        'impact_count' => $impactCount,
                        'title' => 'WooCommerce webhook topic seti güvenli değil',
                        'recommended_action' => 'Entegrasyonlar ekranında önerilen WooCommerce webhook topic setini uygula; yalnızca sipariş ve ürün değişim topiclerini açık tut.',
                        'route' => 'mp.integrations',
                        'why' => $reason,
                        'top_warning' => null,
                    ];
                }

                return collect([$safeProfileItem, $topicItem])
                    ->filter()
                    ->values()
                    ->all();
            })
            ->flatten(1)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function shopifyWebhookTopicGuidanceForUser(int $userId): array
    {
        $defaults = IntegrationSyncProfile::defaultsForMarketplace('shopify');

        return MarketplaceStore::query()
            ->with('syncProfile')
            ->where('user_id', $userId)
            ->where('marketplace', 'shopify')
            ->get()
            ->map(function (MarketplaceStore $store) use ($defaults): ?array {
                $profile = $store->syncProfile;

                if (!$profile || !$profile->webhook_enabled) {
                    return null;
                }

                $topicAudit = IntegrationSyncProfile::auditWebhookTopics(
                    'shopify',
                    data_get($profile->extra_settings ?? [], 'webhook_topics', data_get($defaults, 'extra_settings.webhook_topics', []))
                );

                if ($topicAudit['matches_recommended']) {
                    return null;
                }

                $impactCount = max(1, count($topicAudit['missing']) + count($topicAudit['extra']));
                $severity = $topicAudit['is_empty'] || count($topicAudit['missing']) >= 3 || $topicAudit['extra'] !== []
                    ? 'critical'
                    : 'warning';

                $reason = match (true) {
                    $topicAudit['is_empty'] => 'Webhook açık ama Shopify topic seti boş bırakılmış.',
                    $topicAudit['extra'] !== [] => 'Önerilen set dışında topic seçildiği için gereksiz event yükü oluşabilir.',
                    default => 'Önerilen topiclerin bir kısmı kapalı olduğu için webhook-first akış eksik çalışır.',
                };

                return [
                    'store_id' => $store->id,
                    'store_name' => $store->store_name,
                    'marketplace' => $store->marketplace,
                    'sync_type' => 'profile',
                    'category' => 'shopify_webhook_topics',
                    'severity' => $severity,
                    'impact_count' => $impactCount,
                    'title' => 'Shopify webhook topic seti güvenli değil',
                    'recommended_action' => 'Entegrasyonlar ekranında önerilen Shopify webhook topic setini uygula; sipariş, iade, ürün ve stok değişimleri dışındaki topicleri kapalı tut.',
                    'route' => 'mp.integrations',
                    'why' => $reason,
                    'top_warning' => null,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function shopifySafeProfileGuidanceForUser(int $userId): array
    {
        return $this->buildSafeProfileGuidanceForUser(
            $userId,
            'shopify',
            'shopify_safe_profile',
            'Shopify güvenli profilinden sapma var',
            'Entegrasyonlar ekranında güvenli Shopify profilini uygula; gerekiyorsa gözden geçirip senkron profilini kaydet.',
            'Aşırı polling, açık push ayarları veya yüksek paralellik Shopify mağazasında gereksiz API yükü ve kuyruk gürültüsü üretebilir.'
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function trendyolSafeProfileGuidanceForUser(int $userId): array
    {
        return $this->buildSafeProfileGuidanceForUser(
            $userId,
            'trendyol',
            'trendyol_safe_profile',
            'Trendyol güvenli profilinden sapma var',
            'Entegrasyonlar ekranında güvenli Trendyol profilini uygula; gerekiyorsa gözden geçirip senkron profilini kaydet.',
            'Aşırı polling, açık push ayarları veya yüksek paralellik Trendyol API kotasını gereksiz tüketebilir ve kuyruk yükünü artırabilir.'
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function hepsiburadaSafeProfileGuidanceForUser(int $userId): array
    {
        return $this->buildSafeProfileGuidanceForUser(
            $userId,
            'hepsiburada',
            'hepsiburada_safe_profile',
            'Hepsiburada güvenli profilinden sapma var',
            'Entegrasyonlar ekranında güvenli Hepsiburada profilini uygula; gerekiyorsa gözden geçirip senkron profilini kaydet.',
            'Aşırı polling, açık push ayarları veya yüksek paralellik Hepsiburada OMS ve listing servislerine gereksiz yük bindirebilir.'
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function legacyFinancialProjectionGuidanceForUser(int $userId): array
    {
        return collect($this->legacyFinancialBacklog->summaryForUser($userId))
            ->map(function (array $row): array {
                $pendingRows = (int) ($row['pending_rows'] ?? 0);

                return [
                    'store_id' => $row['store_id'] ?? null,
                    'store_name' => $row['store_name'] ?? null,
                    'marketplace' => $row['marketplace'] ?? null,
                    'sync_type' => 'finance',
                    'category' => 'legacy_financial_projection',
                    'severity' => $pendingRows >= 25 ? 'critical' : 'warning',
                    'impact_count' => $pendingRows,
                    'title' => 'Legacy finans satirlari V2 ledger\'a tasinmamis',
                    'recommended_action' => 'Siparisler V2 ekraninda projection magazasini secip "Legacy finansi V2\'ye tasi" aksiyonunu calistir; once dry-run komutuyla aday sayiyi dogrula.',
                    'route' => 'mp.orders',
                    'why' => 'Eski muhasebe satirlari yeni ledger\'a tasinmadiginda confirmed kar, mutabakat ve Finans V2 gorunumu eksik kalir.',
                    'top_warning' => null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    protected function sortGuidanceItems(array &$items): void
    {
        usort($items, function (array $left, array $right): int {
            $severityWeight = ['critical' => 3, 'warning' => 2, 'info' => 1];

            return [$severityWeight[$right['severity']] ?? 0, $right['impact_count']]
                <=> [$severityWeight[$left['severity']] ?? 0, $left['impact_count']];
        });
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function makeGuidanceItem(
        array $row,
        string $category,
        string $severity,
        int $impactCount,
        string $title,
        string $recommendedAction,
        string $route,
        string $why
    ): array {
        return [
            'store_id' => $row['store_id'] ?? null,
            'store_name' => $row['store_name'] ?? null,
            'marketplace' => $row['marketplace'] ?? null,
            'sync_type' => $row['sync_type'] ?? null,
            'category' => $category,
            'severity' => $severity,
            'impact_count' => $impactCount,
            'title' => $title,
            'recommended_action' => $recommendedAction,
            'route' => $route,
            'why' => $why,
            'top_warning' => $row['top_warning'] ?? null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function buildSafeProfileGuidanceForUser(
        int $userId,
        string $marketplace,
        string $category,
        string $title,
        string $recommendedAction,
        string $why
    ): array {
        $defaults = IntegrationSyncProfile::defaultsForMarketplace($marketplace);

        return MarketplaceStore::query()
            ->with('syncProfile')
            ->where('user_id', $userId)
            ->where('marketplace', $marketplace)
            ->get()
            ->map(fn (MarketplaceStore $store) => $this->buildSafeProfileGuidanceItem(
                $store,
                $category,
                $title,
                $recommendedAction,
                $why,
                $defaults
            ))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>|null
     */
    protected function buildSafeProfileGuidanceItem(
        MarketplaceStore $store,
        string $category,
        string $title,
        string $recommendedAction,
        string $why,
        array $defaults
    ): ?array {
        $profile = $store->syncProfile;

        if (!$profile) {
            return null;
        }

        $checks = [
            'orders_poll_minutes' => $defaults['orders_poll_minutes'],
            'finance_poll_minutes' => $defaults['finance_poll_minutes'],
            'products_poll_minutes' => $defaults['products_poll_minutes'],
            'webhook_enabled' => (bool) $defaults['webhook_enabled'],
            'finance_enabled' => (bool) $defaults['finance_enabled'],
            'price_push_enabled' => (bool) $defaults['price_push_enabled'],
            'stock_push_enabled' => (bool) $defaults['stock_push_enabled'],
            'max_parallel_jobs' => $defaults['max_parallel_jobs'],
            'request_jitter_seconds' => $defaults['request_jitter_seconds'],
        ];

        $mismatchCount = 0;

        foreach ($checks as $field => $expected) {
            if ((string) ($profile->{$field} ?? null) === (string) $expected) {
                continue;
            }

            $mismatchCount++;
        }

        if ($mismatchCount === 0) {
            return null;
        }

        $critical = $mismatchCount >= 4
            || ((bool) $defaults['webhook_enabled'] && !$profile->webhook_enabled)
            || (!(bool) $defaults['price_push_enabled'] && $profile->price_push_enabled)
            || (!(bool) $defaults['stock_push_enabled'] && $profile->stock_push_enabled)
            || (int) $profile->max_parallel_jobs > (int) $defaults['max_parallel_jobs'];

        return [
            'store_id' => $store->id,
            'store_name' => $store->store_name,
            'marketplace' => $store->marketplace,
            'sync_type' => 'profile',
            'category' => $category,
            'severity' => $critical ? 'critical' : 'warning',
            'impact_count' => $mismatchCount,
            'title' => $title,
            'recommended_action' => $recommendedAction,
            'route' => 'mp.integrations',
            'why' => $why,
            'top_warning' => null,
        ];
    }
}
