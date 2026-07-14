<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\WaContact;

class CustomerCareRetentionScanCommand extends Command
{
    protected $signature = 'customer-care:retention-scan {--store= : Store ID} {--dry-run : Dry-run only}';
    protected $description = 'Saklama süresi dolan kayıtları dry-run raporlar.';

    public function handle()
    {
        $storeId = $this->option('store');
        $this->info("Retention taraması yapılıyor... Store: " . ($storeId ?? 'Tümü'));

        $query = SupportConversation::query();
        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        // Saklama süresi: 1 yıldan eski mesajlar, 2 yıldan eski konuşmalar
        $oldConversations = $query->where('created_at', '<=', now()->subYears(2))->count();
        
        $this->line("Saklama süresi dolan ve anonimleştirilecek Konuşmalar: " . $oldConversations);
        $this->line("Saklama süresi dolan Mesajlar: " . SupportMessage::where('created_at', '<=', now()->subYear())->count());
        $this->line("Bu işlem sadece raporlama amaçlıdır. Anonimleştirme için customer-care:anonymize kullanın.");

        return 0;
    }
}
