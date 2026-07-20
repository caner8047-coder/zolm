<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_teams', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('unit_id');
            $table->string('name', 255);
            $table->unsignedBigInteger('lead_employee_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['unit_id', 'name']);
            $table->foreign('unit_id')->references('id')->on('hr_units')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_teams');
    }
};
