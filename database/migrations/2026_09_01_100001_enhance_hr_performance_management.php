<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_performance_cycles', function (Blueprint $table) {
            $table->unsignedTinyInteger('anonymity_threshold')->default(3)->after('status');
            $table->boolean('auto_reminders')->default(true)->after('anonymity_threshold');
            $table->json('reminder_days_before')->nullable()->after('auto_reminders');
        });
        Schema::table('hr_performance_evaluations', function (Blueprint $table) {
            $table->decimal('reviewer_weight', 5, 2)->default(100)->after('reviewer_type');
            $table->boolean('is_anonymous')->default(false)->after('reviewer_weight');
            $table->unsignedInteger('reminder_count')->default(0)->after('submitted_at');
            $table->timestamp('last_reminded_at')->nullable()->after('reminder_count');
        });
        Schema::table('hr_performance_goals', function (Blueprint $table) {
            $table->string('measurement_type', 20)->default('numeric')->after('type');
            $table->text('target_text')->nullable()->after('target_value');
            $table->text('current_text')->nullable()->after('current_value');
        });

        Schema::create('hr_performance_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_entity_id')->constrained('legal_entities')->cascadeOnDelete();
            $table->foreignId('cycle_id')->constrained('hr_performance_cycles')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->decimal('overall_score', 5, 2)->nullable();
            $table->unsignedInteger('expected_responses')->default(0);
            $table->unsignedInteger('completed_responses')->default(0);
            $table->string('status', 30)->default('in_progress');
            $table->json('reviewer_breakdown')->nullable();
            $table->json('competency_breakdown')->nullable();
            $table->string('calculation_hash', 64)->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();
            $table->unique(['cycle_id', 'employee_id'], 'hr_performance_result_cycle_employee_uq');
            $table->index(['legal_entity_id', 'cycle_id', 'status'], 'hr_performance_result_status_idx');
        });

        Schema::create('hr_performance_goal_check_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_entity_id')->constrained('legal_entities')->cascadeOnDelete();
            $table->foreignId('goal_id')->constrained('hr_performance_goals')->cascadeOnDelete();
            $table->decimal('previous_value', 14, 2)->nullable();
            $table->decimal('new_value', 14, 2)->nullable();
            $table->text('previous_text')->nullable();
            $table->text('new_text')->nullable();
            $table->text('note');
            $table->text('evidence')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['legal_entity_id', 'goal_id', 'created_at'], 'hr_goal_check_ins_timeline_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_performance_goal_check_ins');
        Schema::dropIfExists('hr_performance_results');
        Schema::table('hr_performance_goals', function (Blueprint $table) {
            $table->dropColumn(['measurement_type', 'target_text', 'current_text']);
        });
        Schema::table('hr_performance_evaluations', function (Blueprint $table) {
            $table->dropColumn(['reviewer_weight', 'is_anonymous', 'reminder_count', 'last_reminded_at']);
        });
        Schema::table('hr_performance_cycles', function (Blueprint $table) {
            $table->dropColumn(['anonymity_threshold', 'auto_reminders', 'reminder_days_before']);
        });
    }
};
