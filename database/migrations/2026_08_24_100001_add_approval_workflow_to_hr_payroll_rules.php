<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_payroll_rules', function (Blueprint $table) {
            $table->string('status', 30)->default('pending_approval')->after('is_active');
            $table->string('configuration_hash', 64)->nullable()->after('configuration');
            $table->foreignId('approved_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->index(['legal_entity_id', 'code', 'status', 'effective_from'], 'hr_payroll_rules_approved_effective_idx');
        });
    }

    public function down(): void
    {
        Schema::table('hr_payroll_rules', function (Blueprint $table) {
            $table->dropIndex('hr_payroll_rules_approved_effective_idx');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['status', 'configuration_hash', 'approved_at']);
        });
    }
};
