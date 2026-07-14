<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MarketplaceStore;
use App\Models\WaKnowledgeArticle;

class CustomerCareSyncKnowledgeCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'customer-care:sync-knowledge {--store= : Hedef mağaza ID\'si} {--source= : Eşleşecek kaynak (katalog, policy, care)} {--force : Veritabanına gerçekten kaydeder}';

    /**
     * The console command description.
     */
    protected $description = 'ZOLM Bilgi Bankası ve ürün kataloğu grounding verilerini senkronize eder (varsayılan: dry-run)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $storeId = $this->option('store');
        $source = $this->option('source') ?? 'all';
        $force = $this->option('force');

        if (!$storeId) {
            $this->error('Lütfen mağaza ID\'sini belirtin: --store=ID');
            return 1;
        }

        $store = MarketplaceStore::find($storeId);
        if (!$store) {
            $this->error("ID'si {$storeId} olan mağaza bulunamadı.");
            return 1;
        }

        $this->info("🔄 Mağaza {$store->store_name} için bilgi grounding senkronizasyonu başlatılıyor...");
        if (!$force) {
            $this->warn("⚠️  DRY-RUN aktif. Veritabanına hiçbir kayıt yazılmayacak.");
        }

        $records = [];
        if ($source === 'all' || $source === 'policy') {
            $records[] = [
                'type' => 'knowledge_article',
                'title' => 'İade Koşulları Politikası',
                'category' => 'return_policy',
                'content' => 'Ürünlerimizi teslim aldığınız tarihten itibaren 14 gün içinde orijinal ambalajında ve kullanılmamış olarak iade edebilirsiniz. İadelerde kargo ücreti alıcıya aittir.',
            ];
            $records[] = [
                'type' => 'knowledge_article',
                'title' => 'Kargo ve Teslimat Süreçleri',
                'category' => 'shipping_policy',
                'content' => 'Siparişleriniz en geç 3 iş günü içerisinde kargoya teslim edilmektedir. 500 TL üzeri siparişlerde kargo ücretsizdir.',
            ];
        }

        if ($source === 'all' || $source === 'care') {
            $records[] = [
                'type' => 'knowledge_article',
                'title' => 'Yıkama ve Bakım Talimatı',
                'category' => 'care_instruction',
                'content' => 'Pamuklu ürünlerimizi maksimum 30 derece sıcaklıkta tersten yıkayınız. Ağartıcı kullanmayınız ve kurutma makinesine atmayınız.',
            ];
        }

        $syncedCount = 0;
        foreach ($records as $rec) {
            if ($force) {
                WaKnowledgeArticle::updateOrCreate(
                    [
                        'store_id' => $store->id,
                        'title' => $rec['title'],
                    ],
                    [
                        'slug' => \Illuminate\Support\Str::slug($rec['title']),
                        'category' => $rec['category'],
                        'content' => $rec['content'],
                        'status' => 'published',
                        'version' => 1,
                        'created_by' => $store->user_id,
                        'updated_by' => $store->user_id,
                    ]
                );
                $this->line("✅ Senkronize edildi: {$rec['title']} ({$rec['category']})");
            } else {
                $this->line("[Önizleme] Eklenecek/Güncellenecek: {$rec['title']} ({$rec['category']})");
            }
            $syncedCount++;
        }

        $this->info("✨ Senkronizasyon tamamlandı. Toplam işlem gören kayıt sayısı: {$syncedCount}");
        return 0;
    }
}
