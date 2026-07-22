<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_payroll_periods', function (Blueprint $table) {
            $table->string('variance_status', 20)->default('pending')->after('preflight_findings');
            $table->json('variance_findings')->nullable()->after('variance_status');
            $table->timestamp('variance_checked_at')->nullable()->after('variance_findings');
            $table->foreignId('variance_reviewed_by')->nullable()->after('variance_checked_at')->constrained('users')->nullOnDelete();
            $table->timestamp('variance_reviewed_at')->nullable()->after('variance_reviewed_by');
            $table->text('variance_review_note')->nullable()->after('variance_reviewed_at');
            $table->index(['legal_entity_id', 'variance_status'], 'hr_payroll_period_variance_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('hr_payroll_periods', function (Blueprint $table) {
            $table->dropIndex('hr_payroll_period_variance_status_idx');
            $table->dropConstrainedForeignId('variance_reviewed_by');
            $table->dropColumn([
                'variance_status', 'variance_findings', 'variance_checked_at',
                'variance_reviewed_at', 'variance_review_note',
            ]);
        });
    }
};
