<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collections', function (Blueprint $table) {
            $table->id();

            // Tenant izolasyonu
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            // Cari bağlantısı
            $table->unsignedBigInteger('party_id');
            $table->unsignedBigInteger('legal_entity_id')->nullable();
            $table->unsignedBigInteger('journal_entry_id')->nullable();

            $table->date('collection_date');
            $table->decimal('amount', 14, 2);
            $table->string('currency_code', 3)->default('TRY');
            $table->decimal('exchange_rate', 12, 6)->default(1.000000);

            $table->string('payment_method', 50)->default('cash'); // cash, bank, credit_card
            $table->string('description', 500)->nullable();
            $table->string('status', 30)->default('posted'); // posted, voided

            $table->timestamps();

            $table->index(['user_id', 'party_id']);
            $table->index(['user_id', 'collection_date']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            // Tenant izolasyonu
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            // Cari bağlantısı
            $table->unsignedBigInteger('party_id');
            $table->unsignedBigInteger('legal_entity_id')->nullable();
            $table->unsignedBigInteger('journal_entry_id')->nullable();

            $table->date('payment_date');
            $table->decimal('amount', 14, 2);
            $table->string('currency_code', 3)->default('TRY');
            $table->decimal('exchange_rate', 12, 6)->default(1.000000);

            $table->string('payment_method', 50)->default('cash'); // cash, bank, credit_card
            $table->string('description', 500)->nullable();
            $table->string('status', 30)->default('posted'); // posted, voided

            $table->timestamps();

            $table->index(['user_id', 'party_id']);
            $table->index(['user_id', 'payment_date']);
        });

        Schema::create('receivable_allocations', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            $table->unsignedBigInteger('receivable_id');
            $table->foreign('receivable_id')->references('id')->on('receivables')->cascadeOnDelete();

            $table->unsignedBigInteger('collection_id');
            $table->foreign('collection_id')->references('id')->on('collections')->cascadeOnDelete();

            $table->decimal('amount', 14, 2);
            $table->timestamps();

            $table->index(['user_id', 'receivable_id']);
            $table->index(['user_id', 'collection_id']);
        });

        Schema::create('payable_allocations', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            $table->unsignedBigInteger('payable_id');
            $table->foreign('payable_id')->references('id')->on('payables')->cascadeOnDelete();

            $table->unsignedBigInteger('payment_id');
            $table->foreign('payment_id')->references('id')->on('payments')->cascadeOnDelete();

            $table->decimal('amount', 14, 2);
            $table->timestamps();

            $table->index(['user_id', 'payable_id']);
            $table->index(['user_id', 'payment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payable_allocations');
        Schema::dropIfExists('receivable_allocations');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('collections');
    }
};
