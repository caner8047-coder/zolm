<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_payroll_periods', function (Blueprint $table) {
            $table->string('source_status', 20)->default('fresh')->after('source_hash');
            $table->json('source_stale_findings')->nullable()->after('source_status');
            $table->timestamp('source_checked_at')->nullable()->after('source_stale_findings');
            $table->index(['legal_entity_id', 'source_status'], 'hr_payroll_period_source_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('hr_payroll_periods', function (Blueprint $table) {
            $table->dropIndex('hr_payroll_period_source_status_idx');
            $table->dropColumn(['source_status', 'source_stale_findings', 'source_checked_at']);
        });
    }
};
