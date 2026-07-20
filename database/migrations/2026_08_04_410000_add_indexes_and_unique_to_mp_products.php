<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mp_products', function (Blueprint $table) {
            $table->index('source_user_id');
            $table->index('source_product_id');
            $table->index('clone_correlation_id');
            $table->unique(['user_id', 'source_product_id'], 'mp_products_user_source_unique');
        });
    }

    public function down(): void
    {
        Schema::table('mp_products', function (Blueprint $table) {
            $table->dropUnique('mp_products_user_source_unique');
            $table->dropIndex(['source_user_id']);
            $table->dropIndex(['source_product_id']);
            $table->dropIndex(['clone_correlation_id']);
        });
    }
};
