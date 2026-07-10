<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();

            // Tenant izolasyonu
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            // Fiş bağlantısı
            $table->unsignedBigInteger('journal_entry_id');
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->cascadeOnDelete();

            // Hesap bağlantısı
            $table->unsignedBigInteger('account_id');
            $table->foreign('account_id')->references('id')->on('accounts')->restrictOnDelete();

            // Çift taraflı muhasebe — her satırda sadece biri pozitif olur
            $table->decimal('debit_amount', 14, 2)->default(0);
            $table->decimal('credit_amount', 14, 2)->default(0);

            // Döviz desteği
            $table->string('currency_code', 3)->default('TRY');
            $table->decimal('exchange_rate', 12, 6)->default(1);
            $table->decimal('debit_base_amount', 14, 2)->default(0);   // TRY karşılığı
            $table->decimal('credit_base_amount', 14, 2)->default(0);  // TRY karşılığı

            // Party bağlamı (satır bazında opsiyonel)
            // FK ayrı migration'da eklenir (parties tablosu önce gelir).
            $table->unsignedBigInteger('party_id')->nullable();

            $table->integer('sort_order')->default(0);
            $table->string('description', 500)->nullable();
            $table->json('meta_json')->nullable();

            $table->timestamps();

            $table->index(['journal_entry_id', 'sort_order']);
            $table->index(['user_id', 'account_id']);
            $table->index(['user_id', 'party_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_lines');
    }
};
