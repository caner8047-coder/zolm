<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_document_types', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legal_entity_id');
            $table->string('code', 100);
            $table->string('name', 255);
            $table->string('category', 50);
            $table->text('description')->nullable();
            $table->string('sensitivity', 30)->default('standard');
            $table->boolean('requires_expiry_date')->default(false);
            $table->boolean('requires_issue_date')->default(false);
            $table->boolean('requires_document_number')->default(false);
            $table->json('allowed_mime_types')->nullable();
            $table->unsignedInteger('max_file_size_kb')->nullable();
            $table->unsignedInteger('default_validity_months')->nullable();
            $table->boolean('is_mandatory')->default(false);
            $table->boolean('employee_can_upload')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['legal_entity_id', 'code']);
            $table->foreign('legal_entity_id')->references('id')->on('legal_entities')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_document_types');
    }
};
