<?php

namespace App\Services\Marketplace;

use App\Models\TrendyolBoosterCommissionRate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class TrendyolBoosterCommissionRateService
{
    public function seedDefaults(int $userId): int
    {
        $created = 0;

        foreach ($this->defaultRows() as $row) {
            $model = TrendyolBoosterCommissionRate::query()->firstOrNew([
                'user_id' => $userId,
                'category_name' => $row['category_name'],
                'sub_category_name' => $row['sub_category_name'],
                'product_group' => $row['product_group'],
            ]);

            if (! $model->exists) {
                $created++;
            }

            $model->forceFill($row + [
                'marketplace' => 'trendyol',
                'source' => 'ZOLM örnek set',
                'imported_at' => now(),
            ])->save();
        }

        return $created;
    }

    /**
     * PDF parser'dan gelen satırı DB'ye yazar veya günceller.
     *
     * @param int $userId
     * @param array<string, mixed> $row
     * @return void
     */
    public function upsertFromParser(int $userId, array $row): void
    {
        $model = TrendyolBoosterCommissionRate::query()->firstOrNew([
            'user_id'           => $userId,
            'category_name'     => $row['category_name'],
            'sub_category_name' => $row['sub_category_name'],
            'product_group'     => $row['product_group'],
        ]);

        $model->forceFill([
            'maturity_days'   => $row['maturity_days'],
            'commission_rate' => $row['commission_rate'],
            'level_5_rate'    => $row['level_5_rate'] ?? null,
            'level_4_rate'    => $row['level_4_rate'] ?? null,
            'level_3_rate'    => $row['level_3_rate'] ?? null,
            'level_2_rate'    => $row['level_2_rate'] ?? null,
            'level_1_rate'    => $row['level_1_rate'] ?? null,
            'marketplace'     => 'trendyol',
            'source'          => 'PDF Import',
            'imported_at'     => now(),
        ])->save();
    }

    /**
     * @return array{total: int, highest: float, last_update: ?string, rows: Collection<int, TrendyolBoosterCommissionRate>, categories: array<int,string>}
     */
    public function dashboard(
        int    $userId,
        string $search = '',
        string $sort = 'commission_rate',
        string $direction = 'desc',
        string $categoryFilter = '',
        string $rateRange = '',
        string $maturityFilter = ''
    ): array {
        $allowedSorts = ['category_name', 'commission_rate', 'maturity_days', 'updated_at'];
        $sort      = in_array($sort, $allowedSorts, true) ? $sort : 'commission_rate';
        $direction = $direction === 'asc' ? 'asc' : 'desc';

        $base = TrendyolBoosterCommissionRate::query()
            ->where(function (Builder $query) use ($userId): void {
                $query->where('user_id', $userId)->orWhereNull('user_id');
            });

        $rows = (clone $base)
            ->when(trim($search) !== '', function (Builder $q) use ($search): void {
                $q->where(function (Builder $s) use ($search): void {
                    $s->where('category_name',    'like', '%'.trim($search).'%')
                      ->orWhere('sub_category_name', 'like', '%'.trim($search).'%')
                      ->orWhere('product_group',     'like', '%'.trim($search).'%');
                });
            })
            ->when($categoryFilter !== '', function (Builder $q) use ($categoryFilter): void {
                $q->where('category_name', $categoryFilter);
            })
            ->when($rateRange !== '', function (Builder $q) use ($rateRange): void {
                match ($rateRange) {
                    'high'   => $q->where('commission_rate', '>=', 25),
                    'mid'    => $q->whereBetween('commission_rate', [15, 24.99]),
                    'low'    => $q->where('commission_rate', '<', 15),
                    default  => null,
                };
            })
            ->when($maturityFilter !== '', function (Builder $q) use ($maturityFilter): void {
                $q->where('maturity_days', (int) $maturityFilter);
            })
            ->orderBy($sort, $direction)
            ->limit(200)
            ->get();

        $last = (clone $base)->latest('updated_at')->first();

        return [
            'total'      => (clone $base)->count(),
            'highest'    => round((float) ((clone $base)->max('commission_rate') ?? 0), 2),
            'last_update'=> $last?->updated_at?->format('d.m.Y'),
            'rows'       => $rows,
            'categories' => $this->categories($userId),
        ];
    }

    /**
     * Mevcut kategorilerin sıralı listesini döner.
     *
     * @return array<int, string>
     */
    public function categories(int $userId): array
    {
        return TrendyolBoosterCommissionRate::query()
            ->where(function (Builder $q) use ($userId): void {
                $q->where('user_id', $userId)->orWhereNull('user_id');
            })
            ->distinct()
            ->orderBy('category_name')
            ->pluck('category_name')
            ->toArray();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function defaultRows(): array
    {
        // Trendyol resmi komisyon tablosu — güncel_trendyol_komisyon_oranlari.pdf
        return [
            // ─── ELEKTRONİK ────────────────────────────────────────────────────
            ['category_name' => 'Elektronik', 'sub_category_name' => 'Elektronik Aksesuarlar', 'product_group' => 'Kapak & Kılıf, Ekran Koruyucu Film', 'maturity_days' => 21, 'commission_rate' => 29.0, 'level_5_rate' => 20.5, 'level_4_rate' => 22.5, 'level_3_rate' => 28.0],
            ['category_name' => 'Elektronik', 'sub_category_name' => 'Elektronik Aksesuarlar', 'product_group' => 'Telefon Bataryası, Telefon Ekranı, Telefon Kamerası', 'maturity_days' => 21, 'commission_rate' => 29.0, 'level_5_rate' => 24.0, 'level_4_rate' => 24.0, 'level_3_rate' => null],
            ['category_name' => 'Elektronik', 'sub_category_name' => 'Elektronik Aksesuarlar', 'product_group' => 'Kamera Lens Koruyucu, Kulaklık Aksesuarları', 'maturity_days' => 21, 'commission_rate' => 28.0, 'level_5_rate' => 22.0, 'level_4_rate' => 22.0, 'level_3_rate' => null],
            ['category_name' => 'Elektronik', 'sub_category_name' => 'Elektronik Aksesuarlar', 'product_group' => 'Diğer Elektronik Aksesuarlar', 'maturity_days' => 21, 'commission_rate' => 29.0, 'level_5_rate' => 22.0, 'level_4_rate' => 24.0, 'level_3_rate' => null],
            ['category_name' => 'Elektronik', 'sub_category_name' => 'Bilgisayar', 'product_group' => 'Laptop, Masaüstü Bilgisayar', 'maturity_days' => 21, 'commission_rate' => 4.0, 'level_5_rate' => 3.0, 'level_4_rate' => 3.0, 'level_3_rate' => null],
            ['category_name' => 'Elektronik', 'sub_category_name' => 'Bilgisayar', 'product_group' => 'Monitör', 'maturity_days' => 21, 'commission_rate' => 6.0, 'level_5_rate' => 4.0, 'level_4_rate' => 5.0, 'level_3_rate' => null],
            ['category_name' => 'Elektronik', 'sub_category_name' => 'Bilgisayar', 'product_group' => 'Tablet, E-reader', 'maturity_days' => 21, 'commission_rate' => 5.0, 'level_5_rate' => 3.0, 'level_4_rate' => 4.0, 'level_3_rate' => null],
            ['category_name' => 'Elektronik', 'sub_category_name' => 'Bilgisayar', 'product_group' => 'Bellek & Depolama (SSD, HDD, USB)', 'maturity_days' => 21, 'commission_rate' => 8.0, 'level_5_rate' => 5.0, 'level_4_rate' => 6.0, 'level_3_rate' => null],
            ['category_name' => 'Elektronik', 'sub_category_name' => 'Bilgisayar', 'product_group' => 'Diğer Bilgisayar Ürünleri', 'maturity_days' => 21, 'commission_rate' => 14.0, 'level_5_rate' => 10.0, 'level_4_rate' => 12.0, 'level_3_rate' => null],
            ['category_name' => 'Elektronik', 'sub_category_name' => 'Telefon', 'product_group' => 'Cep Telefonu', 'maturity_days' => 21, 'commission_rate' => 3.0, 'level_5_rate' => 1.5, 'level_4_rate' => 2.0, 'level_3_rate' => null],
            ['category_name' => 'Elektronik', 'sub_category_name' => 'Telefon', 'product_group' => 'Akıllı Saat & Tracker', 'maturity_days' => 21, 'commission_rate' => 9.0, 'level_5_rate' => 6.0, 'level_4_rate' => 7.0, 'level_3_rate' => null],
            ['category_name' => 'Elektronik', 'sub_category_name' => 'Ses Sistemleri & Kulaklık', 'product_group' => 'Kulaklık', 'maturity_days' => 21, 'commission_rate' => 19.0, 'level_5_rate' => 14.0, 'level_4_rate' => 16.0, 'level_3_rate' => null],
            ['category_name' => 'Elektronik', 'sub_category_name' => 'Ses Sistemleri & Kulaklık', 'product_group' => 'Hoparlör, Soundbar, Ev Sinema', 'maturity_days' => 21, 'commission_rate' => 11.0, 'level_5_rate' => 8.0, 'level_4_rate' => 9.0, 'level_3_rate' => null],
            ['category_name' => 'Elektronik', 'sub_category_name' => 'Fotoğraf & Kamera', 'product_group' => 'Fotoğraf Makinesi, Objektif', 'maturity_days' => 21, 'commission_rate' => 5.0, 'level_5_rate' => 3.0, 'level_4_rate' => 4.0, 'level_3_rate' => null],
            ['category_name' => 'Elektronik', 'sub_category_name' => 'Fotoğraf & Kamera', 'product_group' => 'Drone', 'maturity_days' => 21, 'commission_rate' => 7.0, 'level_5_rate' => 4.0, 'level_4_rate' => 5.0, 'level_3_rate' => null],
            ['category_name' => 'Elektronik', 'sub_category_name' => 'TV & Görüntü', 'product_group' => 'Televizyon', 'maturity_days' => 21, 'commission_rate' => 4.0, 'level_5_rate' => 2.5, 'level_4_rate' => 3.0, 'level_3_rate' => null],
            ['category_name' => 'Elektronik', 'sub_category_name' => 'TV & Görüntü', 'product_group' => 'Projeksiyon', 'maturity_days' => 21, 'commission_rate' => 6.0, 'level_5_rate' => 4.0, 'level_4_rate' => 5.0, 'level_3_rate' => null],
            ['category_name' => 'Elektronik', 'sub_category_name' => 'Oyun & Konsol', 'product_group' => 'Oyun Konsolu', 'maturity_days' => 21, 'commission_rate' => 4.0, 'level_5_rate' => 2.5, 'level_4_rate' => 3.0, 'level_3_rate' => null],
            ['category_name' => 'Elektronik', 'sub_category_name' => 'Oyun & Konsol', 'product_group' => 'Oyun Aksesuarları, Kontrolcüler', 'maturity_days' => 21, 'commission_rate' => 15.0, 'level_5_rate' => 11.0, 'level_4_rate' => 13.0, 'level_3_rate' => null],
            ['category_name' => 'Elektronik', 'sub_category_name' => 'Güç & Enerji', 'product_group' => 'Powerbank', 'maturity_days' => 21, 'commission_rate' => 20.0, 'level_5_rate' => 15.0, 'level_4_rate' => 17.0, 'level_3_rate' => null],
            ['category_name' => 'Elektronik', 'sub_category_name' => 'Güç & Enerji', 'product_group' => 'Şarj Cihazı, Kablo', 'maturity_days' => 21, 'commission_rate' => 22.0, 'level_5_rate' => 16.0, 'level_4_rate' => 18.0, 'level_3_rate' => null],

            // ─── GİYİM ─────────────────────────────────────────────────────────
            ['category_name' => 'Giyim', 'sub_category_name' => 'Kadın Giyim', 'product_group' => 'Elbise, Bluz, Gömlek', 'maturity_days' => 14, 'commission_rate' => 26.0, 'level_5_rate' => 18.0, 'level_4_rate' => 22.0, 'level_3_rate' => 24.0],
            ['category_name' => 'Giyim', 'sub_category_name' => 'Kadın Giyim', 'product_group' => 'Pantolon, Şort, Etek', 'maturity_days' => 14, 'commission_rate' => 26.0, 'level_5_rate' => 18.0, 'level_4_rate' => 22.0, 'level_3_rate' => 24.0],
            ['category_name' => 'Giyim', 'sub_category_name' => 'Kadın Giyim', 'product_group' => 'Mont, Kaban, Ceket', 'maturity_days' => 14, 'commission_rate' => 24.0, 'level_5_rate' => 16.0, 'level_4_rate' => 20.0, 'level_3_rate' => 22.0],
            ['category_name' => 'Giyim', 'sub_category_name' => 'Erkek Giyim', 'product_group' => 'Gömlek, Polo, T-Shirt', 'maturity_days' => 14, 'commission_rate' => 26.0, 'level_5_rate' => 18.0, 'level_4_rate' => 22.0, 'level_3_rate' => 24.0],
            ['category_name' => 'Giyim', 'sub_category_name' => 'Erkek Giyim', 'product_group' => 'Pantolon, Jean, Şort', 'maturity_days' => 14, 'commission_rate' => 26.0, 'level_5_rate' => 18.0, 'level_4_rate' => 22.0, 'level_3_rate' => 24.0],
            ['category_name' => 'Giyim', 'sub_category_name' => 'Erkek Giyim', 'product_group' => 'Mont, Kaban, Trençkot', 'maturity_days' => 14, 'commission_rate' => 24.0, 'level_5_rate' => 16.0, 'level_4_rate' => 20.0, 'level_3_rate' => 22.0],
            ['category_name' => 'Giyim', 'sub_category_name' => 'Spor Giyim', 'product_group' => 'Eşofman, Tayt, Spor Şort', 'maturity_days' => 14, 'commission_rate' => 26.0, 'level_5_rate' => 18.0, 'level_4_rate' => 22.0, 'level_3_rate' => 24.0],
            ['category_name' => 'Giyim', 'sub_category_name' => 'İç Giyim & Çorap', 'product_group' => 'İç Çamaşırı, Pijama', 'maturity_days' => 14, 'commission_rate' => 28.0, 'level_5_rate' => 20.0, 'level_4_rate' => 24.0, 'level_3_rate' => null],
            ['category_name' => 'Giyim', 'sub_category_name' => 'İç Giyim & Çorap', 'product_group' => 'Çorap', 'maturity_days' => 14, 'commission_rate' => 28.0, 'level_5_rate' => 20.0, 'level_4_rate' => 24.0, 'level_3_rate' => null],

            // ─── AYAKKABI ───────────────────────────────────────────────────────
            ['category_name' => 'Ayakkabı', 'sub_category_name' => 'Kadın Ayakkabı', 'product_group' => 'Topuklu, Düz, Sneaker', 'maturity_days' => 14, 'commission_rate' => 27.0, 'level_5_rate' => 19.0, 'level_4_rate' => 23.0, 'level_3_rate' => 25.0],
            ['category_name' => 'Ayakkabı', 'sub_category_name' => 'Erkek Ayakkabı', 'product_group' => 'Klasik, Spor, Bot', 'maturity_days' => 14, 'commission_rate' => 27.0, 'level_5_rate' => 19.0, 'level_4_rate' => 23.0, 'level_3_rate' => 25.0],
            ['category_name' => 'Ayakkabı', 'sub_category_name' => 'Spor Ayakkabı', 'product_group' => 'Koşu, Fitness, Basketbol', 'maturity_days' => 14, 'commission_rate' => 25.0, 'level_5_rate' => 17.0, 'level_4_rate' => 21.0, 'level_3_rate' => 23.0],
            ['category_name' => 'Ayakkabı', 'sub_category_name' => 'Terlik & Sandalet', 'product_group' => 'Ev Terliği, Sandalet', 'maturity_days' => 14, 'commission_rate' => 28.0, 'level_5_rate' => 20.0, 'level_4_rate' => 24.0, 'level_3_rate' => null],

            // ─── ÇANTA & AKSESUAR ──────────────────────────────────────────────
            ['category_name' => 'Çanta', 'sub_category_name' => 'Kadın Çanta', 'product_group' => 'El Çantası, Omuz Çantası', 'maturity_days' => 14, 'commission_rate' => 27.0, 'level_5_rate' => 19.0, 'level_4_rate' => 23.0, 'level_3_rate' => 25.0],
            ['category_name' => 'Çanta', 'sub_category_name' => 'Sırt & Valiz', 'product_group' => 'Sırt Çantası, Valiz', 'maturity_days' => 14, 'commission_rate' => 24.0, 'level_5_rate' => 16.0, 'level_4_rate' => 20.0, 'level_3_rate' => 22.0],
            ['category_name' => 'Aksesuar', 'sub_category_name' => 'Takı & Mücevher', 'product_group' => 'Kolye, Bileklik, Küpe (Çelik, Gümüş)', 'maturity_days' => 14, 'commission_rate' => 29.0, 'level_5_rate' => 21.0, 'level_4_rate' => 25.0, 'level_3_rate' => null],
            ['category_name' => 'Aksesuar', 'sub_category_name' => 'Güneş Gözlüğü', 'product_group' => 'Güneş Gözlüğü', 'maturity_days' => 14, 'commission_rate' => 28.0, 'level_5_rate' => 20.0, 'level_4_rate' => 24.0, 'level_3_rate' => null],
            ['category_name' => 'Aksesuar', 'sub_category_name' => 'Saat', 'product_group' => 'Kol Saati (Analog/Dijital)', 'maturity_days' => 14, 'commission_rate' => 18.0, 'level_5_rate' => 13.0, 'level_4_rate' => 15.0, 'level_3_rate' => 17.0],

            // ─── KOZMETİK & KİŞİSEL BAKIM ─────────────────────────────────────
            ['category_name' => 'Kozmetik', 'sub_category_name' => 'Cilt Bakımı', 'product_group' => 'Yüz Kremi, Serum, Tonik', 'maturity_days' => 21, 'commission_rate' => 22.0, 'level_5_rate' => 16.0, 'level_4_rate' => 18.0, 'level_3_rate' => 20.0],
            ['category_name' => 'Kozmetik', 'sub_category_name' => 'Cilt Bakımı', 'product_group' => 'Güneş Kremi', 'maturity_days' => 21, 'commission_rate' => 20.0, 'level_5_rate' => 15.0, 'level_4_rate' => 17.0, 'level_3_rate' => 19.0],
            ['category_name' => 'Kozmetik', 'sub_category_name' => 'Makyaj', 'product_group' => 'Fondöten, Allık, Pudra', 'maturity_days' => 21, 'commission_rate' => 24.0, 'level_5_rate' => 17.0, 'level_4_rate' => 20.0, 'level_3_rate' => null],
            ['category_name' => 'Kozmetik', 'sub_category_name' => 'Makyaj', 'product_group' => 'Ruj, Dudak Ürünleri', 'maturity_days' => 21, 'commission_rate' => 26.0, 'level_5_rate' => 18.0, 'level_4_rate' => 22.0, 'level_3_rate' => null],
            ['category_name' => 'Kozmetik', 'sub_category_name' => 'Parfüm', 'product_group' => 'Kadın Parfümü, Erkek Parfümü', 'maturity_days' => 21, 'commission_rate' => 18.0, 'level_5_rate' => 13.0, 'level_4_rate' => 15.0, 'level_3_rate' => 17.0],
            ['category_name' => 'Kozmetik', 'sub_category_name' => 'Saç Bakımı', 'product_group' => 'Şampuan, Saç Kremi, Serum', 'maturity_days' => 21, 'commission_rate' => 20.0, 'level_5_rate' => 15.0, 'level_4_rate' => 17.0, 'level_3_rate' => 19.0],
            ['category_name' => 'Kozmetik', 'sub_category_name' => 'Kişisel Bakım', 'product_group' => 'Diş Fırçası, Tıraş Ürünleri', 'maturity_days' => 21, 'commission_rate' => 20.0, 'level_5_rate' => 15.0, 'level_4_rate' => 17.0, 'level_3_rate' => 19.0],

            // ─── EV & YAŞAM ────────────────────────────────────────────────────
            ['category_name' => 'Ev ve Yaşam', 'sub_category_name' => 'Mobilya', 'product_group' => 'Koltuk, Kanepe', 'maturity_days' => 21, 'commission_rate' => 16.0, 'level_5_rate' => 12.0, 'level_4_rate' => 14.0, 'level_3_rate' => 15.0],
            ['category_name' => 'Ev ve Yaşam', 'sub_category_name' => 'Mobilya', 'product_group' => 'Masa, Sandalye', 'maturity_days' => 21, 'commission_rate' => 16.0, 'level_5_rate' => 12.0, 'level_4_rate' => 14.0, 'level_3_rate' => 15.0],
            ['category_name' => 'Ev ve Yaşam', 'sub_category_name' => 'Mobilya', 'product_group' => 'Puf & Minder', 'maturity_days' => 21, 'commission_rate' => 18.0, 'level_5_rate' => 14.0, 'level_4_rate' => 16.0, 'level_3_rate' => 17.0],
            ['category_name' => 'Ev ve Yaşam', 'sub_category_name' => 'Mobilya', 'product_group' => 'Yatak, Baza, Başlık', 'maturity_days' => 21, 'commission_rate' => 14.0, 'level_5_rate' => 10.0, 'level_4_rate' => 12.0, 'level_3_rate' => 13.0],
            ['category_name' => 'Ev ve Yaşam', 'sub_category_name' => 'Dekorasyon', 'product_group' => 'Tablo, Çerçeve, Heykel', 'maturity_days' => 21, 'commission_rate' => 22.0, 'level_5_rate' => 16.0, 'level_4_rate' => 19.0, 'level_3_rate' => null],
            ['category_name' => 'Ev ve Yaşam', 'sub_category_name' => 'Dekorasyon', 'product_group' => 'Mumluk, Vazo, Saksı', 'maturity_days' => 21, 'commission_rate' => 22.0, 'level_5_rate' => 16.0, 'level_4_rate' => 19.0, 'level_3_rate' => null],
            ['category_name' => 'Ev ve Yaşam', 'sub_category_name' => 'Aydınlatma', 'product_group' => 'Avize, Abajur, LED Lamba', 'maturity_days' => 21, 'commission_rate' => 20.0, 'level_5_rate' => 15.0, 'level_4_rate' => 17.0, 'level_3_rate' => null],
            ['category_name' => 'Ev ve Yaşam', 'sub_category_name' => 'Mutfak', 'product_group' => 'Pişirme Setleri, Tencere', 'maturity_days' => 21, 'commission_rate' => 19.0, 'level_5_rate' => 14.0, 'level_4_rate' => 16.0, 'level_3_rate' => 18.0],
            ['category_name' => 'Ev ve Yaşam', 'sub_category_name' => 'Mutfak', 'product_group' => 'Bıçak, Tahta, Mutfak Aletleri', 'maturity_days' => 21, 'commission_rate' => 22.0, 'level_5_rate' => 16.0, 'level_4_rate' => 19.0, 'level_3_rate' => null],
            ['category_name' => 'Ev ve Yaşam', 'sub_category_name' => 'Tekstil', 'product_group' => 'Nevresim Takımı, Çarşaf', 'maturity_days' => 21, 'commission_rate' => 20.0, 'level_5_rate' => 15.0, 'level_4_rate' => 17.0, 'level_3_rate' => 19.0],
            ['category_name' => 'Ev ve Yaşam', 'sub_category_name' => 'Tekstil', 'product_group' => 'Havlu, Bornoz', 'maturity_days' => 21, 'commission_rate' => 22.0, 'level_5_rate' => 16.0, 'level_4_rate' => 19.0, 'level_3_rate' => null],
            ['category_name' => 'Ev ve Yaşam', 'sub_category_name' => 'Tekstil', 'product_group' => 'Halı, Kilim, Paspas', 'maturity_days' => 21, 'commission_rate' => 18.0, 'level_5_rate' => 13.0, 'level_4_rate' => 15.0, 'level_3_rate' => 17.0],
            ['category_name' => 'Ev ve Yaşam', 'sub_category_name' => 'Temizlik', 'product_group' => 'Temizlik Ürünleri, Deterjan', 'maturity_days' => 21, 'commission_rate' => 16.0, 'level_5_rate' => 12.0, 'level_4_rate' => 14.0, 'level_3_rate' => null],

            // ─── ANNE & ÇOCUK ─────────────────────────────────────────────────
            ['category_name' => 'Anne & Çocuk', 'sub_category_name' => 'Oyuncak', 'product_group' => 'Oyuncak Araçlar', 'maturity_days' => 21, 'commission_rate' => 16.0, 'level_5_rate' => 12.0, 'level_4_rate' => 14.0, 'level_3_rate' => 15.0],
            ['category_name' => 'Anne & Çocuk', 'sub_category_name' => 'Oyuncak', 'product_group' => 'Yapboz, Bulmaca, Kutu Oyunları', 'maturity_days' => 21, 'commission_rate' => 20.0, 'level_5_rate' => 15.0, 'level_4_rate' => 17.0, 'level_3_rate' => null],
            ['category_name' => 'Anne & Çocuk', 'sub_category_name' => 'Oyuncak', 'product_group' => 'Bebek Oyuncakları, Peluş', 'maturity_days' => 21, 'commission_rate' => 20.0, 'level_5_rate' => 15.0, 'level_4_rate' => 17.0, 'level_3_rate' => null],
            ['category_name' => 'Anne & Çocuk', 'sub_category_name' => 'Bebek & Çocuk Giyim', 'product_group' => 'Bebek Takım, Tulum', 'maturity_days' => 14, 'commission_rate' => 24.0, 'level_5_rate' => 17.0, 'level_4_rate' => 21.0, 'level_3_rate' => null],
            ['category_name' => 'Anne & Çocuk', 'sub_category_name' => 'Bebek Bakım', 'product_group' => 'Bez, Islak Mendil, Krem', 'maturity_days' => 21, 'commission_rate' => 14.0, 'level_5_rate' => 10.0, 'level_4_rate' => 12.0, 'level_3_rate' => null],
            ['category_name' => 'Anne & Çocuk', 'sub_category_name' => 'Araba Koltuğu & Puset', 'product_group' => 'Bebek Arabası, Puset', 'maturity_days' => 21, 'commission_rate' => 12.0, 'level_5_rate' => 8.0, 'level_4_rate' => 10.0, 'level_3_rate' => null],
            ['category_name' => 'Anne & Çocuk', 'sub_category_name' => 'Araba Koltuğu & Puset', 'product_group' => 'Araba Koltuğu', 'maturity_days' => 21, 'commission_rate' => 12.0, 'level_5_rate' => 8.0, 'level_4_rate' => 10.0, 'level_3_rate' => null],

            // ─── SPOR & OUTDOORspecial ─────────────────────────────────────────
            ['category_name' => 'Spor & Outdoor', 'sub_category_name' => 'Fitness & Kondisyon', 'product_group' => 'Dambıl, Kettlebell, Bant', 'maturity_days' => 21, 'commission_rate' => 20.0, 'level_5_rate' => 14.0, 'level_4_rate' => 17.0, 'level_3_rate' => null],
            ['category_name' => 'Spor & Outdoor', 'sub_category_name' => 'Fitness & Kondisyon', 'product_group' => 'Bisiklet (Yol, Dağ, Elektrikli)', 'maturity_days' => 21, 'commission_rate' => 6.0, 'level_5_rate' => 4.0, 'level_4_rate' => 5.0, 'level_3_rate' => null],
            ['category_name' => 'Spor & Outdoor', 'sub_category_name' => 'Bisiklet Aksesuarları', 'product_group' => 'Kask, Kilidi, Işık', 'maturity_days' => 21, 'commission_rate' => 20.0, 'level_5_rate' => 14.0, 'level_4_rate' => 17.0, 'level_3_rate' => null],
            ['category_name' => 'Spor & Outdoor', 'sub_category_name' => 'Kamp & Outdoor', 'product_group' => 'Çadır, Uyku Tulumu', 'maturity_days' => 21, 'commission_rate' => 18.0, 'level_5_rate' => 13.0, 'level_4_rate' => 15.0, 'level_3_rate' => null],
            ['category_name' => 'Spor & Outdoor', 'sub_category_name' => 'Top Sporları', 'product_group' => 'Futbol Topu, Basketbol Potası', 'maturity_days' => 21, 'commission_rate' => 18.0, 'level_5_rate' => 13.0, 'level_4_rate' => 15.0, 'level_3_rate' => null],

            // ─── KİTAP, MÜZİK & HOBY ─────────────────────────────────────────
            ['category_name' => 'Kitap', 'sub_category_name' => 'Roman & Edebiyat', 'product_group' => 'Roman, Hikâye, Şiir', 'maturity_days' => 14, 'commission_rate' => 12.0, 'level_5_rate' => 8.0, 'level_4_rate' => 10.0, 'level_3_rate' => null],
            ['category_name' => 'Kitap', 'sub_category_name' => 'Kişisel Gelişim & Bilim', 'product_group' => 'Kişisel Gelişim, Psikoloji', 'maturity_days' => 14, 'commission_rate' => 12.0, 'level_5_rate' => 8.0, 'level_4_rate' => 10.0, 'level_3_rate' => null],
            ['category_name' => 'Müzik & Enstrüman', 'sub_category_name' => 'Enstrüman', 'product_group' => 'Gitar, Bas, Ukulele', 'maturity_days' => 21, 'commission_rate' => 16.0, 'level_5_rate' => 12.0, 'level_4_rate' => 14.0, 'level_3_rate' => null],
            ['category_name' => 'Hobi & Sanat', 'sub_category_name' => 'Sanat Malzemeleri', 'product_group' => 'Boya, Fırça, Tuval', 'maturity_days' => 21, 'commission_rate' => 20.0, 'level_5_rate' => 14.0, 'level_4_rate' => 17.0, 'level_3_rate' => null],

            // ─── OTOMOTİV ─────────────────────────────────────────────────────
            ['category_name' => 'Otomotiv', 'sub_category_name' => 'Aksesuar & Dış', 'product_group' => 'Oto Koltuk Kılıfı, Paspas', 'maturity_days' => 21, 'commission_rate' => 20.0, 'level_5_rate' => 15.0, 'level_4_rate' => 17.0, 'level_3_rate' => null],
            ['category_name' => 'Otomotiv', 'sub_category_name' => 'Lastik & Jant', 'product_group' => 'Lastik', 'maturity_days' => 21, 'commission_rate' => 5.0, 'level_5_rate' => 3.0, 'level_4_rate' => 4.0, 'level_3_rate' => null],
            ['category_name' => 'Otomotiv', 'sub_category_name' => 'Araç Bakım', 'product_group' => 'Oto Yağı, Araç Kimyasalı', 'maturity_days' => 21, 'commission_rate' => 12.0, 'level_5_rate' => 9.0, 'level_4_rate' => 10.0, 'level_3_rate' => null],
            ['category_name' => 'Otomotiv', 'sub_category_name' => 'Navigasyon & Multimedya', 'product_group' => 'GPS, Araç Kamerası, OBD', 'maturity_days' => 21, 'commission_rate' => 16.0, 'level_5_rate' => 12.0, 'level_4_rate' => 14.0, 'level_3_rate' => null],

            // ─── BAHÇE & YAPIM ─────────────────────────────────────────────────
            ['category_name' => 'Bahçe & Yapı Market', 'sub_category_name' => 'Bahçe Aletleri', 'product_group' => 'Çim Biçme Makinesi, Budama', 'maturity_days' => 21, 'commission_rate' => 14.0, 'level_5_rate' => 10.0, 'level_4_rate' => 12.0, 'level_3_rate' => null],
            ['category_name' => 'Bahçe & Yapı Market', 'sub_category_name' => 'El Aletleri', 'product_group' => 'Matkap, Testere, Tornavida', 'maturity_days' => 21, 'commission_rate' => 14.0, 'level_5_rate' => 10.0, 'level_4_rate' => 12.0, 'level_3_rate' => null],
            ['category_name' => 'Bahçe & Yapı Market', 'sub_category_name' => 'Boya & Yapı', 'product_group' => 'Duvar Boyası, İzolasyon', 'maturity_days' => 21, 'commission_rate' => 10.0, 'level_5_rate' => 7.0, 'level_4_rate' => 8.0, 'level_3_rate' => null],

            // ─── SÜPERMARKET ──────────────────────────────────────────────────
            ['category_name' => 'Süpermarket', 'sub_category_name' => 'Gıda', 'product_group' => 'Kahve, Çay, Kuruyemiş', 'maturity_days' => 14, 'commission_rate' => 10.0, 'level_5_rate' => 7.0, 'level_4_rate' => 8.0, 'level_3_rate' => null],
            ['category_name' => 'Süpermarket', 'sub_category_name' => 'Temizlik & Hijyen', 'product_group' => 'Çamaşır Suyu, Deterjan', 'maturity_days' => 14, 'commission_rate' => 10.0, 'level_5_rate' => 7.0, 'level_4_rate' => 8.0, 'level_3_rate' => null],
            ['category_name' => 'Süpermarket', 'sub_category_name' => 'Evcil Hayvan', 'product_group' => 'Kedi Maması, Köpek Maması', 'maturity_days' => 14, 'commission_rate' => 10.0, 'level_5_rate' => 7.0, 'level_4_rate' => 8.0, 'level_3_rate' => null],

            // ─── KÜÇÜK EV ALETLERİ ─────────────────────────────────────────────
            ['category_name' => 'Ev Aletleri', 'sub_category_name' => 'Küçük Ev Aletleri', 'product_group' => 'Blender, Tost Makinesi, Kahve', 'maturity_days' => 21, 'commission_rate' => 12.0, 'level_5_rate' => 8.0, 'level_4_rate' => 10.0, 'level_3_rate' => null],
            ['category_name' => 'Ev Aletleri', 'sub_category_name' => 'Büyük Ev Aletleri', 'product_group' => 'Buzdolabı, Çamaşır Makinesi', 'maturity_days' => 21, 'commission_rate' => 4.0, 'level_5_rate' => 2.5, 'level_4_rate' => 3.0, 'level_3_rate' => null],
            ['category_name' => 'Ev Aletleri', 'sub_category_name' => 'Büyük Ev Aletleri', 'product_group' => 'Klima', 'maturity_days' => 21, 'commission_rate' => 5.0, 'level_5_rate' => 3.0, 'level_4_rate' => 4.0, 'level_3_rate' => null],
            ['category_name' => 'Ev Aletleri', 'sub_category_name' => 'Temizlik Cihazları', 'product_group' => 'Süpürge (Toz, Robot, Islak-Kuru)', 'maturity_days' => 21, 'commission_rate' => 10.0, 'level_5_rate' => 7.0, 'level_4_rate' => 8.0, 'level_3_rate' => null],
        ];
    }
}
