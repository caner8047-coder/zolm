<?php

namespace App\Modules\Hr\Payroll\Services;

class PayrollCalculationEngine
{
    public function calculate(int $monthlyGrossCents, int $openingTaxBaseCents, array $input, array $rules, array $adjustments = []): array
    {
        $standardMinutes = $rules['standard_monthly_minutes'];
        $payableMinutes = max(0, min($standardMinutes, $input['scheduled_minutes'] - $input['missing_minutes']));
        $baseGross = $this->divideRounded($monthlyGrossCents * $payableMinutes, $standardMinutes);
        $overtimeGross = $this->divideRounded(
            $monthlyGrossCents * $input['approved_overtime_minutes'] * $rules['overtime_multiplier_basis_points'],
            $standardMinutes * 10000
        );
        $earning = 0; $socialSecurityExemptEarning = 0; $incomeTaxExemptEarning = 0;
        $preTaxDeduction = 0; $postTaxDeduction = 0; $employerIncentive = 0;
        foreach ($adjustments as $adjustment) {
            $amount = (int) $adjustment['amount_cents'];
            if ($adjustment['type'] === 'earning') {
                $earning += $amount;
                $socialSecurityExemptEarning += ($adjustment['social_security_exempt'] ?? false) ? $amount : 0;
                $incomeTaxExemptEarning += ($adjustment['income_tax_exempt'] ?? false) ? $amount : 0;
            } elseif ($adjustment['type'] === 'deduction') {
                if ($adjustment['pre_tax_deduction'] ?? false) $preTaxDeduction += $amount; else $postTaxDeduction += $amount;
            } elseif ($adjustment['type'] === 'employer_incentive') {
                $employerIncentive += $amount;
            }
        }
        $gross = $baseGross + $overtimeGross + $earning;

        $socialSecurityBase = max(0, $gross - $socialSecurityExemptEarning);
        if (! empty($rules['social_security_ceiling_cents'])) $socialSecurityBase = min($socialSecurityBase, $rules['social_security_ceiling_cents']);
        $employeeSocialSecurity = $this->basisPoints($socialSecurityBase, $rules['employee_social_security_basis_points']);
        $employeeUnemployment = $this->basisPoints($socialSecurityBase, $rules['employee_unemployment_basis_points']);
        $employerSocialSecurity = $this->basisPoints($socialSecurityBase, $rules['employer_social_security_basis_points']);
        $employerUnemployment = $this->basisPoints($socialSecurityBase, $rules['employer_unemployment_basis_points']);
        $periodTaxBase = max(0, $gross - $incomeTaxExemptEarning - $employeeSocialSecurity - $employeeUnemployment - $preTaxDeduction);
        $grossIncomeTax = $this->progressiveTax($openingTaxBaseCents + $periodTaxBase, $rules['income_tax_brackets'])
            - $this->progressiveTax($openingTaxBaseCents, $rules['income_tax_brackets']);
        $incomeTax = max(0, $grossIncomeTax - $rules['income_tax_exemption_cents']);
        $grossStampTax = $this->basisPoints($gross, $rules['stamp_tax_basis_points']);
        $stampTax = max(0, $grossStampTax - $rules['stamp_tax_exemption_cents']);
        $employeeDeductions = $employeeSocialSecurity + $employeeUnemployment + $incomeTax + $stampTax + $preTaxDeduction + $postTaxDeduction;
        $grossEmployerContributions = $employerSocialSecurity + $employerUnemployment;
        $appliedEmployerIncentive = min($grossEmployerContributions, $employerIncentive);
        $employerContributions = $grossEmployerContributions - $appliedEmployerIncentive;

        return [
            'base_gross_cents' => $baseGross,
            'overtime_gross_cents' => $overtimeGross,
            'gross_pay_cents' => $gross,
            'additional_earning_cents' => $earning,
            'social_security_exempt_earning_cents' => $socialSecurityExemptEarning,
            'income_tax_exempt_earning_cents' => $incomeTaxExemptEarning,
            'social_security_base_cents' => $socialSecurityBase,
            'employee_social_security_cents' => $employeeSocialSecurity,
            'employee_unemployment_cents' => $employeeUnemployment,
            'employer_social_security_cents' => $employerSocialSecurity,
            'employer_unemployment_cents' => $employerUnemployment,
            'period_tax_base_cents' => $periodTaxBase,
            'opening_tax_base_cents' => $openingTaxBaseCents,
            'closing_tax_base_cents' => $openingTaxBaseCents + $periodTaxBase,
            'gross_income_tax_cents' => $grossIncomeTax,
            'income_tax_exemption_cents' => min($grossIncomeTax, $rules['income_tax_exemption_cents']),
            'income_tax_cents' => $incomeTax,
            'gross_stamp_tax_cents' => $grossStampTax,
            'stamp_tax_exemption_cents' => min($grossStampTax, $rules['stamp_tax_exemption_cents']),
            'stamp_tax_cents' => $stampTax,
            'employee_deductions_cents' => $employeeDeductions,
            'pre_tax_deduction_cents' => $preTaxDeduction,
            'post_tax_deduction_cents' => $postTaxDeduction,
            'gross_employer_contributions_cents' => $grossEmployerContributions,
            'employer_incentive_cents' => $appliedEmployerIncentive,
            'employer_contributions_cents' => $employerContributions,
            'net_pay_cents' => max(0, $gross - $employeeDeductions),
            'employer_total_cost_cents' => $gross + $employerContributions,
            'payable_minutes' => $payableMinutes,
        ];
    }

    private function progressiveTax(int $taxBaseCents, array $brackets): int
    {
        $tax = 0;
        $lower = 0;
        foreach ($brackets as $bracket) {
            $upper = $bracket['upper_limit_cents'];
            $taxable = $upper === null
                ? max(0, $taxBaseCents - $lower)
                : max(0, min($taxBaseCents, $upper) - $lower);
            $tax += $this->basisPoints($taxable, $bracket['rate_basis_points']);
            if ($upper === null || $taxBaseCents <= $upper) {
                break;
            }
            $lower = $upper;
        }
        return $tax;
    }

    private function basisPoints(int $amountCents, int $basisPoints): int
    {
        return $this->divideRounded($amountCents * $basisPoints, 10000);
    }

    private function divideRounded(int $numerator, int $denominator): int
    {
        return intdiv($numerator + intdiv($denominator, 2), $denominator);
    }
}
