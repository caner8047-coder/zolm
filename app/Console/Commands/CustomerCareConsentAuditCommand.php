<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SupportConsentRecord;

class CustomerCareConsentAuditCommand extends Command
{
    protected $signature = 'customer-care:consent-audit {--store= : Store ID} {--dry-run : Dry-run only}';
    protected $description = 'İzin geçmişlerini ve opt-out durumlarını tarar.';

    public function handle()
    {
        $storeId = $this->option('store');
        $this->info("İzin Defteri Taranıyor... Store: " . ($storeId ?? 'Tümü'));

        $query = SupportConsentRecord::query();
        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        $granted = (clone $query)->where('status', 'granted')->count();
        $revoked = (clone $query)->where('status', 'revoked')->count();

        $this->line("Aktif Pazarlama İzinleri (Granted): " . $granted);
        $this->line("Geri Çekilen İzinler (Revoked/Opt-Out): " . $revoked);

        return 0;
    }
}
