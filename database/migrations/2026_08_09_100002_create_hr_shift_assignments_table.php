<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('hr_shift_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_entity_id')->constrained('legal_entities')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->foreignId('shift_template_id')->constrained('hr_shift_templates')->restrictOnDelete();
            $table->date('shift_date');
            $table->string('status', 20)->default('planned');
            $table->text('note')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['legal_entity_id', 'employee_id', 'shift_date']);
            $table->index(['legal_entity_id', 'shift_date', 'status']);
            $table->index(['legal_entity_id', 'shift_template_id', 'shift_date']);
        });
    }

    public function down(): void { Schema::dropIfExists('hr_shift_assignments'); }
};
