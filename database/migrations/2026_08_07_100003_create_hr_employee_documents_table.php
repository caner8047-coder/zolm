<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_employee_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legal_entity_id');
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('document_type_id');
            $table->unsignedBigInteger('current_file_id')->nullable();
            $table->text('document_number_encrypted')->nullable();
            $table->string('document_number_hash', 64)->nullable();
            $table->string('document_number_last_four', 4)->nullable();
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('status', 30)->default('requested');
            $table->string('verification_status', 30)->default('not_required');
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('version_number')->default(1);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('legal_entity_id')->references('id')->on('legal_entities')->onDelete('cascade');
            $table->foreign('employee_id')->references('id')->on('hr_employees')->onDelete('cascade');
            $table->foreign('document_type_id')->references('id')->on('hr_document_types')->onDelete('restrict');

            $table->index(['employee_id', 'status']);
            $table->index(['legal_entity_id', 'status']);
            $table->index('expiry_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_employee_documents');
    }
};
