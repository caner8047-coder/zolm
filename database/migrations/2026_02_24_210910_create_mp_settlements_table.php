<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('mp_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('period_id')->constrained('mp_periods')->onDelete('cascade');
            $table->foreignId('order_id')->nullable()->constrained('mp_orders')->nullOnDelete();
            
            // Veri Alımı (Ingestion) Sütunları
            $table->string('transaction_type')->nullable()->comment('İşlem Tipi (Satış, İade, Kesinti vb.)');
            $table->string('order_number')->nullable()->index();
            $table->date('transaction_date')->nullable()->comment('İşlem Tarihi / Sipariş Tarihi');
            $table->date('settlement_date')->nullable()->comment('Teslim / Hakediş Tarihi');
            $table->date('due_date')->nullable()->comment('Vade Tarihi');
            
            // Finansal Değerler
            $table->decimal('commission_rate', 5, 2)->default(0)->comment('Komisyon Oranı');
            $table->decimal('ty_hakedis', 12, 2)->default(0)->comment('Platform (TY) Hakediş');
            $table->decimal('seller_hakedis', 12, 2)->default(0)->comment('Satıcı Hakediş - Fiili Yatan');
            $table->decimal('total_amount', 12, 2)->default(0)->comment('Sipariş Toplam Tutar');
            
            // Durum ve Doğrulama
            $table->boolean('is_reconciled')->default(false)->comment('Siparişle (MpOrder) mutabık kalındı mı?');
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mp_settlements');
    }
};
