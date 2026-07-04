<?php

namespace App\Console\Commands;

use App\Jobs\WhatsApp\ProcessStockAlertJob;
use App\Models\ChannelListing;
use App\Models\MpProduct;
use App\Models\WaAutomationConfig;
use Illuminate\Console\Command;

class WhatsAppProcessStockAlertsCommand extends Command
{
    protected $signature = 'whatsapp:process-stock-alerts';

    protected $description = 'Stok hatırlatıcı bekleyen kayıtları işler.';

    public function handle(): int
    {
        $config = WaAutomationConfig::get('stock_alert', ['enabled' => false]);

        if (empty($config['enabled'])) {
            $this->info('Stok hatırlatıcı aktif değil.');
            return self::SUCCESS;
        }

        // Bekleyen waitlist kayıtlarını bul ve ürün bazlı grupla
        $waitlistCounts = \App\Models\WaStockWaitlist::where('status', 'waiting')
            ->selectRaw('wc_product_id, COUNT(*) as count')
            ->groupBy('wc_product_id')
            ->get();

        $processed = 0;
        foreach ($waitlistCounts as $item) {
            // Ürünün güncel stoğunu bul
            $listing = ChannelListing::where('listing_id', $item->wc_product_id)
                ->whereHas('store', fn ($q) => $q->where('marketplace', 'woocommerce'))
                ->first();

            if ($listing && $listing->stock_quantity > 0) {
                ProcessStockAlertJob::dispatch(
                    $listing->mp_product_id ?? 0,
                    $listing->stock_quantity
                );
                $processed++;
            }
        }

        $this->info("{$processed} ürün için stok bildirim job'ı kuyruğa alındı.");
        return self::SUCCESS;
    }
}
