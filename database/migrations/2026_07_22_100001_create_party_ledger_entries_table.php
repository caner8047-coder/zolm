<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('party_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legal_entity_id')->nullable()->constrained('legal_entities')->nullOnDelete();
            // Finansal kayıt güvenliği: party silinirse kayıt yetim kalmasın diye restrict yerine
            // nullOnDelete kullanıyoruz; bakiye hesabı için kayıt korunmalı.
            $table->foreignId('party_id')->nullable()->constrained('parties')->nullOnDelete();
            $table->foreignId('crm_contact_id')->nullable()->constrained('crm_contacts')->nullOnDelete();
            $table->string('source_type', 50)->default('manual');
            $table->string('source_key', 191)->nullable();
            $table->string('document_type', 50);
            $table->string('document_number', 120)->nullable();
            $table->date('document_date');
            $table->date('due_date')->nullable();
            $table->string('description', 255)->nullable();
            $table->decimal('debit_amount', 15, 2)->default(0);
            $table->decimal('credit_amount', 15, 2)->default(0);
            $table->string('currency_code', 3)->default('TRY');
            $table->decimal('exchange_rate', 15, 6)->default(1);
            $table->decimal('debit_base_amount', 15, 2)->default(0);
            $table->decimal('credit_base_amount', 15, 2)->default(0);
            $table->string('status', 30)->default('posted');
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason', 255)->nullable();
            $table->json('meta_json')->nullable();
            $table->timestamps();

            // source_key nullable; SQLite/MySQL NULL'ları distinct kabul eder → güvenli.
            $table->unique(['user_id', 'source_key'], 'party_ledger_user_source_unique');
            $table->index(['user_id', 'party_id', 'status', 'document_date'], 'party_ledger_user_party_status_date_idx');
            $table->index(['user_id', 'legal_entity_id', 'status'], 'party_ledger_user_entity_status_idx');
            $table->index(['user_id', 'source_type', 'source_key'], 'party_ledger_user_source_key_idx');
            $table->index(['user_id', 'document_type', 'status'], 'party_ledger_user_doc_type_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('party_ledger_entries');
    }
};
