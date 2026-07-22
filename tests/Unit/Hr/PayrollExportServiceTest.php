<?php

namespace Tests\Unit\Hr;

use App\Modules\Hr\Payroll\Models\HrPayrollPeriod;
use App\Modules\Hr\Payroll\Services\PayrollExportService;
use Tests\TestCase;

class PayrollExportServiceTest extends TestCase
{
    public function test_journal_voucher_generation_is_balanced(): void
    {
        $period = new HrPayrollPeriod([
            'id' => 999,
            'legal_entity_id' => 1,
            'timesheet_period_id' => 1,
            'name' => '2026 Temmuz Test Bordrosu',
            'status' => 'calculated',
        ]);

        $record = new \App\Modules\Hr\Payroll\Models\HrPayrollRecord([
            'gross_pay_encrypted' => '50000',
            'employee_deductions_encrypted' => '7500',
            'employer_contributions_encrypted' => '8750',
            'income_tax_encrypted' => '6000',
            'stamp_tax_encrypted' => '379.5',
            'net_pay_encrypted' => '36120.5',
        ]);

        $service = new PayrollExportService();
        $voucher = $service->generateJournalVoucher($period, collect([$record]));

        $this->assertEquals('2026 Temmuz Test Bordrosu', $voucher['period_name']);
        $this->assertIsArray($voucher['debits']);
        $this->assertIsArray($voucher['credits']);
        $this->assertTrue($voucher['is_balanced']);
    }
}
