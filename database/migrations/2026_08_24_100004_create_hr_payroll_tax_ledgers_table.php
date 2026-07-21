<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_payroll_tax_openings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_entity_id')->constrained('legal_entities')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->unsignedSmallInteger('tax_year');
            $table->text('opening_tax_base_encrypted');
            $table->string('source_reference', 160);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['legal_entity_id', 'employee_id', 'tax_year']);
        });

        Schema::create('hr_payroll_tax_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_entity_id')->constrained('legal_entities')->cascadeOnDelete();
            $table->foreignId('payroll_period_id')->constrained('hr_payroll_periods')->cascadeOnDelete();
            $table->foreignId('payroll_record_id')->constrained('hr_payroll_records')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->unsignedSmallInteger('tax_year');
            $table->text('opening_tax_base_encrypted');
            $table->text('period_tax_base_encrypted');
            $table->text('closing_tax_base_encrypted');
            $table->string('calculation_hash', 64);
            $table->timestamps();
            $table->unique(['payroll_period_id', 'employee_id']);
            $table->index(['legal_entity_id', 'employee_id', 'tax_year'], 'hr_payroll_tax_ledger_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_payroll_tax_ledgers');
        Schema::dropIfExists('hr_payroll_tax_openings');
    }
};
