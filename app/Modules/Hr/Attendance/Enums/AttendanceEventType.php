<?php

namespace App\Modules\Hr\Attendance\Enums;

enum AttendanceEventType: string
{
    case CheckIn = 'check_in';
    case CheckOut = 'check_out';
    case BreakStart = 'break_start';
    case BreakEnd = 'break_end';
    public function label(): string { return match ($this) { self::CheckIn => 'Giriş', self::CheckOut => 'Çıkış', self::BreakStart => 'Mola Başlangıcı', self::BreakEnd => 'Mola Bitişi' }; }
}
