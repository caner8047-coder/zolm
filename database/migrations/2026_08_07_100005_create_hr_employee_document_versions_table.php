<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_employee_document_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_document_id');
            $table->unsignedBigInteger('file_id');
            $table->unsignedInteger('version_number');
            $table->unsignedBigInteger('uploaded_by');
            $table->text('change_reason')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('employee_document_id')->references('id')->on('hr_employee_documents')->onDelete('cascade');
            $table->foreign('file_id')->references('id')->on('hr_files')->onDelete('restrict');
            $table->foreign('uploaded_by')->references('id')->on('users')->onDelete('restrict');

            $table->index(['employee_document_id', 'version_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_employee_document_versions');
    }
};
