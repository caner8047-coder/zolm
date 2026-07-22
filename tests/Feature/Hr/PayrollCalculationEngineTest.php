<?php

namespace Tests\Feature\Hr;

use App\Modules\Hr\Payroll\Services\NetToGrossPayrollSolver;
use App\Modules\Hr\Payroll\Services\PayrollCalculationEngine;
use Tests\TestCase;

class PayrollCalculationEngineTest extends TestCase
{
    public function test_adjustments_and_exemptions_are_applied_with_integer_money_math(): void
    {
        $result = app(PayrollCalculationEngine::class)->calculate(1000000, 0, $this->input(), $this->rules(), [
            ['type' => 'earning', 'amount_cents' => 100000, 'social_security_exempt' => true, 'income_tax_exempt' => true],
            ['type' => 'deduction', 'amount_cents' => 50000, 'pre_tax_deduction' => false],
            ['type' => 'employer_incentive', 'amount_cents' => 30000],
        ]);
        $this->assertSame(1100000, $result['gross_pay_cents']);
        $this->assertSame(1000000, $result['social_security_base_cents']);
        $this->assertSame(890000, $result['period_tax_base_cents']);
        $this->assertSame(776000, $result['net_pay_cents']);
        $this->assertSame(30000, $result['employer_contributions_cents']);
    }

    public function test_net_to_gross_uses_the_same_calculation_engine(): void
    {
        $solution = app(NetToGrossPayrollSolver::class)->solve(727000, 0, $this->input(), $this->rules());
        $this->assertSame(0, $solution['difference_cents']);
        $this->assertSame(1000000, $solution['monthly_gross_cents']);
        $this->assertSame(727000, $solution['calculation']['net_pay_cents']);
    }

    public function test_employer_benefit_increases_total_cost_without_changing_net_pay(): void
    {
        $baseline = app(PayrollCalculationEngine::class)->calculate(1000000, 0, $this->input(), $this->rules());
        $withBenefit = app(PayrollCalculationEngine::class)->calculate(1000000, 0, $this->input(), $this->rules(), [
            ['type' => 'employer_benefit', 'amount_cents' => 125000],
        ]);

        $this->assertSame($baseline['net_pay_cents'], $withBenefit['net_pay_cents']);
        $this->assertSame(125000, $withBenefit['employer_benefit_cents']);
        $this->assertSame($baseline['employer_total_cost_cents'] + 125000, $withBenefit['employer_total_cost_cents']);
    }

    public function test_special_work_uses_distinct_statutory_multipliers_without_double_counting(): void
    {
        $rules = $this->rules() + [
            'holiday_work_multiplier_basis_points' => 20000,
            'weekly_rest_work_multiplier_basis_points' => 25000,
        ];
        $result = app(PayrollCalculationEngine::class)->calculate(600000, 0, [
            'scheduled_minutes' => 6000,
            'missing_minutes' => 0,
            'approved_overtime_minutes' => 180,
            'approved_regular_overtime_minutes' => 60,
            'approved_holiday_work_minutes' => 60,
            'approved_weekly_rest_work_minutes' => 60,
        ], $rules);

        $this->assertSame(9000, $result['regular_overtime_gross_cents']);
        $this->assertSame(12000, $result['holiday_work_gross_cents']);
        $this->assertSame(15000, $result['weekly_rest_work_gross_cents']);
        $this->assertSame(36000, $result['overtime_gross_cents']);
        $this->assertSame(636000, $result['gross_pay_cents']);
    }

    private function input(): array { return ['scheduled_minutes' => 6000, 'missing_minutes' => 0, 'approved_overtime_minutes' => 0]; }
    private function rules(): array { return ['standard_monthly_minutes' => 6000, 'overtime_multiplier_basis_points' => 15000, 'employee_social_security_basis_points' => 1000, 'employee_unemployment_basis_points' => 100, 'employer_social_security_basis_points' => 500, 'employer_unemployment_basis_points' => 100, 'stamp_tax_basis_points' => 100, 'income_tax_exemption_cents' => 22500, 'stamp_tax_exemption_cents' => 2500, 'income_tax_brackets' => [['upper_limit_cents' => null, 'rate_basis_points' => 2000]]]; }
}
