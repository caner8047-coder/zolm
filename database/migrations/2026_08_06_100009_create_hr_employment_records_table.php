<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_employment_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('legal_entity_id');
            $table->unsignedBigInteger('sgk_workplace_id')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('unit_id')->nullable();
            $table->unsignedBigInteger('team_id')->nullable();
            $table->unsignedBigInteger('position_id')->nullable();
            $table->unsignedBigInteger('manager_employee_id')->nullable();
            $table->unsignedBigInteger('second_manager_employee_id')->nullable();
            $table->unsignedBigInteger('cost_center_id')->nullable();

            $table->string('employment_type', 20)->default('full_time');
            $table->string('work_model', 20)->default('onsite'); // onsite, remote, hybrid
            $table->string('contract_type', 50)->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('probation_end_date')->nullable();
            $table->decimal('weekly_work_hours', 4, 1)->nullable();
            $table->string('status', 20)->default('active');
            $table->text('termination_reason')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('hr_employees')->onDelete('cascade');
            $table->foreign('legal_entity_id')->references('id')->on('legal_entities')->onDelete('cascade');
            $table->foreign('sgk_workplace_id')->references('id')->on('hr_sgk_workplaces')->onDelete('set null');
            $table->foreign('branch_id')->references('id')->on('hr_branches')->onDelete('set null');
            $table->foreign('department_id')->references('id')->on('hr_departments')->onDelete('set null');
            $table->foreign('unit_id')->references('id')->on('hr_units')->onDelete('set null');
            $table->foreign('team_id')->references('id')->on('hr_teams')->onDelete('set null');
            $table->foreign('position_id')->references('id')->on('hr_positions')->onDelete('set null');
            $table->foreign('cost_center_id')->references('id')->on('hr_cost_centers')->onDelete('set null');

            $table->index(['employee_id', 'status']);
            $table->index(['legal_entity_id', 'status']);
            $table->index(['department_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_employment_records');
    }
};
