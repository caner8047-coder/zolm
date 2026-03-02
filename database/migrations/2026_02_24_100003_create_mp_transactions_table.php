<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pazaryeri Muhasebe — Cari Hesap Ekstresi (Transactions)
     * Borç/Alacak çift taraflı muhasebe kaydı. Mutabakat için kullanılır.
     */
    public function up(): void
    {
        Schema::create('mp_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained('mp_periods')->cascadeOnDelete();

            // Tanımlayıcılar
            $table->date('transaction_date');
            $table->string('document_number', 80)->nullable()->index(); // Belge/Fatura No
            $table->string('order_number', 50)->nullable()->index();    // İlişkili sipariş no

            // İşlem tipi (Trendyol'dan gelen)
            // Komisyon Faturası, Kargo Taşıma Faturası, İade Kargo Bedeli,
            // Ağır Kargo Taşıma Bedeli, Barem Farkı, Komisyon İadesi (Alacak)
            $table->string('transaction_type', 80);

            // Açıklama
            $table->text('description')->nullable();

            // Çift taraflı muhasebe
            $table->decimal('debt', 12, 2)->default(0);    // Borç (-) satıcıdan kesilen
            $table->decimal('credit', 12, 2)->default(0);  // Alacak (+) satıcıya ödenen
            $table->decimal('balance', 12, 2)->nullable();  // Bakiye (varsa)

            // Mutabakat durumu
            $table->boolean('is_matched')->default(false)->index(); // Siparişle eşleştirildi mi?

            $table->timestamps();

            // Performans indexleri
            $table->index(['period_id', 'transaction_type'], 'mp_trans_period_type_idx');
            $table->index(['order_number', 'transaction_type'], 'mp_trans_order_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mp_transactions');
    }
};
