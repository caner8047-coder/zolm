<?php

namespace App\Console\Commands;

use App\Models\MarketplaceStore;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class DeactivatePlaceholderMarketplaceStoresCommand extends Command
{
    protected $signature = 'marketplace:deactivate-placeholder-stores
        {--store=* : Sadece belirli mağaza ID\'lerini tara}
        {--all : Tüm aktif mağazaları tara}
        {--dry-run : Yalnızca etkilenecek mağazaları göster, kayıt yapma}';

    protected $description = 'Örnek veya sahte credential kullanan aktif pazaryeri mağazalarını güvenli şekilde pasife alır.';

    public function handle(): int
    {
        $storeIds = collect((array) $this->option('store'))
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values()
            ->all();

        if (!(bool) $this->option('all') && $storeIds === []) {
            $this->error('Tüm aktif mağazaları taramak için --all verin veya belirli kayıtlar için --store=ID kullanın.');

            return self::FAILURE;
        }

        $stores = MarketplaceStore::query()
            ->with('connection')
            ->where('is_active', true)
            ->when($storeIds !== [], fn ($query) => $query->whereKey($storeIds))
            ->orderBy('marketplace')
            ->orderBy('store_name')
            ->get();

        $rows = $stores
            ->map(function (MarketplaceStore $store): ?array {
                $reason = $this->placeholderReason($store);

                if ($reason === null) {
                    return null;
                }

                return [
                    'store' => $store,
                    'reason' => $reason,
                ];
            })
            ->filter()
            ->values();

        if ($rows->isEmpty()) {
            $this->components->info('Pasife alınacak placeholder mağaza bulunamadı.');

            return self::SUCCESS;
        }

        $this->table(
            ['Store ID', 'Kanal', 'Mağaza', 'Sebep'],
            $rows->map(fn (array $row) => [
                (string) $row['store']->id,
                $row['store']->marketplace,
                $row['store']->store_name,
                $row['reason'],
            ])->all()
        );

        if ((bool) $this->option('dry-run')) {
            $this->newLine();
            $this->components->warn("Dry-run tamamlandı. {$rows->count()} mağaza pasife alınmaya uygun.");

            return self::SUCCESS;
        }

        foreach ($rows as $row) {
            /** @var MarketplaceStore $store */
            $store = $row['store'];
            $store->update(['is_active' => false]);
        }

        $this->newLine();
        $this->components->info("{$rows->count()} placeholder mağaza pasife alındı.");

        return self::SUCCESS;
    }

    protected function placeholderReason(MarketplaceStore $store): ?string
    {
        $connection = $store->connection;
        $credentials = (array) ($connection?->credentials_encrypted ?? []);

        $apiBaseUrl = trim((string) ($connection?->api_base_url ?? ''));
        if ($this->looksLikePlaceholderUrl($apiBaseUrl)) {
            return 'API URL örnek / placeholder görünüyor.';
        }

        $storeUrl = trim((string) ($credentials['store_url'] ?? ''));
        if ($this->looksLikePlaceholderUrl($storeUrl)) {
            return 'Store URL örnek / placeholder görünüyor.';
        }

        $sellerId = trim((string) ($store->seller_id ?? ''));
        if (Str::startsWith(Str::upper($sellerId), 'SELLER-SECOND-')) {
            return 'Seller ID örnek veri formatında görünüyor.';
        }

        $apiKey = Str::lower(trim((string) ($credentials['api_key'] ?? '')));
        if (in_array($apiKey, ['test-key', 'service-key', 'sample-key', 'demo-key', 'key', 'key-first', 'cicek-key'], true)) {
            return 'API key örnek / test anahtarı görünüyor.';
        }

        $apiSecret = Str::lower(trim((string) ($credentials['api_secret'] ?? '')));
        if (in_array($apiSecret, ['test-secret', 'sample-secret', 'demo-secret'], true)) {
            return 'API secret örnek / test anahtarı görünüyor.';
        }

        return null;
    }

    protected function looksLikePlaceholderUrl(string $value): bool
    {
        if ($value === '' || filter_var($value, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $host = Str::lower((string) parse_url($value, PHP_URL_HOST));

        if ($host === '') {
            return false;
        }

        return $host === 'example.com'
            || $host === 'www.example.com'
            || $host === 'shop.example.com'
            || $host === 'localhost'
            || $host === '127.0.0.1'
            || Str::endsWith($host, '.example.com');
    }
}
