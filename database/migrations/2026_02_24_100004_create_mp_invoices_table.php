<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pazaryeri Muhasebe — Fatura Kayıtları
     * Toplu faturalar — sadece KDV mahsuplaşması için kullanılır.
     */
    public function up(): void
    {
        Schema::create('mp_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained('mp_periods')->cascadeOnDelete();

            // Fatura bilgileri
            $table->string('invoice_number', 80)->index();
            $table->date('invoice_date');

            // Tip: Komisyon Faturası, Kargo Faturası, Hizmet Faturası
            $table->string('invoice_type', 50);

            // Tutarlar
            $table->decimal('net_amount', 12, 2)->default(0);   // KDV hariç
            $table->decimal('vat_amount', 12, 2)->default(0);   // KDV tutarı
            $table->decimal('vat_rate', 4, 2)->default(20);     // KDV oranı (genelde %20)
            $table->decimal('total_amount', 12, 2)->default(0); // KDV dahil toplam

            // Açıklama
            $table->text('description')->nullable();

            $table->timestamps();

            $table->index(['period_id', 'invoice_type'], 'mp_inv_period_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mp_invoices');
    }
};
