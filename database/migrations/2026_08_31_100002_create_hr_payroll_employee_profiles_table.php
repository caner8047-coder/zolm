<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_payroll_employee_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_entity_id')->constrained('legal_entities')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->string('payroll_group_code', 60)->nullable();
            $table->string('payment_method', 20)->default('bank');
            $table->text('iban_encrypted')->nullable();
            $table->char('iban_hash', 64)->nullable();
            $table->string('iban_last_four', 4)->nullable();
            $table->string('bank_name', 120)->nullable();
            $table->string('bank_account_holder', 160)->nullable();
            $table->string('social_security_status', 40)->default('standard');
            $table->string('insurance_branch_code', 20)->nullable();
            $table->string('incentive_law_code', 30)->nullable();
            $table->string('missing_day_default_code', 20)->nullable();
            $table->unsignedTinyInteger('disability_degree')->nullable();
            $table->boolean('is_retired')->default(false);
            $table->boolean('is_rd_employee')->default(false);
            $table->boolean('is_technopark_employee')->default(false);
            $table->string('status', 30)->default('pending_approval');
            $table->text('change_reason');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['legal_entity_id', 'employee_id', 'version'], 'hr_payroll_profile_employee_version_uq');
            $table->index(['legal_entity_id', 'status', 'effective_from'], 'hr_payroll_profile_status_effective_idx');
            $table->index(['legal_entity_id', 'iban_hash'], 'hr_payroll_profile_iban_hash_idx');
        });

        Schema::table('hr_payroll_records', function (Blueprint $table) {
            $table->foreignId('payroll_profile_id')->nullable()->after('salary_record_id')
                ->constrained('hr_payroll_employee_profiles')->nullOnDelete();
            $table->text('payroll_profile_snapshot')->nullable()->after('rule_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('hr_payroll_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payroll_profile_id');
            $table->dropColumn('payroll_profile_snapshot');
        });
        Schema::dropIfExists('hr_payroll_employee_profiles');
    }
};
