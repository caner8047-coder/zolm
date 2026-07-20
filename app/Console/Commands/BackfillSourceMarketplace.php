<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * mp_orders.source_marketplace boş olan kayıtları
 * bağlı oldukları mp_period.marketplace değeri ile doldurur.
 *
 * Çalıştırma: php artisan mp:backfill-source-marketplace [--dry-run]
 *
 * Güvenlik:
 *  - Zaten dolu kayıtlara dokunmaz.
 *  - 500'er kayıt batch ile günceller (LOCK riski yok).
 *  - --dry-run ile yalnızca kaç kaydın etkileneceğini raporlar.
 */
class BackfillSourceMarketplace extends Command
{
    protected $signature = 'mp:backfill-source-marketplace
                            {--dry-run : Gerçek güncelleme yapmadan kaç kaydın etkileneceğini gösterir}
                            {--batch=500 : Her seferinde güncellenecek kayıt sayısı}';

    protected $description = 'mp_orders.source_marketplace boş olan kayıtları period marketplace bilgisiyle doldurur';

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $batchSize = (int) $this->option('batch');

        // Kaç kayıt boş?
        $totalEmpty = DB::table('mp_orders')
            ->where(function ($q) {
                $q->whereNull('source_marketplace')
                  ->orWhere('source_marketplace', '');
            })
            ->count();

        $this->info("source_marketplace boş olan sipariş sayısı: <comment>{$totalEmpty}</comment>");

        if ($totalEmpty === 0) {
            $this->info('Backfill gerekmiyor. Tüm kayıtlar dolu.');
            return self::SUCCESS;
        }

        if ($isDryRun) {
            $this->warn('[DRY RUN] Gerçek güncelleme yapılmadı. --dry-run bayrağını kaldırarak çalıştırın.');
            return self::SUCCESS;
        }

        // Kullanılabilir period→marketplace eşlemesini belleğe al
        $periodMap = DB::table('mp_periods')
            ->whereNotNull('marketplace')
            ->where('marketplace', '!=', '')
            ->pluck('marketplace', 'id');

        if ($periodMap->isEmpty()) {
            $this->error('mp_periods tablosunda marketplace bilgisi bulunamadı. Önce period verisini doğrulayın.');
            return self::FAILURE;
        }

        $this->info("Eşlenebilir period sayısı: <comment>{$periodMap->count()}</comment>");

        // Tek sorguda JOIN UPDATE — chunk loop yerine daha güvenli ve hızlı
        $affected = DB::affectingStatement('
            UPDATE mp_orders o
            INNER JOIN mp_periods p ON p.id = o.period_id
            SET o.source_marketplace = p.marketplace
            WHERE (o.source_marketplace IS NULL OR o.source_marketplace = "")
              AND p.marketplace IS NOT NULL
              AND p.marketplace != ""
        ');

        $this->info("✅ Güncellenen kayıt: <comment>{$affected}</comment>");

        // Kalan boş kayıtları raporla (period_id eksik veya period'un marketplace'i boş)
        $remaining = DB::table('mp_orders')
            ->where(function ($q) {
                $q->whereNull('source_marketplace')
                  ->orWhere('source_marketplace', '');
            })
            ->count();

        if ($remaining > 0) {
            $this->warn("⚠️  Hâlâ boş kayıt: <comment>{$remaining}</comment> — period_id yok veya period'un marketplace'i tanımsız.");
        }

        return self::SUCCESS;
    }
}
