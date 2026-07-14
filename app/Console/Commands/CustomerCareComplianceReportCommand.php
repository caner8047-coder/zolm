<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SupportDataSubjectRequest;
use App\Models\SupportLegalHold;

class CustomerCareComplianceReportCommand extends Command
{
    protected $signature = 'customer-care:compliance-report {--store= : Store ID} {--dry-run : Dry-run only}';
    protected $description = 'DSR, legal hold ve onay durumlarını raporlar.';

    public function handle()
    {
        $storeId = $this->option('store');
        $this->info("Compliance Raporu Hazırlanıyor... Store: " . ($storeId ?? 'Tümü'));

        $dsrQuery = SupportDataSubjectRequest::query();
        $holdQuery = SupportLegalHold::query();

        if ($storeId) {
            $dsrQuery->where('store_id', $storeId);
            $holdQuery->where('store_id', $storeId);
        }

        $this->line("Bekleyen DSR Talepleri: " . $dsrQuery->where('status', 'pending')->count());
        $this->line("Aktif Yasal Veri Engelleri (Legal Holds): " . $holdQuery->where('active', true)->count());

        return 0;
    }
}
