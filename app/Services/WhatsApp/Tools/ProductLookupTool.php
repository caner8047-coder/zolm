<?php

namespace App\Services\WhatsApp\Tools;

use App\Models\ChannelListing;
use App\Models\ChannelProduct;
use App\Models\MpProduct;

class ProductLookupTool implements AiTool
{
    public function name(): string { return 'product_lookup'; }

    public function description(): string
    {
        return 'Ürün adı, SKU veya WooCommerce ID ile ürün bilgisi arar. İsim, fiyat, stok ve ürün linkini döndürür.';
    }

    public function execute(array $params, int $storeId, ?int $contactId = null): array
    {
        $query = $params['query'] ?? '';
        $wcProductId = $params['wc_product_id'] ?? null;

        if (empty($query) && !$wcProductId) {
            return ['found' => false, 'message' => 'Ürün adı veya ID gerekli.'];
        }

        $listings = ChannelListing::whereHas('channelProduct', function ($q) use ($query, $wcProductId) {
            if ($wcProductId) {
                $q->where('external_product_id', (string) $wcProductId);
            } else {
                $q->where('title', 'like', "%{$query}%")
                    ->orWhere('stock_code', 'like', "%{$query}%");
            }
        })->whereHas('store', fn ($s) => $s->where('marketplace', 'woocommerce'))
            ->with(['channelProduct', 'mpProduct'])
            ->limit(5)
            ->get();

        if ($listings->isEmpty()) {
            return ['found' => false, 'message' => 'Ürün bulunamadı.'];
        }

        $results = $listings->map(function ($listing) {
            return [
                'name' => $listing->channelProduct->title ?? $listing->mpProduct->product_name ?? '',
                'stock_code' => $listing->channelProduct->stock_code ?? '',
                'price' => $listing->raw_payload['price'] ?? null,
                'stock_quantity' => $listing->stock_quantity,
                'wc_product_id' => $listing->listing_id,
                'category' => $listing->channelProduct->category_name ?? '',
                'brand' => $listing->channelProduct->brand ?? '',
            ];
        })->toArray();

        return ['found' => true, 'products' => $results];
    }
}
