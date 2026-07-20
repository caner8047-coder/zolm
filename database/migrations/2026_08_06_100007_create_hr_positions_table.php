<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_positions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legal_entity_id');
            $table->unsignedBigInteger('department_id')->nullable();
            $table->string('title', 255);
            $table->string('code', 50);
            $table->unsignedInteger('level')->default(1);
            $table->decimal('min_salary', 12, 2)->nullable();
            $table->decimal('max_salary', 12, 2)->nullable();
            $table->text('job_description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['legal_entity_id', 'code']);
            $table->foreign('legal_entity_id')->references('id')->on('legal_entities')->onDelete('cascade');
            $table->foreign('department_id')->references('id')->on('hr_departments')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_positions');
    }
};
