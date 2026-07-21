<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_leave_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legal_entity_id');
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('leave_type_id');
            $table->unsignedBigInteger('policy_id')->nullable();
            $table->string('status', 30)->default('pending_manager');
            $table->date('start_date');
            $table->date('end_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->decimal('requested_amount', 8, 2);
            $table->string('unit', 20)->default('day');
            $table->text('reason')->nullable();
            $table->unsignedBigInteger('document_id')->nullable();
            $table->unsignedBigInteger('delegate_employee_id')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->unsignedBigInteger('revision_of_id')->nullable();
            $table->timestamps();

            $table->foreign('legal_entity_id')->references('id')->on('legal_entities')->cascadeOnDelete();
            $table->foreign('employee_id')->references('id')->on('hr_employees')->cascadeOnDelete();
            $table->foreign('leave_type_id')->references('id')->on('hr_leave_types')->restrictOnDelete();
            $table->foreign('policy_id')->references('id')->on('hr_leave_policies')->nullOnDelete();
            $table->foreign('document_id')->references('id')->on('hr_employee_documents')->nullOnDelete();
            $table->foreign('delegate_employee_id')->references('id')->on('hr_employees')->nullOnDelete();
            $table->foreign('cancelled_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('revision_of_id')->references('id')->on('hr_leave_requests')->nullOnDelete();
            $table->index(['legal_entity_id', 'employee_id', 'status']);
            $table->index(['legal_entity_id', 'start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_leave_requests');
    }
};
