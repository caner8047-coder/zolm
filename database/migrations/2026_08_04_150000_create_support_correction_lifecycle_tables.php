<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_answer_errors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained('support_conversations')->cascadeOnDelete();
            $table->foreignId('message_id')->nullable()->constrained('support_messages')->nullOnDelete();
            $table->foreignId('support_ai_run_id')->nullable()->constrained('support_ai_runs')->nullOnDelete();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('severity', 20)->default('warning');
            $table->text('affected_claim_encrypted')->nullable();
            $table->text('root_cause_encrypted')->nullable();
            $table->string('correction_strategy', 40)->default('correction_message');
            $table->string('status', 30)->default('reported');
            $table->foreignId('correction_message_id')->nullable()->constrained('support_messages')->nullOnDelete();
            $table->timestamp('detected_at');
            $table->timestamp('corrected_at')->nullable();
            $table->timestamps();
            $table->index(['store_id', 'status', 'severity'], 'support_answer_errors_queue_idx');
        });

        Schema::create('support_correction_tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('support_answer_error_id')->constrained('support_answer_errors')->cascadeOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('task_type', 40)->default('send_correction');
            $table->string('status', 20)->default('pending');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('result_json')->nullable();
            $table->timestamps();
        });

        Schema::create('support_regression_cases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->foreignId('support_answer_error_id')->constrained('support_answer_errors')->cascadeOnDelete();
            $table->string('language', 12)->default('tr');
            $table->string('intent', 60)->default('general');
            $table->text('question_encrypted');
            $table->text('wrong_answer_encrypted')->nullable();
            $table->text('expected_answer_encrypted')->nullable();
            $table->string('status', 30)->default('pending_review');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique('support_answer_error_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_regression_cases');
        Schema::dropIfExists('support_correction_tasks');
        Schema::dropIfExists('support_answer_errors');
    }
};
