<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pazaryeri Muhasebe — Denetim Logları (Audit Engine Sonuçları)
     * AuditEngine bulduğu tüm hata ve uyarıları buraya yazar.
     */
    public function up(): void
    {
        Schema::create('mp_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained('mp_periods')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('mp_orders')->cascadeOnDelete();

            // Kural tanımlayıcıları
            // STOPAJ_HATA, BAREM_ASIMI, KOMISYON_IADE, YANIK_MALIYET,
            // KAMPANYA_KOM, KDV_ASIMETRI, HAKEDIS_FARK,
            // OPERASYONEL_CEZA, COKLU_SEPET, EARŞIV_UYARI
            $table->string('rule_code', 30)->index();
            $table->enum('severity', ['critical', 'warning', 'info'])->default('warning');

            // Hata detayları
            $table->string('title');
            $table->text('description');

            // Sayısal karşılaştırma
            $table->decimal('expected_value', 12, 2)->nullable(); // Beklenen
            $table->decimal('actual_value', 12, 2)->nullable();   // Gerçekleşen
            $table->decimal('difference', 12, 2)->nullable();     // Fark (TL)

            // İşlem durumu
            $table->enum('status', ['open', 'acknowledged', 'resolved'])->default('open');
            $table->text('resolution_note')->nullable();

            $table->timestamps();

            // Performans indexleri
            $table->index(['period_id', 'rule_code'], 'mp_audit_period_rule_idx');
            $table->index(['period_id', 'severity'], 'mp_audit_period_sev_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mp_audit_logs');
    }
};
