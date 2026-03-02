<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pazaryeri Muhasebe — Finansal Kurallar (Parametrik)
     * Barem, desi fiyatları, stopaj oranı, ağır kargo cezaları vb.
     * Tarih bazlı geçerlilik ile yıllar arası değişiklikleri destekler.
     */
    public function up(): void
    {
        Schema::create('mp_financial_rules', function (Blueprint $table) {
            $table->id();

            // Kural tanımlayıcıları
            $table->string('rule_key', 80);           // stopaj_rate, barem_limit, tex_desi_0_2...
            $table->string('rule_value', 50);          // 0.01, 300, 77.54...
            $table->string('category', 50)->nullable(); // Kargo firması veya ürün kategorisi
            $table->string('marketplace', 30)->default('trendyol');

            // Geçerlilik aralığı
            $table->date('valid_from');
            $table->date('valid_to')->nullable(); // null = hâlâ geçerli

            // Açıklama
            $table->string('description')->nullable();

            $table->timestamps();

            // Arama indexi
            $table->index(['rule_key', 'category', 'valid_from'], 'mp_rules_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mp_financial_rules');
    }
};
