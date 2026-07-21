<?php

namespace App\Modules\Hr\Payroll\Services;

class NetToGrossPayrollSolver
{
    public function __construct(private PayrollCalculationEngine $engine) {}

    public function solve(int $targetNetCents, int $openingTaxBaseCents, array $input, array $rules, array $adjustments = []): array
    {
        if ($targetNetCents <= 0) throw new \InvalidArgumentException('Hedef net ücret pozitif olmalı.');
        $low = 0; $high = max($targetNetCents * 2, 10000);
        while ($this->engine->calculate($high, $openingTaxBaseCents, $input, $rules, $adjustments)['net_pay_cents'] < $targetNetCents) {
            $high *= 2;
            if ($high > 100000000000) throw new \RuntimeException('Hedef net ücret için güvenli brüt sınırı bulunamadı.');
        }
        while ($low < $high) {
            $mid = intdiv($low + $high, 2);
            $net = $this->engine->calculate($mid, $openingTaxBaseCents, $input, $rules, $adjustments)['net_pay_cents'];
            if ($net < $targetNetCents) $low = $mid + 1; else $high = $mid;
        }
        $best = null;
        foreach (array_unique([max(0, $low - 1), $low, $low + 1]) as $monthlyGrossCents) {
            $result = $this->engine->calculate($monthlyGrossCents, $openingTaxBaseCents, $input, $rules, $adjustments);
            $difference = abs($result['net_pay_cents'] - $targetNetCents);
            if ($best === null || $difference < $best['difference_cents']) $best = ['monthly_gross_cents' => $monthlyGrossCents, 'target_net_cents' => $targetNetCents, 'difference_cents' => $difference, 'calculation' => $result];
        }
        return $best;
    }
}
