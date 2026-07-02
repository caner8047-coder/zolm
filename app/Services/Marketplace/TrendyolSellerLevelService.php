<?php

namespace App\Services\Marketplace;

use App\Models\ChannelListing;
use App\Models\ChannelOrderItem;
use App\Models\MarketplaceStore;
use App\Models\TrendyolBoosterProduct;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class TrendyolSellerLevelService
{
    private const GENERAL_THRESHOLDS = [
        5 => ['revenue' => 285_000_000, 'orders' => 275_000],
        4 => ['revenue' => 40_000_000, 'orders' => 38_500],
        3 => ['revenue' => 6_000_000, 'orders' => 6_000],
        2 => ['revenue' => 300_000, 'orders' => 385],
    ];

    /**
     * Trendyol'un kategoriye özel Seviye 4/5 baremleri.
     * Sıfır veya null eşik, ilgili ölçütün aranmadığı anlamına gelir.
     *
     * @var array<int, array{name: string, keywords: array<int, string>, 5: ?array{revenue: ?float, orders: ?int}, 4: ?array{revenue: ?float, orders: ?int}}>
     */
    private const CATEGORY_THRESHOLDS = [
        ['name' => 'Parfüm ve Deodorant', 'keywords' => ['parfüm', 'parfum', 'deodorant'], 5 => null, 4 => ['revenue' => 15_000_000, 'orders' => 5_000]],
        ['name' => 'Saç Bakım', 'keywords' => ['saç bakım', 'sac bakim', 'şampuan', 'sampuan'], 5 => null, 4 => ['revenue' => 28_500_000, 'orders' => 38_500]],
        ['name' => 'Süpermarket', 'keywords' => ['süpermarket', 'supermarket'], 5 => null, 4 => ['revenue' => 30_000_000, 'orders' => 30_000]],
        ['name' => 'Anne ve Bebek Bakım', 'keywords' => ['anne ve bebek bakım', 'bebek bakım', 'bebek bezi'], 5 => null, 4 => ['revenue' => 14_000_000, 'orders' => 15_000]],
        ['name' => 'Ev Bakım ve Temizlik', 'keywords' => ['ev bakım', 'ev bakim', 'temizlik'], 5 => null, 4 => ['revenue' => 20_000_000, 'orders' => 25_000]],
        ['name' => 'Gıda & İçecek', 'keywords' => ['gıda', 'gida', 'içecek', 'icecek'], 5 => null, 4 => ['revenue' => 10_000_000, 'orders' => 28_000]],
        ['name' => 'Pet Shop', 'keywords' => ['pet shop', 'kedi', 'köpek', 'kopek'], 5 => null, 4 => ['revenue' => 16_000_000, 'orders' => 34_000]],
        ['name' => 'Sağlık', 'keywords' => ['sağlık', 'saglik'], 5 => null, 4 => ['revenue' => 18_000_000, 'orders' => 18_000]],
        ['name' => 'Tesettür Giyim', 'keywords' => ['tesettür', 'tesettur'], 5 => null, 4 => ['revenue' => 10_000_000, 'orders' => 7_500]],
        ['name' => 'Erkek Tekstil', 'keywords' => ['erkek tekstil', 'erkek giyim'], 5 => null, 4 => ['revenue' => 10_000_000, 'orders' => 20_000]],
        ['name' => 'Giyim', 'keywords' => ['giyim', 'elbise', 'pantolon', 'gömlek', 'gomlek'], 5 => null, 4 => ['revenue' => 27_000_000, 'orders' => 23_000]],
        ['name' => 'Ayakkabı', 'keywords' => ['ayakkabı', 'ayakkabi', 'sneaker', 'bot'], 5 => null, 4 => ['revenue' => 25_000_000, 'orders' => 12_000]],
        ['name' => 'Oto Aksesuar', 'keywords' => ['oto aksesuar', 'otomobil aksesuar'], 5 => null, 4 => ['revenue' => 18_250_000, 'orders' => 1_050]],
        ['name' => 'Cep Telefonu Aksesuarları', 'keywords' => ['cep telefonu aksesuar', 'telefon aksesuar'], 5 => ['revenue' => 80_000_000, 'orders' => null], 4 => null],
        ['name' => 'Bilgisayar Aksesuarları', 'keywords' => ['bilgisayar aksesuar'], 5 => ['revenue' => 25_000_000, 'orders' => null], 4 => ['revenue' => 15_000_000, 'orders' => 10_000]],
        ['name' => 'Tablet Aksesuarları', 'keywords' => ['tablet aksesuar'], 5 => ['revenue' => 60_000_000, 'orders' => null], 4 => null],
        ['name' => 'Aksesuar', 'keywords' => ['aksesuar', 'takı', 'taki', 'mücevher', 'mucevher'], 5 => null, 4 => ['revenue' => 13_000_000, 'orders' => 9_000]],
        ['name' => 'Gözlük', 'keywords' => ['gözlük', 'gozluk'], 5 => null, 4 => ['revenue' => 10_400_000, 'orders' => 6_000]],
        ['name' => 'Outdoor Ekipman', 'keywords' => ['outdoor ekipman', 'kamp ekipman'], 5 => null, 4 => ['revenue' => 18_000_000, 'orders' => 11_500]],
        ['name' => 'Outdoor', 'keywords' => ['outdoor'], 5 => null, 4 => ['revenue' => 5_000_000, 'orders' => 4_000]],
        ['name' => 'Spor Ekipman', 'keywords' => ['spor ekipman', 'fitness ekipman'], 5 => null, 4 => ['revenue' => 62_000_000, 'orders' => 8_000]],
        ['name' => 'Çorap', 'keywords' => ['çorap', 'corap'], 5 => null, 4 => ['revenue' => 20_000_000, 'orders' => 60_000]],
        ['name' => 'Hobi', 'keywords' => ['hobi'], 5 => ['revenue' => 60_000_000, 'orders' => 11_000], 4 => ['revenue' => 18_000_000, 'orders' => 6_000]],
        ['name' => 'Kitap', 'keywords' => ['kitap'], 5 => ['revenue' => 95_000_000, 'orders' => 210_000], 4 => ['revenue' => 33_000_000, 'orders' => 50_000]],
        ['name' => 'Kırtasiye & Ofis', 'keywords' => ['kırtasiye', 'kirtasiye', 'ofis malzeme'], 5 => ['revenue' => 50_000_000, 'orders' => 150_000], 4 => ['revenue' => 17_500_000, 'orders' => 42_000]],
        ['name' => 'Banyo Yapı & Hırdavat', 'keywords' => ['banyo yapı', 'banyo yapi', 'hırdavat', 'hirdavat'], 5 => null, 4 => ['revenue' => 7_000_000, 'orders' => 15_000]],
        ['name' => 'Banyo', 'keywords' => ['banyo'], 5 => null, 4 => ['revenue' => null, 'orders' => 38_000]],
        ['name' => 'Ev Tekstili', 'keywords' => ['ev tekstili', 'nevresim', 'yatak örtüsü', 'yatak ortusu'], 5 => null, 4 => ['revenue' => 25_000_000, 'orders' => null]],
        ['name' => 'Bahçe & Elektrikli El Aletleri', 'keywords' => ['bahçe', 'bahce', 'elektrikli el aleti'], 5 => null, 4 => ['revenue' => 26_500_000, 'orders' => 10_000]],
        ['name' => 'Anne & Bebek Ürünleri', 'keywords' => ['anne bebek ürün', 'anne bebek urun', 'bebek ürün', 'bebek urun'], 5 => null, 4 => ['revenue' => 8_250_000, 'orders' => 7_000]],
        ['name' => 'Çocuk Gereç', 'keywords' => ['çocuk gereç', 'cocuk gerec', 'bebek arabası', 'bebek arabasi'], 5 => null, 4 => ['revenue' => 31_000_000, 'orders' => 10_000]],
        ['name' => 'Oyuncak', 'keywords' => ['oyuncak'], 5 => null, 4 => ['revenue' => 18_000_000, 'orders' => 21_000]],
        ['name' => 'Halı & Perde & Aydınlatma', 'keywords' => ['halı', 'hali', 'perde', 'aydınlatma', 'aydinlatma'], 5 => ['revenue' => 100_000_000, 'orders' => 45_000], 4 => ['revenue' => 25_000_000, 'orders' => 20_000]],
        ['name' => 'Ağır Mobilya', 'keywords' => ['ağır mobilya', 'agir mobilya', 'koltuk takımı', 'koltuk takimi', 'yatak odası', 'yatak odasi'], 5 => ['revenue' => 150_000_000, 'orders' => 22_000], 4 => ['revenue' => 32_500_000, 'orders' => 3_500]],
        ['name' => 'Ev Dekorasyon', 'keywords' => ['ev dekorasyon', 'dekoratif'], 5 => ['revenue' => 100_000_000, 'orders' => 100_000], 4 => ['revenue' => 15_000_000, 'orders' => 37_000]],
        ['name' => 'Ev Gereçleri', 'keywords' => ['ev gereç', 'ev gerec', 'mutfak gereç', 'mutfak gerec'], 5 => null, 4 => ['revenue' => 25_000_000, 'orders' => 50_000]],
        ['name' => 'Hafif Mobilya', 'keywords' => ['hafif mobilya', 'puf', 'tabure', 'sandalye'], 5 => ['revenue' => 150_000_000, 'orders' => 50_000], 4 => ['revenue' => 20_000_000, 'orders' => 15_000]],
        ['name' => 'Mobilya', 'keywords' => ['mobilya'], 5 => ['revenue' => 200_000_000, 'orders' => 50_000], 4 => ['revenue' => 46_000_000, 'orders' => 6_000]],
        ['name' => 'Süpürge & Ütü', 'keywords' => ['süpürge', 'supurge', 'ütü', 'utu'], 5 => ['revenue' => 80_000_000, 'orders' => 9_500], 4 => ['revenue' => 36_000_000, 'orders' => 4_500]],
        ['name' => 'Yiyecek & İçecek Hazırlama', 'keywords' => ['yiyecek hazırlama', 'icecek hazırlama', 'kahve makinesi', 'blender'], 5 => ['revenue' => 40_000_000, 'orders' => 10_000], 4 => ['revenue' => 25_000_000, 'orders' => 2_200]],
        ['name' => 'Beyaz Eşya & İklimlendirme', 'keywords' => ['beyaz eşya', 'beyaz esya', 'iklimlendirme', 'klima'], 5 => ['revenue' => 135_000_000, 'orders' => 4_000], 4 => ['revenue' => 31_000_000, 'orders' => 1_750]],
        ['name' => 'Telefon', 'keywords' => ['telefon', 'cep telefonu', 'akıllı telefon', 'akilli telefon'], 5 => ['revenue' => 65_000_000, 'orders' => 2_000], 4 => ['revenue' => 40_000_000, 'orders' => 1_500]],
        ['name' => 'Bilgisayar Grubu', 'keywords' => ['bilgisayar', 'notebook', 'laptop'], 5 => ['revenue' => 72_000_000, 'orders' => null], 4 => ['revenue' => 27_000_000, 'orders' => null]],
        ['name' => 'Tablet Grubu', 'keywords' => ['tablet'], 5 => ['revenue' => 28_000_000, 'orders' => null], 4 => ['revenue' => 20_000_000, 'orders' => null]],
        ['name' => 'Giyilebilir Teknoloji', 'keywords' => ['giyilebilir teknoloji', 'akıllı saat', 'akilli saat'], 5 => ['revenue' => 25_000_000, 'orders' => null], 4 => ['revenue' => 14_000_000, 'orders' => null]],
        ['name' => 'Hoparlör & Projeksiyon Sistemleri', 'keywords' => ['hoparlör', 'hoparlor', 'projeksiyon'], 5 => ['revenue' => 25_000_000, 'orders' => null], 4 => ['revenue' => 15_000_000, 'orders' => null]],
        ['name' => 'Oyun & Oyun Konsolları', 'keywords' => ['oyun konsol', 'playstation', 'xbox'], 5 => ['revenue' => 47_500_000, 'orders' => null], 4 => ['revenue' => 22_000_000, 'orders' => null]],
        ['name' => 'Foto & Kamera', 'keywords' => ['fotoğraf', 'fotograf', 'kamera'], 5 => ['revenue' => 45_000_000, 'orders' => null], 4 => ['revenue' => 20_000_000, 'orders' => null]],
        ['name' => 'Bileşenler', 'keywords' => ['bilgisayar bileşen', 'bilgisayar bilesen', 'ekran kartı', 'ekran karti', 'işlemci', 'islemci'], 5 => null, 4 => ['revenue' => 8_000_000, 'orders' => null]],
        ['name' => 'Kişisel Bakım Aletleri', 'keywords' => ['kişisel bakım aleti', 'kisisel bakim aleti', 'saç kurutma', 'sac kurutma', 'tıraş makinesi', 'tiras makinesi'], 5 => ['revenue' => 55_000_000, 'orders' => 15_000], 4 => ['revenue' => 20_000_000, 'orders' => 11_000]],
        ['name' => 'Robot Süpürge', 'keywords' => ['robot süpürge', 'robot supurge'], 5 => ['revenue' => 28_000_000, 'orders' => null], 4 => ['revenue' => 20_000_000, 'orders' => null]],
        ['name' => 'Televizyon', 'keywords' => ['televizyon', 'smart tv'], 5 => ['revenue' => 28_000_000, 'orders' => null], 4 => ['revenue' => 20_000_000, 'orders' => null]],
    ];

    private const EXCLUDED_STATUSES = [
        'cancelled', 'canceled', 'cancel', 'iptal', 'returned', 'return', 'iade', 'un-delivered', 'undelivered',
    ];

    /** @param array<string, mixed> $productData @return array<string, mixed> */
    public function resolve(int $userId, array $productData, ?TrendyolBoosterProduct $tracked = null): array
    {
        $explicitLevel = $this->levelNumber($productData['seller_level'] ?? null);
        if ($explicitLevel !== null) {
            return $this->result($explicitLevel, 'page', 95, 'active', null, null,
                'Satıcı seviyesi Trendyol sayfa verisinden doğrudan alındı.');
        }

        if (! Schema::hasTable('marketplace_stores') || ! Schema::hasTable('channel_order_items')) {
            return $this->unavailable();
        }

        $tracked?->loadMissing('listing.store');
        $sellerId = trim((string) ($productData['seller_id'] ?? ''));
        $store = $this->connectedStore($userId, $sellerId, $tracked);
        if (! $store) {
            return $this->unavailable($sellerId);
        }

        $categoryRule = $this->matchCategoryRule($this->contextText($productData, $tracked));
        $metrics = $this->metrics($store->id, $categoryRule);
        $firstPublishedAt = ChannelListing::query()
            ->where('store_id', $store->id)
            ->orderByRaw('COALESCE(published_at, created_at)')
            ->value(DB::raw('COALESCE(published_at, created_at)'));
        $firstPublishedAt ??= $store->created_at;
        $isNewSeller = $firstPublishedAt !== null && now()->diffInDays($firstPublishedAt, true) < 60;

        if ($isNewSeller) {
            $metrics['is_new_seller'] = true;

            return $this->result(1, 'connected_store_180d', 96, 'new_seller', $metrics, $categoryRule,
                'İlk ürün yüklemesinden sonraki 60 gün boyunca satıcı Seviye 1 kabul edilir.');
        }

        if ((int) $metrics['orders_30d'] === 0) {
            return $this->result(null, 'connected_store_180d', 96, 'inactive', $metrics, $categoryRule,
                'Son 30 günde net sipariş bulunmadığı için satıcı inaktif kabul edildi.');
        }

        $classification = $this->classify(
            (float) $metrics['revenue_180d'],
            (int) $metrics['orders_180d'],
            $categoryRule,
            (float) ($metrics['category_revenue_180d'] ?? 0),
            (int) ($metrics['category_orders_180d'] ?? 0),
            filter_var($productData['is_strategic_brand'] ?? false, FILTER_VALIDATE_BOOL),
            (float) ($productData['micro_export_revenue_180d'] ?? 0),
        );

        return $this->result(
            $classification['level'],
            'connected_store_180d',
            96,
            'active',
            $metrics,
            $categoryRule,
            $classification['reason'].' Seviye 4 ve 5 düşümündeki 30 günlük koruma, günlük seviye geçmişi oluştuğunda ayrıca uygulanır.'
        );
    }

    /**
     * @param  array<string, mixed>|null  $categoryRule
     * @return array{level: int, reason: string}
     */
    public function classify(
        float $revenue180d,
        int $orders180d,
        ?array $categoryRule = null,
        float $categoryRevenue180d = 0,
        int $categoryOrders180d = 0,
        bool $isStrategicBrand = false,
        float $microExportRevenue180d = 0,
    ): array {
        if ($isStrategicBrand) {
            return ['level' => 4, 'reason' => 'Stratejik marka istisnası uygulandı.'];
        }

        if ($microExportRevenue180d > 25_000_000) {
            return ['level' => 4, 'reason' => '180 günlük mikro ihracat net cirosu 25 milyon TL sınırını aştı.'];
        }

        if ($categoryRule !== null) {
            foreach ([5, 4] as $level) {
                $threshold = $categoryRule[$level] ?? null;
                if (is_array($threshold) && $this->meets($categoryRevenue180d, $categoryOrders180d, $threshold)) {
                    return ['level' => $level, 'reason' => $categoryRule['name'].' özel kategori baremi sağlandı.'];
                }
            }
        }

        foreach (self::GENERAL_THRESHOLDS as $level => $threshold) {
            if ($this->meets($revenue180d, $orders180d, $threshold)) {
                return ['level' => $level, 'reason' => 'Genel 180 günlük net ciro ve net sipariş baremi sağlandı.'];
            }
        }

        return ['level' => 1, 'reason' => 'Üst seviye baremleri sağlanmadı.'];
    }

    protected function connectedStore(int $userId, string $sellerId, ?TrendyolBoosterProduct $tracked): ?MarketplaceStore
    {
        if ($tracked?->listing?->store
            && (int) $tracked->listing->store->user_id === $userId
            && $tracked->listing->store->marketplace === 'trendyol'
            && ($sellerId === '' || (string) $tracked->listing->store->seller_id === $sellerId)) {
            return $tracked->listing->store;
        }

        if ($sellerId === '') {
            return null;
        }

        return MarketplaceStore::query()
            ->where('user_id', $userId)
            ->where('marketplace', 'trendyol')
            ->where('seller_id', $sellerId)
            ->first();
    }

    /** @param array<string, mixed>|null $categoryRule @return array<string, float|int|bool|null|string> */
    protected function metrics(int $storeId, ?array $categoryRule): array
    {
        $since180 = now()->subDays(180);
        $since30 = now()->subDays(30);
        $base180 = $this->netItemsQuery($storeId, $since180);
        $base30 = $this->netItemsQuery($storeId, $since30);
        $metrics = [
            'store_id' => $storeId,
            'revenue_180d' => round((float) (clone $base180)->sum(DB::raw($this->netRevenueSql())), 2),
            'orders_180d' => (int) (clone $base180)->distinct('channel_order_items.channel_order_id')->count('channel_order_items.channel_order_id'),
            'orders_30d' => (int) (clone $base30)->distinct('channel_order_items.channel_order_id')->count('channel_order_items.channel_order_id'),
            'category_revenue_180d' => null,
            'category_orders_180d' => null,
            'category_threshold_name' => $categoryRule['name'] ?? null,
            'is_new_seller' => false,
            'as_of' => now()->toIso8601String(),
        ];

        if ($categoryRule !== null) {
            $categoryQuery = $this->applyCategoryFilter(clone $base180, $categoryRule['keywords']);
            $metrics['category_revenue_180d'] = round((float) (clone $categoryQuery)->sum(DB::raw($this->netRevenueSql())), 2);
            $metrics['category_orders_180d'] = (int) (clone $categoryQuery)
                ->distinct('channel_order_items.channel_order_id')
                ->count('channel_order_items.channel_order_id');
        }

        return $metrics;
    }

    protected function netItemsQuery(int $storeId, mixed $since): Builder
    {
        return ChannelOrderItem::query()
            ->join('channel_orders', 'channel_orders.id', '=', 'channel_order_items.channel_order_id')
            ->leftJoin('mp_products', 'mp_products.id', '=', 'channel_order_items.mp_product_id')
            ->where('channel_order_items.store_id', $storeId)
            ->where('channel_orders.ordered_at', '>=', $since)
            ->whereNull('channel_orders.cancelled_at')
            ->whereNull('channel_orders.returned_at')
            ->whereNotIn(DB::raw('LOWER(COALESCE(channel_orders.order_status, \'\'))'), self::EXCLUDED_STATUSES)
            ->whereNotIn(DB::raw('LOWER(COALESCE(channel_order_items.line_status, \'\'))'), self::EXCLUDED_STATUSES);
    }

    /** @param array<int, string> $keywords */
    protected function applyCategoryFilter(Builder $query, array $keywords): Builder
    {
        $asciiKeywords = collect($keywords)
            ->map(fn (string $keyword): string => Str::lower(Str::ascii($keyword)))
            ->filter(fn (string $keyword): bool => mb_strlen($keyword) >= 3)
            ->unique()
            ->values();

        return $query->where(function (Builder $categoryQuery) use ($asciiKeywords): void {
            foreach ($asciiKeywords as $keyword) {
                $pattern = '%'.$keyword.'%';
                $categoryQuery->orWhereRaw(
                    "LOWER(CONCAT(COALESCE(mp_products.category_name, ''), ' ', COALESCE(mp_products.product_name, ''), ' ', COALESCE(channel_order_items.product_name, ''))) LIKE ?",
                    [$pattern]
                );
            }
        });
    }

    protected function netRevenueSql(): string
    {
        return 'COALESCE(channel_order_items.billable_amount, channel_order_items.gross_amount - COALESCE(channel_order_items.discount_amount, 0), channel_order_items.unit_price * channel_order_items.quantity, 0)';
    }

    /** @param array{revenue: ?float, orders: ?int} $threshold */
    protected function meets(float $revenue, int $orders, array $threshold): bool
    {
        $checks = [];

        if (($threshold['revenue'] ?? null) !== null && (float) $threshold['revenue'] > 0) {
            $checks[] = $revenue >= (float) $threshold['revenue'];
        }

        if (($threshold['orders'] ?? null) !== null && (int) $threshold['orders'] > 0) {
            $checks[] = $orders >= (int) $threshold['orders'];
        }

        return $checks !== [] && ! in_array(false, $checks, true);
    }

    /** @return array<string, mixed>|null */
    protected function matchCategoryRule(string $context): ?array
    {
        $context = Str::lower(Str::ascii($context));
        $best = null;
        $bestScore = 0;

        foreach (self::CATEGORY_THRESHOLDS as $rule) {
            foreach ($rule['keywords'] as $keyword) {
                $keyword = Str::lower(Str::ascii($keyword));
                if (! str_contains($context, $keyword)) {
                    continue;
                }

                $score = mb_strlen($keyword);
                if ($score > $bestScore) {
                    $best = $rule;
                    $bestScore = $score;
                }
            }
        }

        return $best;
    }

    protected function contextText(array $productData, ?TrendyolBoosterProduct $tracked): string
    {
        return trim(implode(' ', array_filter([
            $productData['category_name'] ?? null,
            $productData['title'] ?? null,
            $tracked?->category_name,
            $tracked?->title,
        ])));
    }

    protected function levelNumber(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        $level = (int) $value;

        return $level >= 1 && $level <= 5 ? $level : null;
    }

    /** @param array<string, mixed>|null $metrics @param array<string, mixed>|null $categoryRule @return array<string, mixed> */
    protected function result(
        ?int $level,
        string $source,
        float $confidence,
        string $status,
        ?array $metrics,
        ?array $categoryRule,
        string $note,
    ): array {
        return [
            'level' => $level,
            'source' => $source,
            'confidence' => $confidence,
            'status' => $status,
            'metrics' => $metrics,
            'matched_category_rule' => $categoryRule['name'] ?? null,
            'note' => $note,
        ];
    }

    /** @return array<string, mixed> */
    protected function unavailable(string $sellerId = ''): array
    {
        return $this->result(
            null,
            'unavailable',
            0,
            'unknown',
            $sellerId !== '' ? ['seller_id' => $sellerId] : null,
            null,
            'Dış satıcının 180 günlük net ciro ve net sipariş verisi herkese açık değildir. Seviye uydurulmadı; komisyon senaryoları gösterildi.'
        );
    }
}
