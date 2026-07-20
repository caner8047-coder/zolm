<?php

namespace App\Enums;

enum ZolmClaimReason: string
{
    case DEFECTIVE = 'ZOLM_DEFECTIVE';
    case WRONG_ITEM = 'ZOLM_WRONG_ITEM';
    case CUSTOMER_REGRET = 'ZOLM_CUSTOMER_REGRET';
    case CARGO_DAMAGE = 'ZOLM_CARGO_DAMAGE';
    case LATE_DELIVERY = 'ZOLM_LATE_DELIVERY';
    case MISSING_PARTS = 'ZOLM_MISSING_PARTS';

    public function label(): string
    {
        return match($this) {
            self::DEFECTIVE => 'Kusurlu/Bozuk Ürün (Fire)',
            self::WRONG_ITEM => 'Yanlış Ürün Gönderimi',
            self::CUSTOMER_REGRET => 'Müşteri Cayma Hakkı',
            self::CARGO_DAMAGE => 'Kargo Hasarı',
            self::LATE_DELIVERY => 'Geç Teslimat',
            self::MISSING_PARTS => 'Eksik Parça',
        };
    }

    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }
        return $options;
    }
}
