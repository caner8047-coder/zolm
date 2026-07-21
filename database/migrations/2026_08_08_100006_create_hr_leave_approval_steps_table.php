<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_leave_approval_steps', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legal_entity_id');
            $table->unsignedBigInteger('leave_request_id');
            $table->unsignedSmallInteger('step_order');
            $table->string('approver_type', 30);
            $table->unsignedBigInteger('approver_employee_id')->nullable();
            $table->unsignedBigInteger('approver_user_id')->nullable();
            $table->string('status', 30)->default('pending');
            $table->text('comment')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->foreign('legal_entity_id')->references('id')->on('legal_entities')->cascadeOnDelete();
            $table->foreign('leave_request_id')->references('id')->on('hr_leave_requests')->cascadeOnDelete();
            $table->foreign('approver_employee_id')->references('id')->on('hr_employees')->nullOnDelete();
            $table->foreign('approver_user_id')->references('id')->on('users')->nullOnDelete();
            $table->unique(['leave_request_id', 'step_order']);
            $table->index(['legal_entity_id', 'approver_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_leave_approval_steps');
    }
};
