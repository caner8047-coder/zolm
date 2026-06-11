<?php

namespace App\Console\Commands;

use App\Models\IntegrationSyncProfile;
use App\Models\MarketplaceStore;
use Illuminate\Console\Command;

class ApplyShopifySafeProfileCommand extends Command
{
    protected $signature = 'marketplace:apply-shopify-safe-profile
        {--store= : Sadece belirli Shopify mağaza ID}
        {--all : Tüm Shopify mağazalarına uygula}
        {--dry-run : Yalnızca etkilenecek mağazaları göster, kayıt yapma}';

    protected $description = 'Shopify mağazalarına düşük etkili ve güvenli sync profilini uygular.';

    public function handle(): int
    {
        $storeId = $this->option('store') ? (int) $this->option('store') : null;
        $applyAll = (bool) $this->option('all');
        $dryRun = (bool) $this->option('dry-run');

        if (!$applyAll && !$storeId) {
            $this->error('Bir Shopify mağazası seçmek için --store=ID kullanın veya tümü için --all verin.');

            return self::FAILURE;
        }

        $stores = MarketplaceStore::query()
            ->with('syncProfile')
            ->where('marketplace', 'shopify')
            ->when($storeId, fn ($query) => $query->whereKey($storeId))
            ->orderBy('store_name')
            ->get();

        if ($stores->isEmpty()) {
            $this->warn('Uygulanacak Shopify mağazası bulunamadı.');

            return self::SUCCESS;
        }

        $defaults = IntegrationSyncProfile::defaultsForMarketplace('shopify');

        $this->table(
            ['Store ID', 'Mağaza', 'Sipariş', 'Finans', 'Ürün', 'Push', 'Paralel', 'Jitter'],
            $stores->map(function (MarketplaceStore $store) use ($defaults): array {
                return [
                    (string) $store->id,
                    $store->store_name,
                    (string) $defaults['orders_poll_minutes'],
                    (string) $defaults['finance_poll_minutes'],
                    (string) $defaults['products_poll_minutes'],
                    ($defaults['price_push_enabled'] || $defaults['stock_push_enabled']) ? 'Açık' : 'Kapalı',
                    (string) $defaults['max_parallel_jobs'],
                    (string) $defaults['request_jitter_seconds'],
                ];
            })->all()
        );

        if ($dryRun) {
            $this->newLine();
            $this->components->warn("Dry-run tamamlandı. {$stores->count()} Shopify mağazası güvenli profil için uygun.");

            return self::SUCCESS;
        }

        foreach ($stores as $store) {
            $store->syncProfile()->updateOrCreate(
                ['store_id' => $store->id],
                $this->profilePayload($defaults)
            );
        }

        $this->newLine();
        $this->components->info("Shopify güvenli profili {$stores->count()} mağazaya uygulandı.");

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    protected function profilePayload(array $defaults): array
    {
        return [
            'orders_poll_minutes' => $defaults['orders_poll_minutes'],
            'finance_poll_minutes' => $defaults['finance_poll_minutes'],
            'products_poll_minutes' => $defaults['products_poll_minutes'],
            'questions_poll_minutes' => $defaults['questions_poll_minutes'],
            'backfill_mode' => $defaults['backfill_mode'],
            'backfill_days' => $defaults['backfill_days'] ?? null,
            'backfill_custom_from' => null,
            'backfill_custom_to' => null,
            'orders_enabled' => (bool) $defaults['orders_enabled'],
            'finance_enabled' => (bool) $defaults['finance_enabled'],
            'products_enabled' => (bool) $defaults['products_enabled'],
            'questions_enabled' => (bool) $defaults['questions_enabled'],
            'webhook_enabled' => (bool) $defaults['webhook_enabled'],
            'price_push_enabled' => (bool) $defaults['price_push_enabled'],
            'stock_push_enabled' => (bool) $defaults['stock_push_enabled'],
            'auto_match_enabled' => (bool) $defaults['auto_match_enabled'],
            'barcode_fallback_enabled' => (bool) $defaults['barcode_fallback_enabled'],
            'strict_unique_match_enabled' => (bool) $defaults['strict_unique_match_enabled'],
            'nightly_repair_sync_enabled' => (bool) $defaults['nightly_repair_sync_enabled'],
            'max_parallel_jobs' => $defaults['max_parallel_jobs'],
            'request_jitter_seconds' => $defaults['request_jitter_seconds'],
            'extra_settings' => $defaults['extra_settings'] ?? [],
        ];
    }
}
