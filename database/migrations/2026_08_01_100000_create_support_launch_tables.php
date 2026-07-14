<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_launch_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->string('status', 40)->default('draft'); // draft, readiness_failed, ready_for_approval, approved, canary, paused, rolled_back, completed
            $table->json('target_channels')->nullable();
            $table->string('initial_mode', 30)->default('manual'); // manual, copilot, automatic
            $table->integer('canary_percentage')->default(100);
            $table->integer('conversation_limit')->nullable();
            $table->json('allowed_categories')->nullable();
            $table->json('rollback_rules')->nullable();
            $table->unsignedBigInteger('approver_id')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->json('readiness_snapshot')->nullable();
            $table->timestamps();
        });

        Schema::create('support_launch_plan_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('launch_plan_id')->constrained('support_launch_plans')->cascadeOnDelete();
            $table->integer('step_number');
            $table->string('title', 150);
            $table->string('status', 30)->default('pending'); // pending, completed, failed
            $table->timestamps();
        });

        Schema::create('support_launch_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->foreignId('launch_plan_id')->nullable()->constrained('support_launch_plans')->nullOnDelete();
            $table->string('event_type', 60);
            $table->json('details_json')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_launch_events');
        Schema::dropIfExists('support_launch_plan_steps');
        Schema::dropIfExists('support_launch_plans');
    }
};
