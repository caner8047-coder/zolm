<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('hr_attendance_anomalies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_entity_id')->constrained('legal_entities')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->date('work_date');
            $table->string('type', 40);
            $table->string('severity', 20)->default('warning');
            $table->string('status', 20)->default('open');
            $table->json('details')->nullable();
            $table->text('resolution_note')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->unique(['legal_entity_id', 'employee_id', 'work_date', 'type'], 'hr_attendance_anomaly_unique');
            $table->index(['legal_entity_id', 'status', 'work_date'], 'hr_attendance_anomaly_status_idx');
        });
    }
    public function down(): void { Schema::dropIfExists('hr_attendance_anomalies'); }
};
