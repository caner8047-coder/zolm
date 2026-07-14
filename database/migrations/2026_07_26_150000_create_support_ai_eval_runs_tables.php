<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('support_ai_eval_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->restrictOnDelete();
            $table->string('run_type', 40)->default('golden_dataset');
            $table->string('provider', 60);
            $table->string('model', 60);
            $table->string('dataset_version', 20)->default('v1');
            $table->integer('average_score');
            $table->boolean('passed_gate');
            $table->string('status', 20)->default('completed');
            $table->foreignId('triggered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->json('summary_json')->nullable();
            $table->timestamps();
        });

        Schema::create('support_ai_eval_case_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_ai_eval_run_id')
                ->constrained('support_ai_eval_runs')
                ->cascadeOnDelete();
            $table->string('category', 60);
            $table->string('question_hash', 32);
            $table->json('expected_keywords');
            $table->text('response_preview')->nullable();
            $table->integer('score');
            $table->string('status', 20);
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('support_ai_eval_case_results');
        Schema::dropIfExists('support_ai_eval_runs');
    }
};
