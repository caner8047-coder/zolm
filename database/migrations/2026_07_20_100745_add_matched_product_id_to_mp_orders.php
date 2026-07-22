<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mp_orders', function (Blueprint $table) {
            // Eşleştirilen ürün FK (nullable — henüz eşleşmemiş olabilir)
            $table->unsignedBigInteger('matched_product_id')->nullable()->after('stock_code');

            // Eşleştirme güven skoru: 0.0–1.0
            $table->decimal('match_confidence', 4, 3)->nullable()->after('matched_product_id');

            // Eşleştirme yöntemi: exact, model_code, prefix, fuzzy, manual
            $table->string('match_method', 20)->nullable()->after('match_confidence');

            $table->index('matched_product_id');
        });
    }

    public function down(): void
    {
        Schema::table('mp_orders', function (Blueprint $table) {
            $table->dropIndex(['matched_product_id']);
            $table->dropColumn(['matched_product_id', 'match_confidence', 'match_method']);
        });
    }
};
