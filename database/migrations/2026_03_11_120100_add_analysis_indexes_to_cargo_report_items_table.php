<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cargo_report_items', function (Blueprint $table) {
            $table->index(['cargo_report_id', 'is_iade', 'is_parca_gonderi', 'error_type'], 'cri_report_type_error_idx');
            $table->index(['cargo_report_id', 'pazaryeri', 'magaza'], 'cri_report_marketplace_store_idx');
            $table->index(['pazaryeri', 'magaza'], 'cri_marketplace_store_idx');
        });
    }

    public function down(): void
    {
        Schema::table('cargo_report_items', function (Blueprint $table) {
            $table->dropIndex('cri_report_type_error_idx');
            $table->dropIndex('cri_report_marketplace_store_idx');
            $table->dropIndex('cri_marketplace_store_idx');
        });
    }
};
