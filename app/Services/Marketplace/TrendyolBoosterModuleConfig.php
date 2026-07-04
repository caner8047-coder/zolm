<?php

namespace App\Services\Marketplace;

use Illuminate\Support\Str;

class TrendyolBoosterModuleConfig
{
    /**
     * Booster içindeki tüm modül gruplarını döner.
     *
     * @return array<int, array{key: string, label: string, items: array<int, array{label: string, icon: string, module?: string, query?: array<string, mixed>, soon?: bool}>}>
     */
    public static function getGroups(): array
    {
        return [
            [
                'key' => 'product',
                'label' => 'Ürün Stalk',
                'items' => [
                    ['label' => 'Ürün Analizi', 'icon' => 'search', 'module' => 'analysis'],
                    ['label' => 'Ürün Karşılaştırma', 'icon' => 'arrow-left-right', 'module' => 'comparison'],
                    ['label' => 'Ürün Alım Kararı', 'icon' => 'radar', 'module' => 'decision'],
                    ['label' => 'Pazar Karşılaştırması', 'icon' => 'bar-chart-2', 'module' => 'market'],
                    ['label' => 'Booster Radar', 'icon' => 'activity', 'module' => 'tracking'],
                ],
            ],
            [
                'key' => 'calculation',
                'label' => 'Hesaplama',
                'items' => [
                    ['label' => 'Sat veya Satma (AI)', 'icon' => 'activity', 'module' => 'sell_decision'],
                    ['label' => 'Kâr-Zarar Hesaplama', 'icon' => 'gauge', 'module' => 'profit_loss'],
                    ['label' => 'Brüt Kâr-Zarar Hesaplama', 'icon' => 'banknote', 'module' => 'gross_profit'],
                    ['label' => 'Net Kâr Hesaplama', 'icon' => 'banknote', 'module' => 'net_profit'],
                    ['label' => 'Hedef Planlayıcı', 'icon' => 'radar', 'module' => 'target_planner'],
                    ['label' => 'Komisyon Oranları', 'icon' => 'badge-percent', 'module' => 'commissions'],
                    ['label' => 'Kargo Fiyatları', 'icon' => 'truck', 'module' => 'shipping_rates'],
                ],
            ],
            [
                'key' => 'market',
                'label' => 'Pazar Araçları',
                'items' => [
                    ['label' => 'Çok Satanlar', 'icon' => 'trending-up', 'module' => 'bestseller'],
                    ['label' => 'Tedarikçi Bul', 'icon' => 'package', 'module' => 'supplier_finder'],
                    ['label' => 'Anahtar Kelime Takibi', 'icon' => 'activity', 'module' => 'keyword_tracking'],
                    ['label' => 'Anahtar Kelime Aratma', 'icon' => 'search', 'module' => 'keyword'],
                    ['label' => 'Stok Sorgulama', 'icon' => 'boxes', 'module' => 'stock'],
                    ['label' => 'Rakip Takibi', 'icon' => 'users', 'module' => 'competitor'],
                    ['label' => 'Trend Kelimeler', 'icon' => 'bar-chart-2', 'module' => 'trends'],
                    ['label' => 'Trendyol Yorumlar', 'icon' => 'star', 'module' => 'reviews'],
                ],
            ],
            [
                'key' => 'tracking',
                'label' => 'Takip Araçları',
                'items' => [
                    ['label' => 'Favorilerim', 'icon' => 'heart', 'module' => 'tracking', 'query' => ['favorites' => 1]],
                    ['label' => 'Fiyat Takibi', 'icon' => 'line-chart', 'module' => 'price'],
                    ['label' => 'Analiz Geçmişi', 'icon' => 'history', 'module' => 'history'],
                    ['label' => 'Bildirimler', 'icon' => 'bell', 'module' => 'notifications'],
                ],
            ],
        ];
    }

    /**
     * Aktif olan modüllerin düz listesini döner.
     *
     * @return array<string, array{label: string, icon: string}>
     */
    public static function getModules(): array
    {
        return collect(self::getGroups())
            ->flatMap(fn (array $group): array => $group['items'])
            ->reject(fn (array $item): bool => (bool) ($item['soon'] ?? false))
            ->reject(fn (array $item): bool => (bool) ($item['query'] ?? false))
            ->filter(fn (array $item): bool => isset($item['module']))
            ->mapWithKeys(fn (array $item): array => [
                $item['module'] => [
                    'label' => $item['label'],
                    'icon' => $item['icon'],
                ],
            ])
            ->all();
    }

    /**
     * Verilen modülün hangi ana gruba ait olduğunu döner.
     */
    public static function getGroupOfModule(string $item): string
    {
        return match ($item) {
            'bestseller', 'supplier_finder', 'keyword_tracking', 'keyword', 'stock', 'competitor', 'trends', 'reviews' => 'market',
            'sell_decision', 'profit_loss', 'gross_profit', 'net_profit', 'target_planner', 'commissions', 'shipping_rates' => 'calculation',
            'favorites', 'price', 'history', 'notifications' => 'tracking',
            default => 'product',
        };
    }

    /**
     * UI'da gösterilecek modül başlık/açıklama bilgilerini döner.
     *
     * @return array{eyebrow: string, title: string, description: string}
     */
    public static function getWorkspaceCopy(string $activeModule, bool $favoritesOnly = false): array
    {
        if ($activeModule === 'tracking' && $favoritesOnly) {
            return ['eyebrow' => 'Takip araçları', 'title' => 'Favorilerim', 'description' => 'Favoriye aldığınız takip ürünlerini tek listede izleyin ve anlık sinyallerini karşılaştırın.'];
        }

        return match ($activeModule) {
            'analysis' => ['eyebrow' => 'Ürün araştırması', 'title' => 'Ürün Analizi', 'description' => 'Tek ürünü canlı Trendyol verileriyle okuyun; kesin ve tahmini metrikleri ayırarak takibe hazırlayın.'],
            'comparison' => ['eyebrow' => 'Yan yana karar', 'title' => 'Ürün Karşılaştırma', 'description' => 'En fazla dört ürünü fiyat, ilgi, satıcı ve stok sinyalleriyle aynı yüzeyde karşılaştırın.'],
            'decision' => ['eyebrow' => 'Alım kararı', 'title' => 'Ürün Alım Kararı', 'description' => 'Maliyet, komisyon, kargo ve hedef marjı tek karar skorunda birleştirin.'],
            'sell_decision' => ['eyebrow' => 'Hesaplama', 'title' => 'Sat veya Satma (AI)', 'description' => 'Canlı Trendyol verisi, satış hızı, yorum artışı, vergi sonrası net kâr ve pazar görünürlüğüyle alım kararını üretin.'],
            'profit_loss' => ['eyebrow' => 'Hesaplama', 'title' => 'Kâr-Zarar Hesaplama', 'description' => 'Satıştan ürün maliyeti, komisyon, kargo, KDV ve gelir vergisine kadar tam gelir tablosunu görün.'],
            'gross_profit' => ['eyebrow' => 'Hesaplama', 'title' => 'Brüt Kâr-Zarar Hesaplama', 'description' => 'Ürün maliyeti sonrası brüt kârı ve pazaryeri giderleri öncesi marjı hızlıca ölçün.'],
            'net_profit' => ['eyebrow' => 'Hesaplama', 'title' => 'Net Kâr Hesaplama', 'description' => 'Komisyon, kargo, KDV ve gelir vergisi sonrası cebinizde kalan gerçek birim kârı hesaplayın.'],
            'target_planner' => ['eyebrow' => 'Hesaplama', 'title' => 'Hedef Planlayıcı', 'description' => 'Ciro hedefini, net marjı ve ortalama satış fiyatını sipariş adedi, günlük hedef ve maksimum alış fiyatına dönüştürün.'],
            'market' => ['eyebrow' => 'Pazar görünümü', 'title' => 'Pazar Karşılaştırması', 'description' => 'Rakip grubunun fiyat dağılımını, talep yoğunluğunu ve pazar liderini görün.'],
            'tracking' => ['eyebrow' => 'Takip motoru', 'title' => 'Booster Radar', 'description' => 'Takipteki ürünlerin zaman serisini, satış tahminini, fırsat ve risk skorlarını yönetin.'],
            'bestseller' => ['eyebrow' => 'Pazar araçları', 'title' => 'Çok Satanlar', 'description' => 'Son 3 günlük satış ve ciro sinyaline göre Trendyol çok satanlarını kategori, kelime ve fiyat filtresiyle keşfedin.'],
            'supplier_finder' => ['eyebrow' => 'Pazar araçları', 'title' => 'Tedarikçi Radar', 'description' => 'Trendyol ürünündeki gerçek satıcıları ve Google Alışveriş’te ürün kimliği güçlü eşleşen teklifleri; fiyat, rekabet ve zaman sinyalleriyle birlikte görün.'],
            'keyword_tracking' => ['eyebrow' => 'Pazar araçları', 'title' => 'Anahtar Kelime Takibi', 'description' => 'Ürünlerin hedef kelimelerdeki sırasını izleyin; görünürlük kaybını ve ilk sayfa fırsatlarını görün.'],
            'stock' => ['eyebrow' => 'Pazar araçları', 'title' => 'Stok Sorgulama', 'description' => 'Satıcı stoklarını sorgulayın; ardışık kontrollerde stok erimesi ve tahmini satış sinyali üretin.'],
            'keyword' => ['eyebrow' => 'Pazar araçları', 'title' => 'Anahtar Kelime Aratma', 'description' => 'Satmak istediğiniz ürünü yazın; Trendyol arama sonuçlarından popüler kelime, ürün başlığı ve açıklama ipuçları çıkarın.'],
            'competitor' => ['eyebrow' => 'Pazar araçları', 'title' => 'Rakip Takibi', 'description' => 'Rakip mağazaları izleyin; yeni ürün ve katalog değişimlerini yakalayın.'],
            'trends' => ['eyebrow' => 'Pazar araçları', 'title' => 'Trend Kelimeler', 'description' => 'Rakip mağaza başlıklarından yükselen kelimeleri keşfedin ve kendi ürününüzün arama sırasını takibe alın.'],
            'reviews' => ['eyebrow' => 'Pazar araçları', 'title' => 'Trendyol Yorumlar', 'description' => 'Mağazanızdaki Trendyol yorumlarını toplayın, filtreleyin ve WooCommerce ürün sayfalarında modern bir widget ile gösterin.'],
            'price' => ['eyebrow' => 'Takip araçları', 'title' => 'Fiyat Takibi', 'description' => 'Takip ürünlerinin fiyat geçmişini ve son fiyat değişimlerini inceleyin.'],
            'history' => ['eyebrow' => 'Takip araçları', 'title' => 'Analiz Geçmişi', 'description' => 'Booster araçlarında oluşan karar, sorgu ve takip hareketlerini zaman sırasıyla görün.'],
            'commissions' => ['eyebrow' => 'Hesaplama', 'title' => 'Komisyon Oranları', 'description' => 'Kategori bazlı komisyon oranlarını araştırın ve karar hesaplarında kullanın.'],
            'shipping_rates' => ['eyebrow' => 'Hesaplama', 'title' => 'Kargo Fiyatları', 'description' => 'Desi ve kargo firması bazlı fiyatları yönetin, barem altı gönderi maliyetlerini otomatik hesaplayın.'],
            'notifications' => ['eyebrow' => 'Takip araçları', 'title' => 'Bildirimler', 'description' => 'Fiyat, stok, rakip ve kelime sinyalleri için bildirim tercihlerini yönetin.'],
            default => ['eyebrow' => 'Ürün araştırması', 'title' => 'Ürün Analizi', 'description' => 'Tek ürünü canlı Trendyol verileriyle okuyun.'],
        };
    }
}
