<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_leave_policies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legal_entity_id');
            $table->unsignedBigInteger('leave_type_id');
            $table->string('scope', 30)->default('company');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('position_id')->nullable();
            $table->string('employment_type', 30)->nullable();
            $table->decimal('annual_entitlement', 8, 2)->default(0);
            $table->decimal('max_carryover', 8, 2)->default(0);
            $table->boolean('allows_negative_balance')->default(false);
            $table->boolean('requires_hr_approval')->default(false);
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('legal_entity_id')->references('id')->on('legal_entities')->cascadeOnDelete();
            $table->foreign('leave_type_id')->references('id')->on('hr_leave_types')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('hr_branches')->nullOnDelete();
            $table->foreign('department_id')->references('id')->on('hr_departments')->nullOnDelete();
            $table->foreign('position_id')->references('id')->on('hr_positions')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['legal_entity_id', 'leave_type_id', 'is_active']);
            $table->index(['legal_entity_id', 'scope']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_leave_policies');
    }
};
