<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_workforce_plans', function (Blueprint $table) {
            $table->id(); $table->foreignId('legal_entity_id')->constrained('legal_entities')->cascadeOnDelete();
            $table->string('name', 160); $table->date('starts_on'); $table->date('ends_on');
            $table->text('budget_encrypted'); $table->string('currency', 3)->default('TRY'); $table->string('status', 30)->default('draft');
            $table->string('source_hash', 64)->nullable(); $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamp('approved_at')->nullable(); $table->timestamps();
            $table->index(['legal_entity_id', 'status', 'starts_on']);
        });
        Schema::create('hr_workforce_plan_lines', function (Blueprint $table) {
            $table->id(); $table->foreignId('legal_entity_id')->constrained('legal_entities')->cascadeOnDelete();
            $table->foreignId('workforce_plan_id')->constrained('hr_workforce_plans')->cascadeOnDelete();
            $table->foreignId('department_id')->constrained('hr_departments')->restrictOnDelete();
            $table->foreignId('position_id')->constrained('hr_positions')->restrictOnDelete();
            $table->decimal('planned_fte', 8, 2); $table->text('planned_monthly_cost_encrypted');
            $table->decimal('actual_fte_snapshot', 8, 2)->nullable(); $table->text('actual_monthly_cost_encrypted')->nullable();
            $table->text('notes')->nullable(); $table->timestamps();
            $table->unique(['workforce_plan_id', 'department_id', 'position_id'], 'hr_workforce_plan_line_unique');
        });
    }
    public function down(): void { Schema::dropIfExists('hr_workforce_plan_lines'); Schema::dropIfExists('hr_workforce_plans'); }
};
