<?php

namespace App\Services\Marketplace\Contracts;

use App\Models\MarketplaceStore;

interface PullsCatalogProducts
{
    /**
     * Pazaryerinden tam katalog ürün verilerini çeker.
     *
     * Bu metod listing verisinden (fiyat/stok) farklı olarak gerçek katalog içeriğini
     * (açıklama, görseller, özellikler, onay durumu) getirir.
     *
     * Dönen dizinin `items` anahtarı, her öğesi şu alanları içerebilecek bir dizidir:
     *  - hepsiburada_sku, merchant_sku, barcode
     *  - product_name, description, brand, category
     *  - images, attributes, variant_group
     *  - vat_rate, approval_status, rejection_reasons
     *  - import_tracking_id, raw_payload
     *
     * @param  array<string, mixed>  $options
     * @return array{items: array<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    public function pullCatalogProducts(MarketplaceStore $store, array $options = []): array;
}
