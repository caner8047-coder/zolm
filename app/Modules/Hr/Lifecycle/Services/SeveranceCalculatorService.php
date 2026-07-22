<?php

namespace App\Modules\Hr\Lifecycle\Services;

use Illuminate\Support\Carbon;

class SeveranceCalculatorService
{
    public const DEFAULT_2026_SEVERANCE_CEILING = 46244.38;
    public const STAMP_TAX_RATE = 0.00759;

    public function calculate(
        Carbon|string $startDate,
        Carbon|string $endDate,
        float $monthlyGrossSalary,
        float $monthlyBenefits = 0.0,
        ?float $severanceCeiling = null
    ): array {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();
        $ceiling = $severanceCeiling ?? self::DEFAULT_2026_SEVERANCE_CEILING;

        $totalDays = max(1, $start->diffInDays($end) + 1);
        $years = (int) floor($totalDays / 365.25);
        $remainingDaysAfterYears = $totalDays - (int) floor($years * 365.25);

        // Giydirilmiş Brüt Ücret (Brüt Maaş + Aylık Düzenli Yan Haklar)
        $adjustedGrossSalary = max(0, $monthlyGrossSalary + $monthlyBenefits);

        // 1. KIDEM TAZMİNATI HESABI
        // Yasal tavan kontrolü
        $severanceBase = min($adjustedGrossSalary, $ceiling);
        $grossSeverance = round($severanceBase * ($totalDays / 365.25), 2);
        $severanceStampTax = round($grossSeverance * self::STAMP_TAX_RATE, 2);
        $netSeverance = round($grossSeverance - $severanceStampTax, 2);

        // 2. İHBAR TAZMİNATI HESABI
        // 4857 SK m.17 ihbar süreleri
        $noticeWeeks = match (true) {
            $totalDays <= 180 => 2,      // 0 - 6 Ay arası: 2 Hafta
            $totalDays <= 540 => 4,      // 6 Ay - 1.5 Yıl arası: 4 Hafta
            $totalDays <= 1095 => 6,     // 1.5 Yıl - 3 Yıl arası: 6 Hafta
            default => 8,                 // 3 Yıldan fazla: 8 Hafta
        };

        $noticeDays = $noticeWeeks * 7;
        $dailyAdjustedGross = $adjustedGrossSalary / 30.0;
        $grossNotice = round($dailyAdjustedGross * $noticeDays, 2);

        // İhbar Kesintileri (%15 Standart Gelir Vergisi + %0.759 Damga Vergisi)
        $noticeIncomeTax = round($grossNotice * 0.15, 2);
        $noticeStampTax = round($grossNotice * self::STAMP_TAX_RATE, 2);
        $netNotice = round($grossNotice - ($noticeIncomeTax + $noticeStampTax), 2);

        return [
            'tenure' => [
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'total_days' => $totalDays,
                'years' => $years,
                'remaining_days' => $remainingDaysAfterYears,
                'tenure_human' => "{$years} Yıl, {$remainingDaysAfterYears} Gün",
            ],
            'base' => [
                'monthly_gross' => $monthlyGrossSalary,
                'monthly_benefits' => $monthlyBenefits,
                'adjusted_gross' => $adjustedGrossSalary,
                'severance_ceiling' => $ceiling,
                'effective_severance_base' => $severanceBase,
                'ceiling_applied' => $adjustedGrossSalary > $ceiling,
            ],
            'severance' => [
                'gross_amount' => $grossSeverance,
                'income_tax' => 0.00, // Gelir vergisinden muaf
                'stamp_tax' => $severanceStampTax,
                'net_amount' => $netSeverance,
            ],
            'notice' => [
                'notice_weeks' => $noticeWeeks,
                'notice_days' => $noticeDays,
                'gross_amount' => $grossNotice,
                'income_tax' => $noticeIncomeTax,
                'stamp_tax' => $noticeStampTax,
                'net_amount' => $netNotice,
            ],
            'summary' => [
                'total_gross' => round($grossSeverance + $grossNotice, 2),
                'total_deductions' => round($severanceStampTax + $noticeIncomeTax + $noticeStampTax, 2),
                'total_net_payable' => round($netSeverance + $netNotice, 2),
            ],
        ];
    }
}
