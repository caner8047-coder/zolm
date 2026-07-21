<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('hr_overtime_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_entity_id')->constrained('legal_entities')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->foreignId('overtime_type_id')->constrained('hr_overtime_types')->restrictOnDelete();
            $table->date('work_date');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->unsignedInteger('requested_minutes');
            $table->unsignedInteger('approved_minutes')->nullable();
            $table->string('status', 30)->default('pending_manager');
            $table->text('reason');
            $table->string('project_reference', 120)->nullable();
            $table->string('production_order_reference', 120)->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamps();
            $table->index(['legal_entity_id', 'status', 'work_date'], 'hr_overtime_status_date_idx');
            $table->index(['legal_entity_id', 'employee_id', 'work_date'], 'hr_overtime_employee_date_idx');
        });
    }
    public function down(): void { Schema::dropIfExists('hr_overtime_requests'); }
};
