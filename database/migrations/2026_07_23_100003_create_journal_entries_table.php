<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();

            // Tenant izolasyonu
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            // Şirket bağlamı (opsiyonel)
            $table->unsignedBigInteger('legal_entity_id')->nullable();
            $table->foreign('legal_entity_id')->references('id')->on('legal_entities')->nullOnDelete();

            // Party bağlamı (opsiyonel — cari hareketi ise)
            // Not: parties tablosu (2026_07_21) önce migrate edilmeli.
            // FK ayrı migration'da eklenir; burada sadece sütun tanımlanır.
            $table->unsignedBigInteger('party_id')->nullable();

            // Fiş tipi
            $table->string('entry_type', 40)->default('manual');
            // manual / sales_invoice / purchase_invoice / collection / payment
            // cash_in / cash_out / bank_transfer / marketplace_settlement / adjustment

            // Kaynak izlenebilirlik (idempotent)
            $table->string('source_type', 60)->nullable();  // manual / party_ledger / channel_order / mp_settlement vb.
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_key', 191)->nullable();  // unique idempotency key

            // Fiş bilgileri
            $table->string('reference_number', 120)->nullable(); // Belge no
            $table->date('entry_date');                         // Fiş tarihi
            $table->date('due_date')->nullable();               // Vade
            $table->string('description', 500)->nullable();
            $table->string('currency_code', 3)->default('TRY');
            $table->decimal('exchange_rate', 12, 6)->default(1);

            // Durum
            $table->string('status', 20)->default('posted');
            // posted / voided / draft

            // İptal bilgisi
            $table->timestamp('voided_at')->nullable();
            $table->unsignedBigInteger('voided_by')->nullable();
            $table->string('void_reason', 500)->nullable();
            $table->foreign('voided_by')->references('id')->on('users')->nullOnDelete();

            $table->timestamp('posted_at')->nullable();
            $table->json('meta_json')->nullable();

            $table->timestamps();

            // Idempotency: user_id + source_key unique
            $table->unique(['user_id', 'source_key'], 'journal_entries_user_source_key_unique');
            $table->index(['user_id', 'entry_date']);
            $table->index(['user_id', 'entry_type', 'status']);
            $table->index(['user_id', 'party_id']);
            $table->index(['user_id', 'legal_entity_id']);
            $table->index(['user_id', 'source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
