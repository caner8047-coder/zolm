<?php
 
namespace App\Console\Commands;
 
use App\Models\MarketplaceStore;
use App\Services\Marketplace\HepsiburadaReadinessService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class HepsiburadaReadinessCommand extends Command
{
    protected $signature = 'marketplace:hepsiburada-readiness
        {store : Mağaza ID veya Store Key}
        {--connection-only : Sadece bağlantı testi yapar}
        {--categories : Kategori ağacı testi yapar}
        {--category-id= : Nitelik sorgusu için Kategori ID}
        {--catalog : Katalog ürün okuma testi yapar}
        {--batch-id= : Batch status testi için Batch ID}
        {--batch-operation=price-uploads : Batch status operasyon türü (price-uploads, stock-uploads)}
        {--max-items=5 : Raporlanacak maksimum kayıt sayısı}
        {--timeout=15 : API istek timeout süresi}
        {--format=table : Çıktı formatı (table veya json)}
        {--confirm-read : Gerçek canlı API okuma isteği gönderilmesini onaylar}';

    protected $description = 'Hepsiburada API entegrasyonu bağlantı, rollout kapıları ve salt-okuma hazırlıklarını denetler.';

    public function handle(HepsiburadaReadinessService $readinessService): int
    {
        $storeIdOrKey = $this->argument('store');

        $store = MarketplaceStore::query()
            ->where('id', $storeIdOrKey)
            ->orWhere('store_key', $storeIdOrKey)
            ->first();

        if (!$store) {
            $this->error("Mağaza bulunamadı: {$storeIdOrKey}");
            return self::FAILURE;
        }

        if ($store->marketplace !== 'hepsiburada') {
            $this->error("Bu komut yalnızca Hepsiburada mağazaları için geçerlidir. Seçilen mağaza marketplace: {$store->marketplace}");
            return self::FAILURE;
        }

        // Determine operation
        $operation = 'connection';
        if ($this->option('categories')) {
            $operation = 'categories';
        } elseif ($this->option('category-id')) {
            $operation = 'attributes';
        } elseif ($this->option('catalog')) {
            $operation = 'catalog';
        } elseif ($this->option('batch-id')) {
            $operation = 'batch';
        } elseif ($this->option('connection-only')) {
            $operation = 'connection';
        }

        $options = [
            'operation'       => $operation,
            'confirm_read'    => (bool) $this->option('confirm-read'),
            'category_id'     => $this->option('category-id'),
            'batch_id'        => $this->option('batch-id'),
            'batch_operation' => $this->option('batch-operation'),
            'max_items'       => (int) $this->option('max-items'),
            'timeout'         => (int) $this->option('timeout'),
        ];

        // Ensure timeout is configured in marketplace config dynamically for this run
        config(['marketplace.hepsiburada.request_timeout' => $options['timeout']]);

        // DB Mutation Guard - Before counts
        $guardTables = [
            'channel_products',
            'channel_listings',
            'mp_categories',
            'mp_category_attributes',
            'mp_category_attribute_values',
            'mp_orders',
            'mp_transactions',
        ];

        $beforeCounts = [];
        foreach ($guardTables as $table) {
            try {
                $beforeCounts[$table] = DB::table($table)->count();
            } catch (\Throwable $e) {
                $beforeCounts[$table] = 0;
            }
        }

        // Run inspection
        $result = $readinessService->inspect($store, $options);

        // DB Mutation Guard - After counts
        $afterCounts = [];
        $mutations = 0;
        foreach ($guardTables as $table) {
            try {
                $afterCounts[$table] = DB::table($table)->count();
            } catch (\Throwable $e) {
                $afterCounts[$table] = 0;
            }
            $diff = abs($afterCounts[$table] - $beforeCounts[$table]);
            if ($diff > 0) {
                $mutations += $diff;
            }
        }

        if ($mutations > 0) {
            $result['is_ready'] = false;
            $result['decision'] = 'read_probe_mutated_database';
            $result['message'] = "Veritabanı değişikliği (mutation) saptandı! Smoke test sırasında DB yazma/silme yapılamaz.";
            
            // Re-update audit log decision to reflect mutation violation if it was saved
            try {
                \App\Models\HepsiburadaReadinessAudit::where('correlation_id', $result['correlation_id'])
                    ->update([
                        'db_mutation_count' => $mutations,
                        'decision' => 'read_probe_mutated_database',
                    ]);
            } catch (\Throwable $e) {
                // ignore
            }
        }

        // Output format
        $format = $this->option('format');
        if ($format === 'json') {
            $this->output->write(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return $result['is_ready'] ? self::SUCCESS : self::FAILURE;
        }

        // Table Format Output
        $this->info("=== ZOLM Hepsiburada Canlı Okuma Readiness Raporu ===");
        $this->line("Correlation ID : " . $result['correlation_id']);
        $this->line("Mağaza Adı     : " . $store->store_name . " (ID: " . $store->id . ")");
        $this->line("Operasyon      : " . strtoupper($operation));
        $this->line("Canlı İstek    : " . ($options['confirm_read'] ? 'ONAYLANDI' : 'KAPALI (Sadece parametre kontrolü)'));
        $this->line("Karar Kodu     : " . $result['decision']);
        $this->line("Hazır mı?      : " . ($result['is_ready'] ? 'EVET' : 'HAYIR'));
        $this->line("Durum Mesajı   : " . $result['message']);

        if (isset($result['duration_ms'])) {
            $this->line("Süre           : " . $result['duration_ms'] . " ms");
        }

        if (isset($result['item_count'])) {
            $this->line("Dönen Kayıt    : " . $result['item_count']);
        }

        if (!empty($result['details'])) {
            $this->info("\n--- Dönen Kayıt Örnekleri (Maks: " . $options['max_items'] . ") ---");
            if (is_array($result['details'])) {
                foreach (array_slice($result['details'], 0, $options['max_items']) as $index => $item) {
                    $this->line("[" . ($index + 1) . "] " . json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                }
            } else {
                $this->line(json_encode($result['details'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }
        }

        if ($mutations > 0) {
            $this->error("\nKRİTİK UYARI: Veritabanında {$mutations} adet kayıt değişikliği saptandı!");
        }

        return $result['is_ready'] ? self::SUCCESS : self::FAILURE;
    }
}
