<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kargo Karşılaştırma Raporları
     * 
     * Her karşılaştırma işlemi için bir rapor kaydı oluşturulur.
     * Özet istatistikler ve metadata bu tabloda saklanır.
     */
    public function up(): void
    {
        Schema::create('cargo_reports', function (Blueprint $table) {
            $table->id();
            
            // Raporu oluşturan kullanıcı
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Rapor adı (Sürat Kargo 1, MNG Kargo - Ocak 2026, vb.)
            $table->string('name');
            
            // Kargo firması
            $table->string('cargo_company', 50)->nullable();
            
            // Rapor tarihi
            $table->date('report_date');
            
            // İstatistikler
            $table->unsignedInteger('total_orders')->default(0);
            $table->unsignedInteger('matched_orders')->default(0);
            $table->unsignedInteger('unmatched_orders')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            
            // Desi farklılıkları
            $table->decimal('total_expected_desi', 12, 2)->default(0);
            $table->decimal('total_actual_desi', 12, 2)->default(0);
            $table->decimal('total_desi_diff', 12, 2)->default(0);
            
            // Tutar farklılıkları
            $table->decimal('total_expected_tutar', 12, 2)->default(0);
            $table->decimal('total_actual_tutar', 12, 2)->default(0);
            $table->decimal('total_tutar_diff', 12, 2)->default(0);
            
            // Yüklenen dosya bilgileri
            $table->string('cargo_file_name')->nullable();
            $table->string('order_file_name')->nullable();
            
            // Durum
            $table->enum('status', ['processing', 'completed', 'archived'])->default('completed');
            
            // Notlar
            $table->text('notes')->nullable();
            
            $table->timestamps();
            
            // İndeksler
            $table->index(['user_id', 'report_date']);
            $table->index('cargo_company');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cargo_reports');
    }
};
