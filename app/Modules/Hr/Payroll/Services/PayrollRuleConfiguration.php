<?php

namespace App\Modules\Hr\Payroll\Services;

use Illuminate\Validation\ValidationException;

class PayrollRuleConfiguration
{
    public const CODE = 'STATUTORY_PAYROLL';

    public function validate(array $configuration): array
    {
        $validator = validator($configuration, [
            'standard_monthly_minutes' => ['required', 'integer', 'min:1'],
            'overtime_multiplier_basis_points' => ['required', 'integer', 'min:0', 'max:100000'],
            'holiday_work_multiplier_basis_points' => ['sometimes', 'integer', 'min:0', 'max:100000'],
            'weekly_rest_work_multiplier_basis_points' => ['sometimes', 'integer', 'min:0', 'max:100000'],
            'require_employee_payroll_profile' => ['sometimes', 'boolean'],
            'employee_social_security_basis_points' => ['required', 'integer', 'min:0', 'max:10000'],
            'employee_unemployment_basis_points' => ['required', 'integer', 'min:0', 'max:10000'],
            'employer_social_security_basis_points' => ['required', 'integer', 'min:0', 'max:10000'],
            'employer_unemployment_basis_points' => ['required', 'integer', 'min:0', 'max:10000'],
            'stamp_tax_basis_points' => ['required', 'integer', 'min:0', 'max:10000'],
            'income_tax_exemption_cents' => ['required', 'integer', 'min:0'],
            'stamp_tax_exemption_cents' => ['required', 'integer', 'min:0'],
            'social_security_ceiling_cents' => ['nullable', 'integer', 'min:1'],
            'income_tax_brackets' => ['required', 'array', 'min:1'],
            'income_tax_brackets.*.upper_limit_cents' => ['nullable', 'integer', 'min:1'],
            'income_tax_brackets.*.rate_basis_points' => ['required', 'integer', 'min:0', 'max:10000'],
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages([
                'configuration' => 'Yasal bordro kural paketi eksik veya geçersiz: '.$validator->errors()->first(),
            ]);
        }

        $validated = $validator->validated();
        $previous = 0;
        $openEndedSeen = false;
        foreach ($validated['income_tax_brackets'] as $index => $bracket) {
            $limit = $bracket['upper_limit_cents'];
            if ($openEndedSeen || ($limit !== null && $limit <= $previous)) {
                throw ValidationException::withMessages(['configuration' => 'Gelir vergisi dilimleri artan sırada olmalı; yalnız son dilim açık uçlu olabilir.']);
            }
            if ($limit === null) {
                $openEndedSeen = true;
            } else {
                $previous = $limit;
            }
        }
        if (! $openEndedSeen) {
            throw ValidationException::withMessages(['configuration' => 'Gelir vergisi dilimlerinin son satırı açık uçlu olmalı.']);
        }

        return $validated;
    }

    public function hash(array $configuration): string
    {
        return hash('sha256', json_encode($this->sortRecursively($configuration), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    private function sortRecursively(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value);
        }
        foreach ($value as &$item) {
            if (is_array($item)) {
                $item = $this->sortRecursively($item);
            }
        }
        return $value;
    }
}
