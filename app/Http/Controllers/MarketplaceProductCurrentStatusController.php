<?php

namespace App\Http\Controllers;

use App\Models\ChannelListing;
use App\Models\MarketplaceStore;
use App\Models\MpProduct;
use App\Services\Marketplace\MarketplaceManualSyncDispatchService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MarketplaceProductCurrentStatusController extends Controller
{
    /**
     * Livewire erişilemediğinde de ürün satırındaki senkron aksiyonu gerçek bir
     * POST isteği olarak çalışır. Böylece kullanıcı her zaman sonuç mesajı alır.
     */
    public function __invoke(Request $request, int $productId, MarketplaceManualSyncDispatchService $dispatcher): RedirectResponse
    {
        $product = MpProduct::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($productId);

        $listings = ChannelListing::query()
            ->with(['store.connection', 'store.syncProfile', 'channelProduct'])
            ->where('mp_product_id', $product->id)
            ->whereHas('store', function (Builder $query) use ($request) {
                $query->where('user_id', $request->user()->id)
                    ->where('is_active', true)
                    ->whereHas('connection', fn (Builder $connection) => $connection->whereIn('status', ['configured', 'connected', 'demo']));
            })
            ->get();

        $targets = $listings->groupBy('store_id')->map(fn ($rows) => [
            'store' => $rows->first()->store,
            'listings' => $rows,
        ]);

        if ($targets->isEmpty()) {
            $targets = MarketplaceStore::query()
                ->with(['connection', 'syncProfile'])
                ->where('user_id', $request->user()->id)
                ->where('is_active', true)
                ->whereHas('connection', fn (Builder $connection) => $connection->whereIn('status', ['configured', 'connected', 'demo']))
                ->get()
                ->mapWithKeys(fn (MarketplaceStore $store) => [$store->id => ['store' => $store, 'listings' => collect()]]);
        }

        if ($targets->isEmpty()) {
            return redirect()->route('mp.products')->with('warning', 'Güncel durum için bağlantısı tamamlanmış mağaza bulunamadı.');
        }

        $messages = [];
        foreach ($targets as $target) {
            $store = $target['store'];
            $channelProduct = $target['listings']->pluck('channelProduct')->filter()->first();

            try {
                $result = $dispatcher->dispatch($store, 'products', [
                    'bypass_recent' => true,
                    'source' => 'current_status_refresh',
                    'origin_screen' => 'products',
                    'mp_product_ids' => [$product->id],
                    'channel_listing_ids' => $target['listings']->pluck('id')->map(fn ($id) => (int) $id)->all(),
                    'stock_codes' => collect([$product->stock_code, $channelProduct?->stock_code])->filter()->unique()->values()->all(),
                    'barcodes' => collect([$product->barcode, $channelProduct?->barcode])->filter()->unique()->values()->all(),
                    'options' => array_filter([
                        'start_date' => now()->subDays(180)->toIso8601String(),
                        'end_date' => now()->toIso8601String(),
                        'stock_code' => $product->stock_code ?: $channelProduct?->stock_code,
                        'barcode' => $product->barcode ?: $channelProduct?->barcode,
                        'external_product_id' => $channelProduct?->external_product_id,
                    ]),
                ]);

                $feedback = $dispatcher->feedback($result, 'Ürün', $store->store_name ?: ucfirst($store->marketplace));
                $messages[] = $feedback['message'];
            } catch (\Throwable $exception) {
                $messages[] = ($store->store_name ?: ucfirst($store->marketplace)).': '.$exception->getMessage();
            }
        }

        return redirect()->route('mp.products')->with('success', implode(' ', $messages));
    }
}
