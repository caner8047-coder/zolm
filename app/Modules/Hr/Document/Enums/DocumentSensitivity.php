<?php

namespace App\Modules\Hr\Document\Enums;

enum DocumentSensitivity: string
{
    case Standard = 'standard';
    case Confidential = 'confidential';
    case HighlySensitive = 'highly_sensitive';

    public function label(): string
    {
        return match ($this) {
            self::Standard => 'Standart',
            self::Confidential => 'Gizli',
            self::HighlySensitive => 'Çok Hassas',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Standard => 'gray',
            self::Confidential => 'yellow',
            self::HighlySensitive => 'red',
        };
    }
}
