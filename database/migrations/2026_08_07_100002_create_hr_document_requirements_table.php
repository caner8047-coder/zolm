<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_document_requirements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legal_entity_id');
            $table->unsignedBigInteger('document_type_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('position_id')->nullable();
            $table->string('employment_type', 20)->nullable();
            $table->boolean('is_required')->default(true);
            $table->boolean('required_on_hire')->default(false);
            $table->unsignedInteger('due_days_after_hire')->nullable();
            $table->unsignedInteger('reminder_days_before_expiry')->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('legal_entity_id')->references('id')->on('legal_entities')->onDelete('cascade');
            $table->foreign('document_type_id')->references('id')->on('hr_document_types')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_document_requirements');
    }
};
