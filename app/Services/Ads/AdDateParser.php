<?php

namespace App\Services\Ads;

use Carbon\Carbon;

class AdDateParser
{
    /**
     * Tarih formatlarını parse et
     */
    public function parse($value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        // Carbon nesnesi ise doğrudan döndür
        if ($value instanceof Carbon) {
            return $value;
        }

        // DateTime nesnesi ise dönüştür
        if ($value instanceof \DateTime) {
            return Carbon::instance($value);
        }

        $value = (string) trim($value);

        // Excel serial date kontrolü (40000-60000 arası)
        if (is_numeric($value) && $value >= 40000 && $value <= 60000) {
            return Carbon::createFromExcelSerialDate((int) $value);
        }

        // Desteklenen formatlar
        $formats = [
            'd.m.Y',           // 24.06.2026
            'd.m.Y H:i',       // 24.06.2026 14:30
            'd/m/Y',           // 24/06/2026
            'd/m/Y H:i',       // 24/06/2026 14:30
            'Y-m-d',           // 2026-06-24
            'Y-m-d H:i',       // 2026-06-24 14:30
            'Y-m-d\TH:i:s',    // 2026-06-24T14:30:00
            'm/d/Y',           // 06/24/2026
        ];

        foreach ($formats as $format) {
            $parsed = Carbon::createFromFormat($format, $value);
            if ($parsed) {
                return $parsed;
            }
        }

        // Son çare: Carbon::parse
        try {
            return Carbon::parse($value);
        } catch (\Exception $e) {
            return null;
        }
    }
}
