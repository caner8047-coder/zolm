<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\MpOrder;
use App\Models\MpAuditLog;
use App\Models\MpFinancialRule;
use Illuminate\Support\Facades\DB;

echo "Stopaj Geriye Dönük Düzeltme İşlemi Başlıyor...\n";

$stopajRate = MpFinancialRule::getRuleFloat('stopaj_rate') ?: 0.01;

$count = 0;
DB::beginTransaction();
try {
    MpOrder::where(function($q) {
        $q->whereNull('withholding_tax')->orWhere('withholding_tax', '<=', 0);
    })->whereNotIn('status', ['İptal Edildi'])->chunk(1000, function ($orders) use (&$count, $stopajRate) {
        foreach ($orders as $order) {
            $productVatRate = ((float) ($order->product_vat_rate ?? 20)) / 100;
            $grossAmount = (float) $order->gross_amount;
            $totalDiscounts = abs((float) $order->discount_amount) + abs((float) $order->campaign_discount);
            
            $discountedGross = max(0, $grossAmount - $totalDiscounts);
            $vatExcludedBase = $discountedGross / (1 + $productVatRate);
            
            $expectedStopaj = round($vatExcludedBase * $stopajRate, 2);
            
            if ($expectedStopaj > 0) {
                $order->withholding_tax = $expectedStopaj;
                $order->save();
                $count++;
            }
        }
        echo "İşlenen kayıt sayısı: {$count}...\n";
    });

    // Stopaj Hatası loglarını temizleyelim
    $deletedLogs = MpAuditLog::whereIn('rule_code', ['EKSİK_STOPAJ_KESINTISI', 'STOPAJ_HATA'])->delete();

    // is_flagged flaglerini yeniden hesaplamak için Audit Engine'i tetikleyebiliriz,
    // ancak şimdilik hata logu kalmayan siparişlerin flagini kaldıralım:
    $flaggedOrders = MpOrder::where('is_flagged', true)->get();
    $unflagCount = 0;
    foreach ($flaggedOrders as $fo) {
        if ($fo->auditLogs()->count() === 0) {
            $fo->is_flagged = false;
            $fo->save();
            $unflagCount++;
        }
    }

    DB::commit();
    echo "\nBaşarılı! {$count} adet siparişin eksik stopaj vergisi ({$stopajRate} oranında) geçmişe dönük olarak hesaplandı ve veritabanına işlendi.\n";
    echo "Silinen Hatalı Stopaj Logları (Audit): {$deletedLogs}\n";
    echo "Hata kalmadığı için bayrağı (riskli işlem) kaldırılan sipariş sayısı: {$unflagCount}\n";
    
} catch (\Exception $e) {
    DB::rollBack();
    echo "Hata: " . $e->getMessage() . "\n";
}
