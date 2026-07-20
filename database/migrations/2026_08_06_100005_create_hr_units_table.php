<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_units', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('department_id');
            $table->string('name', 255);
            $table->string('code', 50);
            $table->unsignedBigInteger('manager_employee_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['department_id', 'code']);
            $table->foreign('department_id')->references('id')->on('hr_departments')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_units');
    }
};
