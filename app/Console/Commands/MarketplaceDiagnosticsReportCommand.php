<?php

namespace App\Console\Commands;

use App\Models\IntegrationSyncRun;
use App\Services\Marketplace\MarketplaceDiagnosticsReportService;
use App\Services\Marketplace\MarketplaceProviderRegistry;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class MarketplaceDiagnosticsReportCommand extends Command
{
    protected $signature = 'marketplace:diagnostics-report
        {--store= : Sadece belirli mağaza ID}
        {--type=all : orders, products, finance veya all}
        {--hours=168 : Geriye dönük pencere (saat)}
        {--limit=200 : İncelenecek maksimum sync kaydı}
        {--smoke-only : Yalnız smoke test kayıtlarını kullan}';

    protected $description = 'Sync ve smoke test kayıtlarındaki mapping diagnostiklerini kanal ve veri tipi bazında özetler.';

    public function __construct(
        protected MarketplaceDiagnosticsReportService $diagnosticsReport,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $storeId = $this->option('store') ? (int) $this->option('store') : null;
        $syncType = trim((string) $this->option('type'));
        $hours = max(1, (int) $this->option('hours'));
        $limit = max(1, min(500, (int) $this->option('limit')));
        $smokeOnly = (bool) $this->option('smoke-only');

        if (!in_array($syncType, ['all', 'orders', 'products', 'finance'], true)) {
            $this->error('Geçersiz type seçimi. orders, products, finance veya all kullanın.');

            return self::FAILURE;
        }

        $runs = IntegrationSyncRun::query()
            ->with('store:id,store_name,marketplace')
            ->when($storeId, fn (Builder $query) => $query->where('store_id', $storeId))
            ->when($syncType !== 'all', fn (Builder $query) => $query->where('sync_type', $syncType))
            ->when($smokeOnly, fn (Builder $query) => $query->where('trigger_type', 'smoke_test'))
            ->where('created_at', '>=', now()->subHours($hours))
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->filter(fn (IntegrationSyncRun $run) => $run->diagnostics() !== []);

        $summary = $this->diagnosticsReport->summarize($runs);

        $this->components->info('Pazaryeri mapping diagnostik raporu');
        $this->newLine();
        $this->table(
            ['Özet', 'Değer'],
            [
                ['İncelenen kayıt', (string) $summary['totals']['runs']],
                ['Gruplar', (string) $summary['totals']['groups']],
                ['Smoke test', (string) $summary['totals']['smoke_runs']],
                ['Uyarılı run', (string) $summary['totals']['warning_runs']],
                ['Toplam uyarı', (string) $summary['totals']['total_warnings']],
            ]
        );

        if ($summary['rows'] === []) {
            $this->warn('Bu filtrelerle diagnostik kaydı bulunamadı.');

            return self::SUCCESS;
        }

        $rows = collect($summary['rows'])
            ->take(20)
            ->map(fn (array $row) => [
                $row['store_name'] ?? '-',
                MarketplaceProviderRegistry::get((string) $row['marketplace'])['label'] ?? $row['marketplace'],
                $row['sync_type'],
                (string) $row['total_runs'],
                (string) $row['warning_runs'],
                (string) $row['total_warning_count'],
                (string) ($row['missing_stock_code_count'] + $row['missing_barcode_count']),
                (string) ($row['missing_order_number_count'] + $row['missing_package_id_count'] + $row['missing_item_line_id_count'] + $row['missing_line_id_count']),
                (string) ($row['missing_amount_count'] + $row['missing_settlement_date_count']),
                (string) ($row['top_warning'] ?: '-'),
            ])
            ->all();

        $this->newLine();
        $this->table(
            ['Mağaza', 'Kanal', 'Tip', 'Run', 'Uyarılı', 'Toplam uyarı', 'Ürün eşleşme riski', 'Sipariş kimlik riski', 'Finans alan riski', 'Öne çıkan uyarı'],
            $rows
        );

        return self::SUCCESS;
    }
}
