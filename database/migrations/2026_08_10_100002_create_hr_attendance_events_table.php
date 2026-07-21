<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('hr_attendance_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_entity_id')->constrained('legal_entities')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->foreignId('attendance_device_id')->nullable()->constrained('hr_attendance_devices')->nullOnDelete();
            $table->string('event_type', 30);
            $table->timestamp('occurred_at');
            $table->string('source', 30);
            $table->string('source_key', 160);
            $table->string('payload_hash', 64);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('is_manual')->default(false);
            $table->text('manual_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['legal_entity_id', 'source', 'source_key'], 'hr_attendance_source_key_unique');
            $table->index(['legal_entity_id', 'employee_id', 'occurred_at'], 'hr_attendance_employee_time_idx');
            $table->index(['legal_entity_id', 'occurred_at', 'event_type'], 'hr_attendance_time_type_idx');
        });
    }
    public function down(): void { Schema::dropIfExists('hr_attendance_events'); }
};
