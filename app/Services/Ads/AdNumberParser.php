<?php

namespace App\Services\Ads;

class AdNumberParser
{
    /**
     * Türkçe sayı formatını parse et
     * 1.234,56 → 1234.56
     * %3,42 → 3.42
     * - → null
     */
    public function parse($value): ?float
    {
        if ($value === null || $value === '' || $value === '-') {
            return null;
        }

        $value = (string) $value;

        // Yüzde işaretini kaldır
        $value = str_replace('%', '', $value);

        // Para birimi sembollerini kaldır
        $value = str_replace(['₺', 'TL', 'TRY', '$', '€'], '', $value);

        // Boşlukları temizle
        $value = trim($value);

        // Negatif kontrolü
        $isNegative = str_starts_with($value, '-');
        if ($isNegative) {
            $value = substr($value, 1);
        }

        // Türkçe binlik ayracı (.) ve ondalık ayracı (,) dönüşümü
        // Eğer hem . hem , varsa: 1.234,56 → 1234.56
        // Sadece , varsa: 123,45 → 123.45
        // Sadece . varsa ve arkasında 2+ hane varsa: 1234.56 → 1234.56
        // Sadece . varsa ve arkasında 1-2 hane varsa: 1234.56 → belirsiz, olduğu gibi bırak

        if (str_contains($value, ',') && str_contains($value, '.')) {
            // Her iki ayracı da var: 1.234,56
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } elseif (str_contains($value, ',')) {
            // Sadece virgül: 123,45 veya 1.234.567,89
            $parts = explode(',', $value);
            if (count($parts) === 2 && strlen($parts[1]) <= 2) {
                // Ondalık ayracı olarak virgül: 123,45
                $value = $parts[0] . '.' . $parts[1];
            } else {
                // Virgül binlik ayracı: 1,234,567
                $value = str_replace(',', '', $value);
            }
        }

        // Nokta varsa ve binlik ayracı olup olmadığını kontrol et
        if (str_contains($value, '.')) {
            $parts = explode('.', $value);
            if (count($parts) === 2 && strlen($parts[1]) === 3 && strlen($parts[0]) > 3) {
                // Binlik ayracı: 1.234.567,89 (virgül zaten yukarıda işlendi)
                // Bu durumda nokta binlik ayracıdır
                $value = str_replace('.', '', $value);
            }
        }

        $result = (float) $value;

        return $isNegative ? -$result : $result;
    }
}
