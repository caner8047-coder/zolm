<?php

namespace App\Console\Commands;

use App\Models\IntegrationSyncProfile;
use App\Models\MarketplaceStore;
use Illuminate\Console\Command;

class ApplyRecommendedWebhookTopicsCommand extends Command
{
    protected $signature = 'marketplace:apply-recommended-webhook-topics
        {--store= : Sadece belirli mağaza ID}
        {--all : Uygun tüm mağazalara uygula}
        {--marketplace= : Tüm mağazalar için provider filtrele (woocommerce|shopify)}
        {--dry-run : Yalnızca etkilenecek mağazaları göster, kayıt yapma}';

    protected $description = 'WooCommerce ve Shopify için önerilen webhook topic setini mağaza profiline uygular.';

    public function handle(): int
    {
        $storeId = $this->option('store') ? (int) $this->option('store') : null;
        $applyAll = (bool) $this->option('all');
        $dryRun = (bool) $this->option('dry-run');
        $marketplace = $this->normalizeMarketplace($this->option('marketplace'));

        if (!$storeId && !$applyAll) {
            $this->error('Bir mağaza için --store=ID kullanın veya toplu uygulama için --all verin.');

            return self::FAILURE;
        }

        if ($applyAll && !$marketplace) {
            $this->error('Toplu uygulama için --marketplace=woocommerce veya --marketplace=shopify verin.');

            return self::FAILURE;
        }

        $stores = MarketplaceStore::query()
            ->with('syncProfile')
            ->when($storeId, fn ($query) => $query->whereKey($storeId))
            ->when($marketplace, fn ($query) => $query->where('marketplace', $marketplace))
            ->orderBy('store_name')
            ->get();

        if ($stores->isEmpty()) {
            $this->warn('Uygulanacak mağaza bulunamadı.');

            return self::SUCCESS;
        }

        $unsupported = $stores->filter(function (MarketplaceStore $store): bool {
            return IntegrationSyncProfile::recommendedWebhookTopicsForMarketplace($store->marketplace) === [];
        });

        if ($unsupported->isNotEmpty()) {
            $this->newLine();
            $this->components->warn('Bazı mağazalar desteklenmediği için atlandı:');
            $this->table(
                ['Store ID', 'Mağaza', 'Pazaryeri'],
                $unsupported->map(fn (MarketplaceStore $store) => [
                    (string) $store->id,
                    $store->store_name,
                    (string) $store->marketplace,
                ])->all()
            );
        }

        $eligible = $stores->reject(fn (MarketplaceStore $store) => IntegrationSyncProfile::recommendedWebhookTopicsForMarketplace($store->marketplace) === []);

        if ($eligible->isEmpty()) {
            $this->warn('Önerilen webhook topic seti uygulanabilecek uygun mağaza bulunamadı.');

            return self::SUCCESS;
        }

        $this->table(
            ['Store ID', 'Mağaza', 'Pazaryeri', 'Topic sayısı'],
            $eligible->map(function (MarketplaceStore $store): array {
                $topics = IntegrationSyncProfile::recommendedWebhookTopicsForMarketplace($store->marketplace);

                return [
                    (string) $store->id,
                    $store->store_name,
                    ucfirst((string) $store->marketplace),
                    (string) count($topics),
                ];
            })->all()
        );

        if ($dryRun) {
            $this->newLine();
            $this->components->warn("Dry-run tamamlandı. {$eligible->count()} mağaza için önerilen webhook topic seti hazır.");

            return self::SUCCESS;
        }

        foreach ($eligible as $store) {
            $defaults = IntegrationSyncProfile::defaultsForMarketplace($store->marketplace);
            $existingExtra = $store->syncProfile?->extra_settings ?? [];
            $extraSettings = is_array($existingExtra) ? $existingExtra : [];
            $extraSettings['webhook_topics'] = IntegrationSyncProfile::recommendedWebhookTopicsForMarketplace($store->marketplace);

            $store->syncProfile()->updateOrCreate(
                ['store_id' => $store->id],
                [
                    'orders_poll_minutes' => $store->syncProfile?->orders_poll_minutes ?? $defaults['orders_poll_minutes'],
                    'finance_poll_minutes' => $store->syncProfile?->finance_poll_minutes ?? $defaults['finance_poll_minutes'],
                    'products_poll_minutes' => $store->syncProfile?->products_poll_minutes ?? $defaults['products_poll_minutes'],
                    'backfill_mode' => $store->syncProfile?->backfill_mode ?? $defaults['backfill_mode'],
                    'backfill_days' => $store->syncProfile?->backfill_days ?? ($defaults['backfill_days'] ?? null),
                    'backfill_custom_from' => $store->syncProfile?->backfill_custom_from,
                    'backfill_custom_to' => $store->syncProfile?->backfill_custom_to,
                    'orders_enabled' => $store->syncProfile?->orders_enabled ?? (bool) $defaults['orders_enabled'],
                    'finance_enabled' => $store->syncProfile?->finance_enabled ?? (bool) $defaults['finance_enabled'],
                    'products_enabled' => $store->syncProfile?->products_enabled ?? (bool) $defaults['products_enabled'],
                    'webhook_enabled' => true,
                    'price_push_enabled' => $store->syncProfile?->price_push_enabled ?? (bool) $defaults['price_push_enabled'],
                    'stock_push_enabled' => $store->syncProfile?->stock_push_enabled ?? (bool) $defaults['stock_push_enabled'],
                    'auto_match_enabled' => $store->syncProfile?->auto_match_enabled ?? (bool) $defaults['auto_match_enabled'],
                    'barcode_fallback_enabled' => $store->syncProfile?->barcode_fallback_enabled ?? (bool) $defaults['barcode_fallback_enabled'],
                    'strict_unique_match_enabled' => $store->syncProfile?->strict_unique_match_enabled ?? (bool) $defaults['strict_unique_match_enabled'],
                    'nightly_repair_sync_enabled' => $store->syncProfile?->nightly_repair_sync_enabled ?? (bool) $defaults['nightly_repair_sync_enabled'],
                    'max_parallel_jobs' => $store->syncProfile?->max_parallel_jobs ?? $defaults['max_parallel_jobs'],
                    'request_jitter_seconds' => $store->syncProfile?->request_jitter_seconds ?? $defaults['request_jitter_seconds'],
                    'extra_settings' => $extraSettings,
                ]
            );
        }

        $this->newLine();
        $this->components->info("Önerilen webhook topic seti {$eligible->count()} mağazaya uygulandı.");

        return self::SUCCESS;
    }

    protected function normalizeMarketplace(?string $marketplace): ?string
    {
        $value = strtolower(trim((string) $marketplace));

        if ($value === '') {
            return null;
        }

        return in_array($value, ['woocommerce', 'shopify'], true) ? $value : null;
    }
}
