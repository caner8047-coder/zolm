<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_payroll_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_entity_id')->constrained('legal_entities')->cascadeOnDelete();
            $table->foreignId('payroll_period_id')->constrained('hr_payroll_periods')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->string('code', 60);
            $table->string('name', 160);
            $table->string('type', 30);
            $table->text('amount_encrypted');
            $table->boolean('social_security_exempt')->default(false);
            $table->boolean('income_tax_exempt')->default(false);
            $table->boolean('pre_tax_deduction')->default(false);
            $table->string('status', 30)->default('pending_approval');
            $table->text('reason');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['payroll_period_id', 'employee_id', 'code']);
            $table->index(['legal_entity_id', 'payroll_period_id', 'status'], 'hr_payroll_adjustments_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_payroll_adjustments');
    }
};
