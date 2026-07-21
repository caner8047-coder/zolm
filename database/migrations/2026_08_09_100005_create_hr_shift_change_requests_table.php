<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('hr_shift_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_entity_id')->constrained('legal_entities')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->foreignId('shift_assignment_id')->constrained('hr_shift_assignments')->cascadeOnDelete();
            $table->foreignId('desired_shift_template_id')->constrained('hr_shift_templates')->restrictOnDelete();
            $table->date('desired_shift_date');
            $table->string('status', 20)->default('pending');
            $table->text('reason');
            $table->text('decision_note')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['legal_entity_id', 'status', 'created_at'], 'hr_shift_change_status_idx');
            $table->index(['legal_entity_id', 'employee_id', 'status'], 'hr_shift_change_employee_idx');
        });
    }

    public function down(): void { Schema::dropIfExists('hr_shift_change_requests'); }
};
