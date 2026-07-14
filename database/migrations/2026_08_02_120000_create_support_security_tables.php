<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_security_audit_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->string('status', 30)->default('pending'); // pending, running, completed, failed
            $table->string('overall_severity', 20)->nullable(); // critical, high, medium, low, clean
            $table->unsignedInteger('findings_count')->default(0);
            $table->unsignedBigInteger('triggered_by')->nullable();
            $table->boolean('is_dry_run')->default(true);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('support_security_findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('support_security_audit_runs')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->string('category', 80); // route_flag, rbac_guard, tenant_isolation, secret_encryption, webhook_hmac, etc.
            $table->string('severity', 20); // critical, high, medium, low
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('open'); // open, acknowledged, remediated
            $table->timestamps();
        });

        Schema::create('support_security_evidence_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('support_security_audit_runs')->cascadeOnDelete();
            $table->string('control_name', 100);
            $table->string('result', 20); // pass, fail, unknown
            $table->text('evidence_data_encrypted')->nullable(); // Crypt ile şifreli, PII/secret içermez
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_security_evidence_items');
        Schema::dropIfExists('support_security_findings');
        Schema::dropIfExists('support_security_audit_runs');
    }
};
