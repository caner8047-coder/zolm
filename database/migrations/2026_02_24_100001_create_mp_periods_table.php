<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pazaryeri Muhasebe — Dönem Tablosu
     * Her Excel import bir döneme bağlanır. Aylık/yıllık raporlama birimi.
     */
    public function up(): void
    {
        Schema::create('mp_periods', function (Blueprint $table) {
            $table->id();

            // Kullanıcı ve mağaza
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('seller_id', 50)->nullable()->index(); // Gelecekte çoklu mağaza desteği

            // Dönem bilgisi
            $table->smallInteger('year');        // 2025, 2026...
            $table->tinyInteger('month');         // 1-12
            $table->string('marketplace', 30)->default('trendyol'); // trendyol, hepsiburada...

            // İşlem durumu
            $table->enum('status', ['draft', 'processing', 'completed', 'error'])->default('draft');
            $table->json('import_files')->nullable();   // Yüklenen dosya adları ve tarihleri
            $table->json('summary_cache')->nullable();  // Dashboard KPI önbellek
            $table->text('notes')->nullable();

            // İstatistikler (hızlı erişim)
            $table->integer('total_orders')->default(0);
            $table->integer('total_returns')->default(0);
            $table->integer('total_cancellations')->default(0);
            $table->integer('total_audit_errors')->default(0);

            $table->timestamps();

            // Benzersiz dönem: aynı yıl/ay + marketplace + seller
            $table->unique(['year', 'month', 'marketplace', 'seller_id'], 'mp_periods_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mp_periods');
    }
};
