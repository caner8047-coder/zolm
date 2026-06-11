<?php

namespace Tests\Unit;

use App\Livewire\MarketplaceOrders;
use App\Livewire\MarketplaceMatchingCenter;
use App\Livewire\MpProductsManager;
use App\Models\ChannelListing;
use App\Models\ChannelOrder;
use App\Models\ChannelOrderItem;
use App\Models\ChannelProduct;
use App\Models\IntegrationConnection;
use App\Models\MarketplaceStore;
use Tests\TestCase;

class MarketplacePublicProductUrlTest extends TestCase
{
    public function test_products_manager_builds_koctas_product_url_from_product_sku(): void
    {
        $listing = $this->koctasListing([
            'product_sku' => '5003085487',
            'shop_sku' => '1BRJZEM00218',
        ]);

        $url = (new MpProductsManager())->marketplacePublicProductUrl($listing);

        $this->assertSame(
            'https://www.koctas.com.tr/zem-alto-bohem-sallanir-berjer-gri/p/5003085487',
            $url,
        );
    }

    public function test_orders_view_builds_same_koctas_product_url(): void
    {
        $listing = $this->koctasListing([
            'product_sku' => '5003085487',
            'shop_sku' => '1BRJZEM00218',
        ]);

        $url = (new MarketplaceOrders())->marketplacePublicProductUrl($listing);

        $this->assertSame(
            'https://www.koctas.com.tr/zem-alto-bohem-sallanir-berjer-gri/p/5003085487',
            $url,
        );
    }

    public function test_koctas_product_url_falls_back_to_search_when_product_code_is_missing(): void
    {
        $listing = $this->koctasListing([
            'shop_sku' => '1BRJZEM00218',
        ], externalProductId: '1BRJZEM00218');

        $url = (new MpProductsManager())->marketplacePublicProductUrl($listing);

        $this->assertSame(
            'https://www.koctas.com.tr/search?text=Zem%20Alto%20Bohem%20Sallan%C4%B1r%20Berjer%2C%20Gri',
            $url,
        );
    }

    public function test_products_manager_builds_woocommerce_product_url_from_title(): void
    {
        $listing = $this->woocommerceListing('Zem Boey Yuvarlak Orta Sehpa/Bench Natürel Ayak, Kırık Beyaz');

        $url = (new MpProductsManager())->marketplacePublicProductUrl($listing);

        $this->assertSame(
            'https://www.zemhome.com.tr/magaza/zem-boey-yuvarlak-orta-sehpa-bench-naturel-ayak-kirik-beyaz/',
            $url,
        );
    }

    public function test_orders_view_builds_same_woocommerce_product_url(): void
    {
        $listing = $this->woocommerceListing('Zem Benetta Orta Sehpa Seti, Natürel Ayak Kırık Beyaz Sehpa Desenli Puf (1 ADET PUF 1 ADET SEHPA)');

        $url = (new MarketplaceOrders())->marketplacePublicProductUrl($listing);

        $this->assertSame(
            'https://www.zemhome.com.tr/magaza/zem-benetta-orta-sehpa-seti-naturel-ayak-kirik-beyaz-sehpa-desenli-puf-1-adet-puf-1-adet-sehpa/',
            $url,
        );
    }

    public function test_woocommerce_product_url_prefers_api_permalink(): void
    {
        $listing = $this->woocommerceListing('Zem Liva Sandıklı Bohem Puf - Gri', [
            'parent' => [
                'name' => 'Zem Liva Sandıklı Bohem Puf',
                'permalink' => 'https://www.zemhome.com.tr/magaza/zem-liva-sandikli-bohem-puf/',
            ],
        ]);

        $url = (new MpProductsManager())->marketplacePublicProductUrl($listing);

        $this->assertSame(
            'https://www.zemhome.com.tr/magaza/zem-liva-sandikli-bohem-puf/',
            $url,
        );
    }

    public function test_listing_status_labels_are_turkish_for_channel_statuses(): void
    {
        $productsManager = new MpProductsManager();
        $matchingCenter = new MarketplaceMatchingCenter();

        $this->assertSame('Yayında', $productsManager->listingStatusLabel('publish'));
        $this->assertSame('Pasif', $productsManager->listingStatusLabel('inactive'));
        $this->assertSame('Onay bekliyor', $productsManager->listingStatusLabel('pending_approval'));
        $this->assertSame('Bilinmeyen durum', $productsManager->listingStatusLabel('unexpected_remote_status'));
        $this->assertSame('success', $productsManager->listingStatusTone('publish'));
        $this->assertSame('danger', $productsManager->listingStatusTone('inactive'));

        $this->assertSame('Yayında', $matchingCenter->statusLabel('publish'));
        $this->assertSame('Pasif', $matchingCenter->statusLabel('inactive'));
    }

    public function test_orders_view_builds_koctas_product_search_url_from_order_item_without_listing(): void
    {
        $store = new MarketplaceStore([
            'marketplace' => 'koctas',
            'store_name' => 'ZEM DIZAYN',
        ]);
        $order = new ChannelOrder();
        $order->setRelation('store', $store);

        $item = new ChannelOrderItem([
            'product_name' => 'Modica Bench, Gri',
            'stock_code' => '1BRJZEM00143',
            'raw_payload' => [
                'offer_sku' => '1BRJZEM00143',
                'product_title' => 'Modica Bench, Gri',
            ],
        ]);

        $url = (new MarketplaceOrders())->marketplacePublicProductUrlForOrderItem($item, $order);

        $this->assertSame(
            'https://www.koctas.com.tr/search?text=Modica%20Bench%2C%20Gri',
            $url,
        );
    }

    /**
     * @param  array<string, mixed>  $rawPayload
     */
    protected function koctasListing(array $rawPayload, string $externalProductId = '5003085487'): ChannelListing
    {
        $store = new MarketplaceStore([
            'marketplace' => 'koctas',
            'store_name' => 'ZEM DIZAYN',
            'seller_id' => null,
        ]);

        $product = new ChannelProduct([
            'external_product_id' => $externalProductId,
            'stock_code' => '1BRJZEM00218',
            'barcode' => '8690000000012',
            'title' => 'Zem Alto Bohem Sallanır Berjer, Gri',
            'raw_payload' => array_merge([
                'product_title' => 'Zem Alto Bohem Sallanır Berjer, Gri',
            ], $rawPayload),
        ]);

        $listing = new ChannelListing([
            'listing_id' => 'OFF-1',
            'listing_status' => 'active',
        ]);

        $listing->setRelation('store', $store);
        $listing->setRelation('channelProduct', $product);

        return $listing;
    }

    /**
     * @param  array<string, mixed>  $rawPayload
     */
    protected function woocommerceListing(string $title, array $rawPayload = []): ChannelListing
    {
        $store = new MarketplaceStore([
            'marketplace' => 'woocommerce',
            'store_name' => 'Zem Home',
            'seller_id' => 'woo-zem-home',
        ]);

        $connection = new IntegrationConnection([
            'provider' => 'woocommerce',
            'api_base_url' => 'https://www.zemhome.com.tr/wp-json/wc/v3/',
            'credentials_encrypted' => [],
        ]);

        $store->setRelation('connection', $connection);

        $product = new ChannelProduct([
            'external_product_id' => '16735',
            'stock_code' => '1SHPZEM00009',
            'title' => $title,
            'raw_payload' => array_replace([
                'id' => 16735,
                'name' => $title,
            ], $rawPayload),
        ]);

        $listing = new ChannelListing([
            'listing_id' => '16735',
            'listing_status' => 'publish',
        ]);

        $listing->setRelation('store', $store);
        $listing->setRelation('channelProduct', $product);

        return $listing;
    }
}
