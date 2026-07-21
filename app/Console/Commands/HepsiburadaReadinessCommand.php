<?php

namespace App\Console\Commands;

use App\Models\MarketplaceStore;
use App\Models\User;
use App\Services\Marketplace\HepsiburadaReadinessOutputSanitizer;
use App\Services\Marketplace\HepsiburadaReadinessService;
use App\Services\Marketplace\MarketplaceStoreAccessResolver;
use Illuminate\Console\Command;

class HepsiburadaReadinessCommand extends Command
{
    protected $signature = 'marketplace:hepsiburada-readiness
        {store : Mağaza ID veya Store Key}
        {--actor-id= : İşlemi gerçekleştiren aktör/kullanıcı ID}
        {--reason= : İşlemin gerçekleştirilme gerekçesi}
        {--connection-only : Sadece bağlantı testi yapar}
        {--categories : Kategori ağacı testi yapar}
        {--category-id= : Nitelik sorgusu için Kategori ID}
        {--catalog : Katalog ürün okuma testi yapar}
        {--batch-id= : Batch status testi için Batch ID}
        {--batch-operation=price-uploads : Batch status operasyon türü (price-uploads, stock-uploads)}
        {--max-items=5 : Raporlanacak maksimum kayıt sayısı (1-10)}
        {--timeout=15 : API istek timeout süresi (1-15 saniye)}
        {--format=table : Çıktı formatı (table veya json)}
        {--confirm-read : Gerçek canlı API okuma isteği gönderilmesini onaylar}';

    protected $description = 'Hepsiburada API entegrasyonu bağlantı, rollout kapıları ve salt-okuma hazırlıklarını denetler.';

    public function handle(HepsiburadaReadinessService $readinessService): int
    {
        $actorId = $this->option('actor-id');
        $reason = trim((string) $this->option('reason'));

        if (!$actorId) {
            $this->error('İşlem aktörü zorunludur (--actor-id).');
            return self::FAILURE;
        }

        if ($reason === '') {
            $this->error('İşlem gerekçesi zorunludur (--reason).');
            return self::FAILURE;
        }

        $actor = User::find($actorId);
        if (!$actor || !$actor->is_active) {
            $this->error('Aktör kullanıcısı bulunamadı veya pasif durumda (authorization_failed).');
            return self::FAILURE;
        }

        $storeIdOrKey = $this->argument('store');
        $store = MarketplaceStore::query()
            ->where('id', $storeIdOrKey)
            ->orWhere('seller_id', $storeIdOrKey)
            ->first();

        if (!$store) {
            $this->error("Mağaza bulunamadı: {$storeIdOrKey}");
            return self::FAILURE;
        }

        if ($store->marketplace !== 'hepsiburada') {
            $this->error("Bu komut yalnızca Hepsiburada mağazaları için geçerlidir. Seçilen mağaza marketplace: {$store->marketplace}");
            return self::FAILURE;
        }

        // Store Authorization check
        try {
            app(MarketplaceStoreAccessResolver::class)
                ->resolveForCredentialManagement($actor, $store->id);
        } catch (\Throwable $e) {
            $this->error('Aktörün bu mağaza üzerinde entegrasyon kontrol yetkisi bulunmuyor (authorization_failed).');
            return self::FAILURE;
        }

        // Validate mutually exclusive operation flags
        $opFlags = array_filter([
            $this->option('categories') ? 'categories' : null,
            $this->option('category-id') ? 'attributes' : null,
            $this->option('catalog') ? 'catalog' : null,
            $this->option('batch-id') ? 'batch' : null,
            $this->option('connection-only') ? 'connection' : null,
        ]);

        if (count($opFlags) > 1) {
            $this->error('Birden fazla operasyon seçeneği aynı anda belirtilemez.');
            return self::FAILURE;
        }

        $operation = reset($opFlags) ?: 'connection';

        // Input validation & clamping
        $maxItems = max(1, min(10, (int) ($this->option('max-items') ?: 5)));
        $timeout = max(1, min(15, (int) ($this->option('timeout') ?: 15)));

        $batchOperation = (string) $this->option('batch-operation');
        if (!in_array($batchOperation, ['price-uploads', 'stock-uploads'], true)) {
            $this->error('Geçersiz batch operasyon türü (yalnızca price-uploads veya stock-uploads desteklenir).');
            return self::FAILURE;
        }

        $categoryId = $this->option('category-id') ? trim((string) $this->option('category-id')) : null;
        if ($operation === 'attributes' && ($categoryId === null || $categoryId === '')) {
            $this->error('Nitelik sorgusu için kategori ID boş olamaz (--category-id).');
            return self::FAILURE;
        }

        $batchId = $this->option('batch-id') ? trim((string) $this->option('batch-id')) : null;
        if ($operation === 'batch' && ($batchId === null || $batchId === '')) {
            $this->error('Batch sorgusu için batch ID boş olamaz (--batch-id).');
            return self::FAILURE;
        }

        $options = [
            'operation'       => $operation,
            'confirm_read'    => (bool) $this->option('confirm-read'),
            'category_id'     => $categoryId,
            'batch_id'        => $batchId,
            'batch_operation' => $batchOperation,
            'max_items'       => $maxItems,
            'timeout'         => $timeout,
            'actor_id'        => $actor->id,
            'reason'          => $reason,
        ];

        config(['marketplace.hepsiburada.request_timeout' => $timeout]);

        // Run inspection
        $result = $readinessService->inspect($store, $options);

        // Sanitize details if present
        $maskedBatchId = $batchId ? HepsiburadaReadinessOutputSanitizer::maskString($batchId) : null;
        if ($maskedBatchId) {
            $result['batch_id_masked'] = $maskedBatchId;
        }

        // Output format
        $format = (string) $this->option('format');
        if ($format === 'json') {
            $this->output->write(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return $result['is_ready'] ? self::SUCCESS : self::FAILURE;
        }

        // Table Format Output
        $this->info("=== ZOLM Hepsiburada Canlı Okuma Readiness Raporu ===");
        $this->line("Correlation ID : " . $result['correlation_id']);
        $this->line("Mağaza Adı     : " . $store->store_name . " (ID: " . $store->id . ")");
        $this->line("Aktör ID       : " . $actor->id . " (" . $actor->name . ")");
        $this->line("Gerekçe        : " . $reason);
        $this->line("Operasyon      : " . strtoupper($operation));
        $this->line("Canlı İstek    : " . ($options['confirm_read'] ? 'ONAYLANDI' : 'KAPALI (Yapılandırma analizi)'));
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
            $this->info("\n--- Dönen Kayıt Örnekleri (Sanitize Edilmiş, Maks: " . $maxItems . ") ---");
            if (is_array($result['details'])) {
                foreach (array_slice($result['details'], 0, $maxItems) as $index => $item) {
                    $this->line("[" . ($index + 1) . "] " . json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                }
            } else {
                $this->line(json_encode($result['details'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }
        }

        return $result['is_ready'] ? self::SUCCESS : self::FAILURE;
    }
}
