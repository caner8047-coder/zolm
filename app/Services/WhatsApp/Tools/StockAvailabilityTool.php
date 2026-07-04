<?php

namespace App\Services\WhatsApp\Tools;

use App\Models\ChannelListing;

class StockAvailabilityTool implements AiTool
{
    public function name(): string { return 'stock_availability'; }

    public function description(): string
    {
        return 'Ürün veya varyasyon için güncel satın alınabilir stok bilgisi döndürür.';
    }

    public function execute(array $params, int $storeId, ?int $contactId = null): array
    {
        $wcProductId = $params['wc_product_id'] ?? null;
        $wcVariationId = $params['wc_variation_id'] ?? null;

        if (!$wcProductId) {
            return ['found' => false, 'message' => 'Ürün ID gerekli.'];
        }

        $query = ChannelListing::whereHas('store', fn ($s) => $s->where('marketplace', 'woocommerce'))
            ->where('listing_id', (string) $wcProductId);

        if ($wcVariationId) {
            $query->where('listing_id', (string) $wcVariationId);
        }

        $listing = $query->with('channelProduct')->first();

        if (!$listing) {
            return ['found' => false, 'message' => 'Ürün bulunamadı.'];
        }

        $inStock = $listing->stock_quantity > 0;

        return [
            'found' => true,
            'product_name' => $listing->channelProduct->title ?? '',
            'stock_quantity' => $listing->stock_quantity,
            'in_stock' => $inStock,
            'wc_product_id' => $listing->listing_id,
        ];
    }
}
