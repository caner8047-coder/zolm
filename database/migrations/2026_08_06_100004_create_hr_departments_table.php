<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_departments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legal_entity_id');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('cost_center_id')->nullable();
            $table->string('name', 255);
            $table->string('code', 50);
            $table->unsignedBigInteger('manager_employee_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['legal_entity_id', 'code']);
            $table->foreign('legal_entity_id')->references('id')->on('legal_entities')->onDelete('cascade');
            $table->foreign('parent_id')->references('id')->on('hr_departments')->onDelete('set null');
            $table->foreign('branch_id')->references('id')->on('hr_branches')->onDelete('set null');
            $table->foreign('cost_center_id')->references('id')->on('hr_cost_centers')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_departments');
    }
};
