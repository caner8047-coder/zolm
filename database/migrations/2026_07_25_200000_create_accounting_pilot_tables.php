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
        if (!Schema::hasTable('accounting_pilot_feedbacks')) {
            Schema::create('accounting_pilot_feedbacks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('actor_user_id')->nullable()->constrained('users')->onDelete('set null');
                $table->string('module');
                $table->string('route_name')->nullable();
                $table->string('type')->default('feedback');
                $table->string('severity')->default('medium');
                $table->string('status')->default('open');
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('browser')->nullable();
                $table->integer('viewport_width')->nullable();
                $table->integer('viewport_height')->nullable();
                $table->string('screenshot_path')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->json('meta_json')->nullable();
                $table->timestamps();

                // Indexes
                $table->index(['user_id', 'status', 'severity'], 'acc_feedback_user_status_severity_idx');
                $table->index(['user_id', 'module', 'status'], 'acc_feedback_user_module_status_idx');
                $table->index(['user_id', 'actor_user_id'], 'acc_feedback_user_actor_idx');
            });
        }

        if (!Schema::hasTable('accounting_pilot_health_snapshots')) {
            Schema::create('accounting_pilot_health_snapshots', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->string('run_uuid');
                $table->string('status')->default('passed');
                $table->integer('score')->default(100);
                $table->integer('failed_count')->default(0);
                $table->integer('warning_count')->default(0);
                $table->json('checks_json');
                $table->json('meta_json')->nullable();
                $table->timestamps();

                // Indexes
                $table->index(['user_id', 'status', 'created_at'], 'acc_health_user_status_created_idx');
                $table->unique(['user_id', 'run_uuid'], 'acc_health_user_uuid_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounting_pilot_health_snapshots');
        Schema::dropIfExists('accounting_pilot_feedbacks');
    }
};
