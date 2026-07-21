<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('hr_shift_availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_entity_id')->constrained('legal_entities')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->date('availability_date');
            $table->string('status', 20);
            $table->time('preferred_start')->nullable();
            $table->time('preferred_end')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['legal_entity_id', 'employee_id', 'availability_date'], 'hr_shift_availability_employee_date_unique');
            $table->index(['legal_entity_id', 'availability_date', 'status'], 'hr_shift_availability_date_status_idx');
        });
    }

    public function down(): void { Schema::dropIfExists('hr_shift_availabilities'); }
};
