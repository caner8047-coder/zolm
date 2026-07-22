<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mp_price_canary_certifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id');
            $table->string('barcode_masked', 30);
            $table->string('barcode_hash', 64)->nullable(); // SHA256 of real barcode for dedup
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->string('actor_role', 30)->nullable();
            $table->string('correlation_id', 64)->nullable();
            $table->string('branch', 100)->nullable();
            $table->string('commit_hash', 40)->nullable();
            $table->string('environment', 20)->default('local');

            // Readiness section
            $table->string('readiness_decision', 100)->nullable();
            $table->boolean('readiness_passed')->default(false);
            $table->json('readiness_criteria_passed')->nullable();
            $table->json('readiness_criteria_failed')->nullable();
            $table->json('readiness_criteria_warnings')->nullable();
            $table->json('readiness_minimum_samples')->nullable();
            $table->string('readiness_hash', 64)->nullable();
            $table->string('policy_version', 20)->nullable();
            $table->string('rule_version', 20)->nullable();
            $table->decimal('shadow_duration_hours', 8, 2)->nullable();
            $table->unsignedInteger('shadow_record_count')->nullable();
            $table->unsignedInteger('shadow_evaluation_count')->nullable();
            $table->decimal('api_success_rate', 5, 2)->nullable();
            $table->decimal('queue_success_rate', 5, 2)->nullable();
            $table->decimal('shadow_accuracy_rate', 5, 2)->nullable();
            $table->decimal('margin_protection_rate', 5, 2)->nullable();
            $table->decimal('unnecessary_drop_rate', 5, 2)->nullable();
            $table->unsignedInteger('duplicate_action_count')->default(0);
            $table->unsignedInteger('unexpected_push_count')->default(0);

            // Approval section
            $table->unsignedBigInteger('approval_id')->nullable();
            $table->boolean('approval_valid')->default(false);
            $table->boolean('fingerprint_match')->default(false);

            // Price simulation section
            $table->decimal('simulated_current_price', 10, 2)->nullable();
            $table->decimal('simulated_buybox_price', 10, 2)->nullable();
            $table->decimal('simulated_recommended_price', 10, 2)->nullable();
            $table->decimal('simulated_min_safe_price', 10, 2)->nullable();
            $table->decimal('simulated_price_change_pct', 7, 4)->nullable();
            $table->string('recommendation_type', 50)->nullable();
            $table->string('risk_level', 20)->nullable();

            // Security section
            $table->boolean('emergency_stop_active')->default(false);
            $table->boolean('manual_lock_active')->default(false);
            $table->unsignedInteger('pending_action_count')->default(0);
            $table->string('write_guard_result', 100)->nullable();
            $table->unsignedInteger('real_price_push_count')->default(0);
            $table->string('batch_request_id_generated')->nullable();
            $table->decimal('listing_price_before', 10, 2)->nullable();
            $table->decimal('listing_price_after', 10, 2)->nullable();
            $table->boolean('listing_price_changed')->default(false);
            $table->boolean('audit_created')->default(false);
            $table->boolean('notification_created')->default(false);

            // Final result
            $table->string('certification_result', 60)->default('failed');
            // certified_zero_write | blocked_insufficient_evidence | blocked_readiness |
            // blocked_approval | blocked_write_guard | failed
            $table->json('certification_report_json')->nullable();
            $table->timestamp('certified_at')->nullable();

            $table->timestamps();

            $table->index('store_id');
            $table->index('barcode_hash');
            $table->index('certification_result');
            $table->index('certified_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mp_price_canary_certifications');
    }
};
