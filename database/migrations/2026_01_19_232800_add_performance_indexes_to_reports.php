<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations - Performans optimizasyonu için index'ler
     */
    public function up(): void
    {
        // Reports tablosuna index'ler
        Schema::table('reports', function (Blueprint $table) {
            // Tarih bazlı sorgular için
            $table->index('created_at', 'reports_created_at_index');
            
            // Status bazlı sorgular için
            $table->index('status', 'reports_status_index');
            
            // Composite index - en sık kullanılan sorgu kombinasyonu
            $table->index(['status', 'created_at'], 'reports_status_created_index');
            
            // User bazlı sorgular için
            $table->index('user_id', 'reports_user_id_index');
            
            // Profile bazlı sorgular için
            $table->index('profile_id', 'reports_profile_id_index');
        });

        // Report files tablosuna index'ler
        Schema::table('report_files', function (Blueprint $table) {
            // Report bazlı sorgular için
            $table->index('report_id', 'report_files_report_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropIndex('reports_created_at_index');
            $table->dropIndex('reports_status_index');
            $table->dropIndex('reports_status_created_index');
            $table->dropIndex('reports_user_id_index');
            $table->dropIndex('reports_profile_id_index');
        });

        Schema::table('report_files', function (Blueprint $table) {
            $table->dropIndex('report_files_report_id_index');
        });
    }
};
