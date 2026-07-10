<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('e_documents', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            $table->unsignedBigInteger('sales_order_id')->nullable();
            $table->foreign('sales_order_id')->references('id')->on('sales_orders')->nullOnDelete();

            $table->string('document_type', 30)->default('e_archive'); // e_invoice, e_archive
            $table->uuid('uuid')->unique();
            $table->string('invoice_number', 50)->nullable(); // e.g. GIB2026000000101

            $table->string('status', 30)->default('draft'); // draft, sent, accepted, rejected, cancelled

            $table->string('pdf_path', 255)->nullable();
            $table->string('xml_path', 255)->nullable();
            $table->text('response_message')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('e_document_events', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('e_document_id');
            $table->foreign('e_document_id')->references('id')->on('e_documents')->cascadeOnDelete();

            $table->string('status_from', 30);
            $table->string('status_to', 30);
            $table->string('message', 500)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('e_document_events');
        Schema::dropIfExists('e_documents');
    }
};
