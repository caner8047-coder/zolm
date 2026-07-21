<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_leave_balances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legal_entity_id');
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('leave_type_id');
            $table->unsignedSmallInteger('period_year');
            $table->decimal('entitled_amount', 8, 2)->default(0);
            $table->decimal('used_amount', 8, 2)->default(0);
            $table->decimal('adjustment_amount', 8, 2)->default(0);
            $table->decimal('carried_amount', 8, 2)->default(0);
            $table->decimal('remaining_amount', 8, 2)->default(0);
            $table->timestamps();

            $table->foreign('legal_entity_id')->references('id')->on('legal_entities')->cascadeOnDelete();
            $table->foreign('employee_id')->references('id')->on('hr_employees')->cascadeOnDelete();
            $table->foreign('leave_type_id')->references('id')->on('hr_leave_types')->restrictOnDelete();
            $table->unique(['legal_entity_id', 'employee_id', 'leave_type_id', 'period_year'], 'hr_leave_balance_period_unique');
            $table->index(['legal_entity_id', 'employee_id', 'period_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_leave_balances');
    }
};
