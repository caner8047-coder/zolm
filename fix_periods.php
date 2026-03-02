<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\MpOrder;
use App\Models\MpTransaction;
use App\Models\MpAuditLog;
use App\Models\MpPeriod;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

echo "Dönem Dağıtma İşlemi Başlıyor...\n";

// Tüm periyotları önbelleğe alalım ki sürekli sorgu atmasın
$periodsBuffer = [];

function getPeriodId($dateStr, &$periodsBuffer, $userId) {
    if (empty($dateStr)) return null;
    
    try {
        $date = Carbon::parse($dateStr);
        $year = $date->year;
        $month = $date->month;
        
        $key = "{$year}-{$month}";
        
        if (!isset($periodsBuffer[$key])) {
            $period = MpPeriod::firstOrCreate(
                [
                    'user_id' => $userId,
                    'year'    => $year,
                    'month'   => $month,
                ],
                [
                    'marketplace' => 'Trendyol',
                    'status'      => 'draft',
                ]
            );
            $periodsBuffer[$key] = $period->id;
        }
        
        return $periodsBuffer[$key];
    } catch (\Exception $e) {
        return null;
    }
}

DB::beginTransaction();
try {
    $userId = 1; // Tüm yetkisizler az önce 1'e çekilmişti
    
    // 1. Siparişleri Dağıt (Sipariş Tarihine Göre)
    $ordersCount = 0;
    MpOrder::chunk(1000, function ($orders) use (&$ordersCount, &$periodsBuffer, $userId) {
        foreach ($orders as $order) {
            $correctPeriodId = getPeriodId($order->order_date, $periodsBuffer, $userId);
            
            if ($correctPeriodId && $order->period_id !== $correctPeriodId) {
                // Sadece period_id update, timestamps false atılabilirdi ama kalsın
                DB::table('mp_orders')->where('id', $order->id)->update(['period_id' => $correctPeriodId]);
                $ordersCount++;
            }
        }
        echo "Sipariş işlendi: {$ordersCount} taşındı...\n";
    });

    // 2. Transactionları Dağıt (İşlem Tarihine Göre)
    $txCount = 0;
    MpTransaction::chunk(1000, function ($txs) use (&$txCount, &$periodsBuffer, $userId) {
        foreach ($txs as $tx) {
            $correctPeriodId = getPeriodId($tx->transaction_date, $periodsBuffer, $userId);
            if ($correctPeriodId && $tx->period_id !== $correctPeriodId) {
                DB::table('mp_transactions')->where('id', $tx->id)->update(['period_id' => $correctPeriodId]);
                $txCount++;
            }
        }
        echo "Transaction işlendi: {$txCount} taşındı...\n";
    });

    // 3. Audit Logları Dağıt (Önce siparişlere bağlı, yoksa created_at'e göre)
    // En garantisi AuditLog'un bağlı olduğu siparişin period_id'sine eşitlemektir.
    $auditCount = 0;
    MpAuditLog::chunk(1000, function ($logs) use (&$auditCount) {
        foreach ($logs as $log) {
            if ($log->order_id) {
                $orderPeriodId = DB::table('mp_orders')->where('id', $log->order_id)->value('period_id');
                if ($orderPeriodId && $log->period_id !== $orderPeriodId) {
                    DB::table('mp_audit_logs')->where('id', $log->id)->update(['period_id' => $orderPeriodId]);
                    $auditCount++;
                }
            }
        }
        echo "Audit işlendi: {$auditCount} taşındı...\n";
    });

    DB::commit();
    echo "\nBaşarılı! Veriler ilgili aylara (Sipariş/İşlem tarihlerine göre) gerçekçi biçimde dağıtıldı.\n";
    echo "Taşınan Sipariş: {$ordersCount}\n";
    echo "Taşınan İşlem (Transaction): {$txCount}\n";
    echo "Taşınan Denetim (Audit): {$auditCount}\n";
    
} catch (\Exception $e) {
    DB::rollBack();
    echo "Hata: " . $e->getMessage() . "\n";
}
