<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_leave_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legal_entity_id');
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('leave_type_id');
            $table->unsignedBigInteger('leave_request_id')->nullable();
            $table->unsignedSmallInteger('period_year');
            $table->string('transaction_type', 30);
            $table->decimal('amount', 8, 2);
            $table->string('source_type', 100);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->text('note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('legal_entity_id')->references('id')->on('legal_entities')->cascadeOnDelete();
            $table->foreign('employee_id')->references('id')->on('hr_employees')->cascadeOnDelete();
            $table->foreign('leave_type_id')->references('id')->on('hr_leave_types')->restrictOnDelete();
            $table->foreign('leave_request_id')->references('id')->on('hr_leave_requests')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->unique(['legal_entity_id', 'source_type', 'source_id', 'transaction_type'], 'hr_leave_transaction_source_unique');
            $table->index(
                ['legal_entity_id', 'employee_id', 'leave_type_id', 'period_year'],
                'hr_leave_tx_tenant_employee_type_year_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_leave_transactions');
    }
};
