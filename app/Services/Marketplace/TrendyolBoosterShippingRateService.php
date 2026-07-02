<?php

namespace App\Services\Marketplace;

use App\Models\TrendyolBoosterShippingRate;
use App\Services\MpSettingsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class TrendyolBoosterShippingRateService
{
    /**
     * Barem destek oranları sabit olarak (kullanıcının ilettiği güncel tabloya göre) tanımlanır.
     */
    public const BAREM_RATES = [
        'fast_delivery' => [
            '0_199' => [
                'TEX' => 34.16, 'PTT' => 34.16, 'Aras' => 42.91, 'Sürat' => 48.74, 'Kolay Gelsin' => 51.24, 'DHL' => 52.08, 'YK' => 74.58, 'Yurtiçi' => 74.58,
            ],
            '200_349' => [
                'TEX' => 65.83, 'PTT' => 65.83, 'Aras' => 73.74, 'Sürat' => 79.58, 'Kolay Gelsin' => 82.08, 'DHL' => 82.91, 'YK' => 104.58, 'Yurtiçi' => 104.58,
            ],
        ],
        'standard_delivery' => [
            '0_199' => [
                'TEX' => 64.58, 'PTT' => 64.58, 'Aras' => 71.66, 'Sürat' => 77.49, 'Kolay Gelsin' => 79.58, 'DHL' => 80.83, 'YK' => 101.24, 'Yurtiçi' => 101.24,
            ],
            '200_349' => [
                'TEX' => 72.91, 'PTT' => 72.91, 'Aras' => 79.99, 'Sürat' => 85.83, 'Kolay Gelsin' => 87.91, 'DHL' => 89.16, 'YK' => 109.58, 'Yurtiçi' => 109.58,
            ],
        ],
    ];

    public function seedDefaults(int $userId): int
    {
        $created = 0;

        foreach ($this->defaultRows() as $row) {
            $model = TrendyolBoosterShippingRate::query()->firstOrNew([
                'user_id' => $userId,
                'cargo_company' => $row['cargo_company'],
                'desi' => $row['desi'],
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

    public function upsertFromParser(int $userId, array $row): void
    {
        $model = TrendyolBoosterShippingRate::query()->firstOrNew([
            'user_id' => $userId,
            'cargo_company' => $row['cargo_company'],
            'desi' => $row['desi'],
        ]);

        $model->forceFill([
            'price' => $row['price'],
            'marketplace' => 'trendyol',
            'source' => 'PDF Import',
            'imported_at' => now(),
        ])->save();
    }

    /**
     * Satış fiyatı, desi ve kargo şirketine göre KDV HARİÇ kargo maliyetini hesaplar.
     * $isFastDelivery = termin süresi 1 gün ve başarılı teslimat durumu (Avantajlı Barem).
     */
    public function calculateCargoCost(float $salePrice, int $desi, string $cargoCompany = 'TEX', bool $isFastDelivery = true, ?int $userId = null): float
    {
        return (float) ($this->resolveRate($salePrice, $desi, $cargoCompany, $isFastDelivery, $userId)['net'] ?? 0);
    }

    /** @return array<string, mixed> */
    public function recommend(
        float $salePrice,
        float $estimatedDesi,
        string $cargoCompany = 'TEX',
        bool $isFastDelivery = true,
        ?int $userId = null,
    ): array {
        $billableDesi = max(1, (int) ceil($estimatedDesi));
        $cargoCompany = $this->normalizeCompany($cargoCompany);
        $rate = $this->resolveRate($salePrice, $billableDesi, $cargoCompany, $isFastDelivery, $userId);
        $scenarios = collect([
            'low' => max(1, (int) floor($billableDesi * 0.8)),
            'base' => $billableDesi,
            'high' => max($billableDesi + 1, (int) ceil($billableDesi * 1.25)),
        ])->map(function (int $desi, string $key) use ($salePrice, $cargoCompany, $isFastDelivery, $userId): array {
            $scenarioRate = $this->resolveRate($salePrice, $desi, $cargoCompany, $isFastDelivery, $userId);

            return [
                'key' => $key,
                'desi' => $desi,
                'cost_net' => $scenarioRate['net'],
                'cost_gross' => $scenarioRate['net'] !== null ? round($scenarioRate['net'] * 1.20, 2) : null,
                'source' => $scenarioRate['source'],
            ];
        })->values()->all();

        return [
            'cargo_company' => $cargoCompany,
            'billable_desi' => $billableDesi,
            'cost_net' => $rate['net'],
            'cost_gross' => $rate['net'] !== null ? round($rate['net'] * 1.20, 2) : null,
            'vat_rate' => 20,
            'source' => $rate['source'],
            'source_label' => $rate['source_label'],
            'confidence' => $rate['confidence'],
            'matched_desi' => $rate['matched_desi'],
            'note' => $rate['note'],
            'scenarios' => $scenarios,
        ];
    }

    /** @return array{net: ?float, source: string, source_label: string, confidence: float, matched_desi: ?int, note: string} */
    protected function resolveRate(
        float $salePrice,
        int $desi,
        string $cargoCompany,
        bool $isFastDelivery,
        ?int $userId,
    ): array {
        if ($salePrice > 0 && $salePrice < 350 && $desi <= 10) {
            $baremType = $isFastDelivery ? 'fast_delivery' : 'standard_delivery';
            $range = $salePrice < 200 ? '0_199' : '200_349';
            $companyKey = isset(self::BAREM_RATES[$baremType][$range][$cargoCompany]) ? $cargoCompany : 'TEX';
            $value = self::BAREM_RATES[$baremType][$range][$companyKey] ?? null;

            if ($value !== null) {
                return [
                    'net' => (float) $value,
                    'source' => 'trendyol_barem',
                    'source_label' => 'Trendyol barem tarifesi',
                    'confidence' => 78,
                    'matched_desi' => $desi,
                    'note' => '350 TL altı ve 10 desiye kadar olan gönderi için barem fiyatı kullanıldı.',
                ];
            }
        }

        if (Schema::hasTable('trendyol_booster_shipping_rates')) {
            $rate = TrendyolBoosterShippingRate::query()
                ->where(fn (Builder $query) => $query->where('user_id', $userId)->orWhereNull('user_id'))
                ->where('cargo_company', $cargoCompany)
                ->where('desi', '>=', $desi)
                ->orderByRaw('user_id IS NULL')
                ->orderBy('desi')
                ->first();

            if ($rate) {
                return [
                    'net' => (float) $rate->price,
                    'source' => $rate->user_id !== null ? 'user_shipping_tariff' : 'system_shipping_tariff',
                    'source_label' => $rate->user_id !== null ? 'Yüklenen kargo tarifesi' : 'Sistem kargo tarifesi',
                    'confidence' => $rate->user_id !== null ? 96 : 84,
                    'matched_desi' => (int) $rate->desi,
                    'note' => (int) $rate->desi === $desi
                        ? 'KDV hariç desi fiyatı tarifeden birebir alındı.'
                        : $desi.' desi satırı bulunamadığı için yukarı yuvarlanmış '.(int) $rate->desi.' desi tarifesi kullanıldı.',
                ];
            }
        }

        $settingsRate = (new MpSettingsService($userId))->getDesiPrice($cargoCompany, $desi);
        if ($settingsRate > 0) {
            return [
                'net' => $settingsRate,
                'source' => 'financial_rule',
                'source_label' => 'ZOLM kargo finans kuralı',
                'confidence' => 86,
                'matched_desi' => $desi,
                'note' => 'KDV hariç kargo bedeli geçerli ZOLM desi finans kuralından alındı.',
            ];
        }

        return [
            'net' => null,
            'source' => 'missing_tariff',
            'source_label' => 'Tarife bulunamadı',
            'confidence' => 0,
            'matched_desi' => null,
            'note' => 'Bu kargo şirketi ve desi için tarife bulunamadı; kargo bedeli otomatik uygulanmadı.',
        ];
    }

    protected function normalizeCompany(string $company): string
    {
        $company = trim($company);

        return match (true) {
            str_contains(mb_strtolower($company), 'yurti') => 'Yurtiçi',
            str_contains(mb_strtolower($company), 'sürat') => 'Sürat',
            str_contains(mb_strtolower($company), 'aras') => 'Aras',
            str_contains(mb_strtolower($company), 'ptt') => 'PTT',
            str_contains(mb_strtolower($company), 'dhl') => 'DHL',
            str_contains(mb_strtolower($company), 'kolay') => 'Kolay Gelsin',
            str_contains(mb_strtolower($company), 'trendyol'), str_contains(mb_strtolower($company), 'tex') => 'TEX',
            default => $company !== '' ? $company : 'TEX',
        };
    }

    /**
     * Dashboard ve UI için tüm desi verisini pivot formatta (veya liste) döndürür.
     */
    public function dashboard(int $userId): array
    {
        $base = TrendyolBoosterShippingRate::query()
            ->where(function (Builder $query) use ($userId): void {
                $query->where('user_id', $userId)->orWhereNull('user_id');
            });

        $rows = (clone $base)->orderBy('desi')->get();

        // Şirketleri çıkar
        $companies = $rows->pluck('cargo_company')->unique()->values()->toArray();

        // Pivot formatı: [desi => [company => price]]
        $pivot = [];
        foreach ($rows as $row) {
            $pivot[$row->desi][$row->cargo_company] = (float) $row->price;
        }

        $last = (clone $base)->latest('updated_at')->first();

        return [
            'total' => (clone $base)->count(),
            'last_update' => $last?->updated_at?->format('d.m.Y'),
            'companies' => $companies,
            'pivot' => $pivot,
        ];
    }

    protected function defaultRows(): array
    {
        return [];
    }
}
