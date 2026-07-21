<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_payroll_periods', function (Blueprint $table) {
            $table->string('output_preflight_status', 20)->default('pending')->after('preflight_findings');
            $table->json('output_preflight_findings')->nullable()->after('output_preflight_status');
            $table->string('output_preflight_hash', 64)->nullable()->after('output_preflight_findings');
            $table->timestamp('output_preflight_at')->nullable()->after('output_preflight_hash');
            $table->foreignId('output_preflight_by')->nullable()->after('output_preflight_at')->constrained('users')->nullOnDelete();
        });

        Schema::create('hr_payroll_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_entity_id')->constrained('legal_entities')->cascadeOnDelete();
            $table->foreignId('payroll_period_id')->constrained('hr_payroll_periods')->restrictOnDelete();
            $table->string('classification', 40)->default('control_output');
            $table->string('format', 10)->default('xlsx');
            $table->string('preflight_hash', 64);
            $table->string('content_hash', 64);
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at');
            $table->timestamps();
            $table->index(['legal_entity_id', 'payroll_period_id', 'generated_at'], 'hr_payroll_exports_period_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_payroll_exports');
        Schema::table('hr_payroll_periods', function (Blueprint $table) {
            $table->dropConstrainedForeignId('output_preflight_by');
            $table->dropColumn(['output_preflight_status', 'output_preflight_findings', 'output_preflight_hash', 'output_preflight_at']);
        });
    }
};
