<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_payroll_periods', function (Blueprint $table) {
            $table->timestamp('calculated_at')->nullable()->after('prepared_by');
            $table->foreignId('calculated_by')->nullable()->after('calculated_at')->constrained('users')->nullOnDelete();
            $table->string('calculation_hash', 64)->nullable()->after('calculated_by');
            $table->string('preflight_status', 20)->default('pending')->after('calculation_hash');
            $table->json('preflight_findings')->nullable()->after('preflight_status');
        });
    }

    public function down(): void
    {
        Schema::table('hr_payroll_periods', function (Blueprint $table) {
            $table->dropConstrainedForeignId('calculated_by');
            $table->dropColumn(['calculated_at', 'calculation_hash', 'preflight_status', 'preflight_findings']);
        });
    }
};
