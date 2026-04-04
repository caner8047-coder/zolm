<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';

$kernel = $app->make(Kernel::class);
$response = $kernel->handle(
    $request = Request::capture()
);

use App\Models\MpProduct;
use Illuminate\Support\Facades\DB;

try {
    echo "<h2>Çift Ürünleri Temizleme (Duplicate Cleanup)</h2>";
    echo "<p>Başlıyor...</p>";

    $duplicates = DB::table('mp_products')
        ->select('stock_code')
        ->whereNotNull('stock_code')
        ->where('stock_code', '!=', '')
        ->groupBy('stock_code')
        ->havingRaw('COUNT(*) > 1')
        ->get();

    $deletedCount = 0;
    $mergedCount = 0;

    foreach ($duplicates as $dup) {
        $products = MpProduct::where('stock_code', $dup->stock_code)->get();
        
        // Asıl ürünü bul (Satış fiyatı 0'dan büyük olan VEYA barkodu stok koduyla aynı OLMAYAN)
        // Çünkü Trendyol'dan gelen gerçek ürünlerin barkodu farklıdır ve satış fiyatı çekilmiştir.
        $master = $products->firstWhere('sale_price', '>', 0);
        
        if (!$master) {
            $master = $products->first(function ($p) {
                return $p->barcode !== $p->stock_code;
            });
        }

        // Eğer asıl ürün bulunamadıysa ilk ürünü asıl kabul et
        if (!$master) {
            $master = $products->first();
        }
        
        foreach ($products as $p) {
            if ($p->id !== $master->id) {
                // Bu ürün "kopya" ürün (Eski v0.5'ten gelen, barkodu olmayan vs.)
                
                // Eğer master üründe kargo/maliyet eksikse, kopya üründen aktar
                $fieldsToMerge = ['cogs', 'packaging_cost', 'cargo_cost', 'desi', 'pieces', 'vat_rate'];
                $needsSave = false;
                
                foreach ($fieldsToMerge as $field) {
                    if (($master->$field == 0 || empty($master->$field)) && $p->$field > 0) {
                        $master->$field = $p->$field;
                        $needsSave = true;
                    }
                }
                
                if ($needsSave) {
                    $master->save();
                    $mergedCount++;
                }
                
                // Kopyayı sil
                $p->delete();
                $deletedCount++;
            }
        }
    }

    echo "<div style='color:green; font-weight:bold;'>İşlem Başarıyla Tamamlandı!</div>";
    echo "<ul>";
    echo "<li>Silinen Fazladan (Kopya) Ürün Sayısı: <strong>" . $deletedCount . "</strong></li>";
    echo "<li>Maliyetleri Asıl Ürüne Aktarılan (Güncellenen) Ürün Sayısı: <strong>" . $mergedCount . "</strong></li>";
    echo "</ul>";
    echo "<p>Artık bu dosyayı silebilirsiniz.</p>";

} catch (\Exception $e) {
    echo "HATA: " . $e->getMessage();
}
