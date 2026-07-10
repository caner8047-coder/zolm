<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receivables', function (Blueprint $table) {
            $table->id();

            // Tenant izolasyonu
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            // Cari (Party) bağlantısı
            $table->unsignedBigInteger('party_id');
            // legal_entity ve journal_entry bağlantıları
            $table->unsignedBigInteger('legal_entity_id')->nullable();
            $table->unsignedBigInteger('journal_entry_id')->nullable();

            $table->string('document_number', 120)->nullable();
            $table->date('document_date');
            $table->date('due_date')->nullable();

            $table->decimal('amount', 14, 2);
            $table->decimal('paid_amount', 14, 2)->default(0.00);

            $table->string('currency_code', 3)->default('TRY');
            $table->decimal('exchange_rate', 12, 6)->default(1.000000);

            $table->string('status', 30)->default('open'); // open, paid, partially_paid, voided
            $table->string('description', 500)->nullable();

            $table->timestamps();

            $table->index(['user_id', 'party_id']);
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'document_date']);
        });

        Schema::create('payables', function (Blueprint $table) {
            $table->id();

            // Tenant izolasyonu
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            // Cari (Party) bağlantısı
            $table->unsignedBigInteger('party_id');
            // legal_entity ve journal_entry bağlantıları
            $table->unsignedBigInteger('legal_entity_id')->nullable();
            $table->unsignedBigInteger('journal_entry_id')->nullable();

            $table->string('document_number', 120)->nullable();
            $table->date('document_date');
            $table->date('due_date')->nullable();

            $table->decimal('amount', 14, 2);
            $table->decimal('paid_amount', 14, 2)->default(0.00);

            $table->string('currency_code', 3)->default('TRY');
            $table->decimal('exchange_rate', 12, 6)->default(1.000000);

            $table->string('status', 30)->default('open'); // open, paid, partially_paid, voided
            $table->string('description', 500)->nullable();

            $table->timestamps();

            $table->index(['user_id', 'party_id']);
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'document_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payables');
        Schema::dropIfExists('receivables');
    }
};
