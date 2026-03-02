<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MpFinancialRule extends Model
{
    protected $fillable = [
        'rule_key', 'rule_value', 'category', 'marketplace',
        'valid_from', 'valid_to', 'description',
    ];

    protected $casts = [
        'valid_from' => 'date',
        'valid_to'   => 'date',
    ];

    // ─── Static Helpers ─────────────────────────────────────────

    /**
     * Belirli bir tarihte geçerli olan kural değerini getir
     *
     * @param string      $key      Kural anahtarı (stopaj_rate, barem_limit, tex_desi_0_2)
     * @param string|null $category Kategori/kargo firması (TEX, Aras, PTT...)
     * @param string|null $date     Tarih (null ise bugün)
     * @return string|null
     */
    public static function getRule(string $key, ?string $category = null, ?string $date = null): ?string
    {
        $date = $date ?? now()->toDateString();

        $query = static::where('rule_key', $key)
            ->where('valid_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('valid_to')
                  ->orWhere('valid_to', '>=', $date);
            });

        if ($category) {
            $query->where('category', $category);
        }

        return $query->orderByDesc('valid_from')->value('rule_value');
    }

    /**
     * Sayısal kural değeri
     */
    public static function getRuleFloat(string $key, ?string $category = null, ?string $date = null): float
    {
        return (float) (static::getRule($key, $category, $date) ?? 0);
    }

    /**
     * Belirli bir kargo firmasının desi fiyatını getir
     */
    public static function getDesiPrice(string $cargoCompany, float $desi, ?string $date = null): float
    {
        // Desi aralıklarını uygun formata çevir
        $desiRanges = ['0_2', '3', '4', '5', '10', '15', '20', '25', '30'];

        $key = null;
        foreach ($desiRanges as $range) {
            if (str_contains($range, '_')) {
                $parts = explode('_', $range);
                if ($desi >= (float)$parts[0] && $desi <= (float)$parts[1]) {
                    $key = "desi_{$range}";
                    break;
                }
            } else {
                if ($desi <= (float)$range) {
                    $key = "desi_{$range}";
                    break;
                }
            }
        }

        if (!$key) $key = 'desi_30'; // En yüksek aralık

        return static::getRuleFloat($key, $cargoCompany, $date);
    }

    /**
     * Barem fiyatını getir (sipariş tutarına göre)
     */
    public static function getBaremPrice(string $cargoCompany, float $orderAmount, ?string $date = null): ?float
    {
        $baremLimit = static::getRuleFloat('barem_limit', null, $date);

        if ($orderAmount >= $baremLimit) {
            return null; // 300+ TL = barem geçerli değil
        }

        if ($orderAmount < 150) {
            return static::getRuleFloat('barem_0_150', $cargoCompany, $date);
        }

        return static::getRuleFloat('barem_150_300', $cargoCompany, $date);
    }

    /**
     * Ağır kargo cezasını getir
     */
    public static function getHeavyCargoFee(string $cargoCompany, ?string $date = null): float
    {
        return static::getRuleFloat('heavy_cargo_fee', $cargoCompany, $date);
    }
}
