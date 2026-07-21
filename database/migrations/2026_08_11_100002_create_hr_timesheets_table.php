<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('hr_timesheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_entity_id')->constrained('legal_entities')->cascadeOnDelete();
            $table->foreignId('timesheet_period_id')->constrained('hr_timesheet_periods')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->foreignId('shift_assignment_id')->nullable()->constrained('hr_shift_assignments')->nullOnDelete();
            $table->date('work_date');
            $table->unsignedInteger('scheduled_minutes')->default(0);
            $table->unsignedInteger('worked_minutes')->default(0);
            $table->unsignedInteger('break_minutes')->default(0);
            $table->unsignedInteger('leave_minutes')->default(0);
            $table->unsignedInteger('overtime_minutes')->default(0);
            $table->unsignedInteger('missing_minutes')->default(0);
            $table->timestamp('first_in_at')->nullable();
            $table->timestamp('last_out_at')->nullable();
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('source_revision')->default(1);
            $table->timestamp('calculated_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->unique(['legal_entity_id', 'employee_id', 'work_date'], 'hr_timesheet_employee_day_unique');
            $table->index(['legal_entity_id', 'timesheet_period_id', 'status'], 'hr_timesheet_period_status_idx');
        });
    }
    public function down(): void { Schema::dropIfExists('hr_timesheets'); }
};
