<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_payroll_records', function (Blueprint $table) {
            $table->unsignedBigInteger('salary_record_id')->nullable()->after('employee_id');
            $table->json('rule_snapshot')->nullable()->after('source_hash');
            $table->text('calculation_trace')->nullable()->after('rule_snapshot');
            $table->text('gross_pay_encrypted')->nullable()->after('calculation_trace');
            $table->text('employee_deductions_encrypted')->nullable()->after('gross_pay_encrypted');
            $table->text('employer_contributions_encrypted')->nullable()->after('employee_deductions_encrypted');
            $table->text('income_tax_encrypted')->nullable()->after('employer_contributions_encrypted');
            $table->text('stamp_tax_encrypted')->nullable()->after('income_tax_encrypted');
            $table->text('net_pay_encrypted')->nullable()->after('stamp_tax_encrypted');
            $table->string('calculation_hash', 64)->nullable()->after('net_pay_encrypted');
            $table->timestamp('calculated_at')->nullable()->after('calculation_hash');
        });
    }

    public function down(): void
    {
        Schema::table('hr_payroll_records', function (Blueprint $table) {
            $table->dropColumn('salary_record_id');
            $table->dropColumn([
                'rule_snapshot', 'calculation_trace', 'gross_pay_encrypted',
                'employee_deductions_encrypted', 'employer_contributions_encrypted',
                'income_tax_encrypted', 'stamp_tax_encrypted', 'net_pay_encrypted',
                'calculation_hash', 'calculated_at',
            ]);
        });
    }
};
