<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_experiments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->string('name', 200);
            $table->string('type', 60)->default('prompt_variant'); // prompt_variant, policy_variant, knowledge_variant, channel_template
            $table->string('status', 30)->default('draft'); // draft, ready, running, completed, cancelled, archived
            $table->text('description')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('support_experiment_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('experiment_id')->constrained('support_experiments')->cascadeOnDelete();
            $table->string('label', 100); // control, variant_a, variant_b...
            $table->string('artifact_type', 60)->nullable(); // prompt, policy, knowledge, channel_template
            $table->unsignedBigInteger('artifact_version_id')->nullable(); // SupportArtifactVersion
            $table->json('config_override')->nullable();
            $table->boolean('is_winner_candidate')->default(false);
            $table->timestamps();
        });

        Schema::create('support_experiment_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('experiment_id')->constrained('support_experiments')->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained('support_experiment_variants')->cascadeOnDelete();
            $table->string('status', 30)->default('pending');
            $table->unsignedInteger('case_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('summary')->nullable(); // aggregate metrics
            $table->timestamps();
        });

        Schema::create('support_experiment_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('support_experiment_runs')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->unsignedBigInteger('eval_case_id')->nullable(); // SupportAiEvalCaseResult
            $table->boolean('policy_violation')->default(false);
            $table->boolean('hallucination_detected')->default(false);
            $table->string('brand_voice_score', 10)->nullable(); // pass, fail, unknown
            $table->unsignedInteger('latency_ms')->nullable();
            $table->unsignedInteger('total_tokens')->nullable();
            $table->decimal('estimated_cost', 10, 6)->nullable();
            $table->string('human_verdict', 20)->nullable(); // accepted, edited, rejected, null
            $table->text('redacted_response_sample')->nullable(); // PII redacted
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_experiment_results');
        Schema::dropIfExists('support_experiment_runs');
        Schema::dropIfExists('support_experiment_variants');
        Schema::dropIfExists('support_experiments');
    }
};
