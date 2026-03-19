<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mp_transactions', function (Blueprint $table) {
            $table->string('source_line_number', 150)->nullable()->after('document_number');
            $table->index(['period_id', 'source_line_number'], 'mp_trans_period_source_line_idx');
        });
    }

    public function down(): void
    {
        Schema::table('mp_transactions', function (Blueprint $table) {
            $table->dropIndex('mp_trans_period_source_line_idx');
            $table->dropColumn('source_line_number');
        });
    }
};
