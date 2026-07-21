<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_document_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legal_entity_id');
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('document_type_id');
            $table->unsignedBigInteger('requested_by');
            $table->date('due_date')->nullable();
            $table->text('message')->nullable();
            $table->string('status', 30)->default('pending');
            $table->unsignedBigInteger('fulfilled_document_id')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->foreign('legal_entity_id')->references('id')->on('legal_entities')->onDelete('cascade');
            $table->foreign('employee_id')->references('id')->on('hr_employees')->onDelete('cascade');
            $table->foreign('document_type_id')->references('id')->on('hr_document_types')->onDelete('restrict');
            $table->foreign('requested_by')->references('id')->on('users')->onDelete('restrict');

            $table->index(['employee_id', 'status']);
            $table->index(['legal_entity_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_document_requests');
    }
};
