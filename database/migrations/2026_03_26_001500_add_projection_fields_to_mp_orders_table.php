<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mp_orders', function (Blueprint $table) {
            $table->foreignId('store_id')
                ->nullable()
                ->after('period_id')
                ->constrained('marketplace_stores')
                ->nullOnDelete();

            $table->foreignId('legal_entity_id')
                ->nullable()
                ->after('store_id')
                ->constrained('legal_entities')
                ->nullOnDelete();

            $table->string('source_marketplace')
                ->nullable()
                ->after('status');

            $table->timestamp('projected_at')
                ->nullable()
                ->after('updated_at');

            $table->index(['store_id', 'projected_at'], 'mp_orders_store_projected_idx');
        });
    }

    public function down(): void
    {
        Schema::table('mp_orders', function (Blueprint $table) {
            $table->dropIndex('mp_orders_store_projected_idx');
            $table->dropConstrainedForeignId('legal_entity_id');
            $table->dropConstrainedForeignId('store_id');
            $table->dropColumn(['source_marketplace', 'projected_at']);
        });
    }
};
