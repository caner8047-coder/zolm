<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE cargo_report_items
            MODIFY COLUMN error_type ENUM(
                'none',
                'referans_eksik',
                'desi_eksik',
                'desi_fazla',
                'tutar_eksik',
                'tutar_fazla',
                'parca_eksik',
                'parca_fazla',
                'eslesmedi'
            ) NOT NULL DEFAULT 'none'
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE cargo_report_items
            MODIFY COLUMN error_type ENUM(
                'none',
                'desi_eksik',
                'desi_fazla',
                'tutar_eksik',
                'tutar_fazla',
                'parca_eksik',
                'parca_fazla',
                'eslesmedi'
            ) NOT NULL DEFAULT 'none'
        ");
    }
};
