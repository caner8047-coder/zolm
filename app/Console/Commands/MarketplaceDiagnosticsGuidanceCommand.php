<?php

namespace App\Console\Commands;

use App\Services\Marketplace\MarketplaceDiagnosticsGuidanceService;
use App\Services\Marketplace\MarketplaceProviderRegistry;
use Illuminate\Console\Command;

class MarketplaceDiagnosticsGuidanceCommand extends Command
{
    protected $signature = 'marketplace:diagnostics-guidance
        {user : Kullanıcı ID}
        {--store= : Sadece belirli mağaza ID}
        {--type=all : orders, products, finance veya all}
        {--hours=168 : Geriye dönük pencere (saat)}
        {--limit=200 : İncelenecek maksimum sync kaydı}
        {--smoke-only : Yalnız smoke test kayıtlarını kullan}';

    protected $description = 'Mapping diagnostiklerinden hareketle operatör için öncelikli düzeltme önerileri üretir.';

    public function __construct(
        protected MarketplaceDiagnosticsGuidanceService $guidanceService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $syncType = trim((string) $this->option('type'));

        if (!in_array($syncType, ['all', 'orders', 'products', 'finance'], true)) {
            $this->error('Geçersiz type seçimi. orders, products, finance veya all kullanın.');

            return self::FAILURE;
        }

        $guidance = $this->guidanceService->guidanceForUser((int) $this->argument('user'), [
            'store_id' => $this->option('store') ? (int) $this->option('store') : null,
            'sync_type' => $syncType,
            'hours' => max(1, (int) $this->option('hours')),
            'limit' => max(1, min(500, (int) $this->option('limit'))),
            'smoke_only' => (bool) $this->option('smoke-only'),
        ]);

        $this->components->info('Pazaryeri diagnostik karar desteği');
        $this->newLine();
        $this->table(
            ['Özet', 'Değer'],
            [
                ['Toplam öneri', (string) $guidance['totals']['items']],
                ['Kritik', (string) $guidance['totals']['critical']],
                ['Uyarı', (string) $guidance['totals']['warning']],
                ['Bilgi', (string) $guidance['totals']['info']],
            ]
        );

        if ($guidance['items'] === []) {
            $this->warn('Bu filtrelerle öneri üretilmedi. Diagnostik kayıtları temiz görünüyor.');

            return self::SUCCESS;
        }

        $rows = collect($guidance['items'])
            ->take(15)
            ->map(fn (array $item) => [
                $item['store_name'] ?? '-',
                MarketplaceProviderRegistry::get((string) $item['marketplace'])['label'] ?? $item['marketplace'],
                $item['sync_type'],
                $item['severity'],
                (string) $item['impact_count'],
                $item['title'],
                $item['recommended_action'],
            ])
            ->all();

        $this->newLine();
        $this->table(
            ['Mağaza', 'Kanal', 'Tip', 'Seviye', 'Etkilenen', 'Başlık', 'Önerilen aksiyon'],
            $rows
        );

        return self::SUCCESS;
    }
}
