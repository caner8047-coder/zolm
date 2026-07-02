<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trendyol_booster_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trendyol_booster_product_id')->constrained('trendyol_booster_products')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('sale_price', 12, 2)->default(0);
            $table->decimal('previous_sale_price', 12, 2)->nullable();
            $table->decimal('price_delta', 12, 2)->default(0);
            $table->decimal('price_delta_percent', 8, 2)->default(0);
            $table->string('stock_status', 40)->default('unknown');
            $table->string('availability', 120)->nullable();
            $table->unsignedTinyInteger('opportunity_score')->default(0);
            $table->string('decision_status', 30)->default('watch');
            $table->decimal('net_profit', 12, 2)->default(0);
            $table->decimal('profit_margin_percent', 8, 2)->default(0);
            $table->json('raw_payload')->nullable();
            $table->timestamp('checked_at')->useCurrent();
            $table->timestamps();

            $table->index(['user_id', 'checked_at'], 'tr_booster_snap_user_checked_idx');
            $table->index(['trendyol_booster_product_id', 'checked_at'], 'tr_booster_snap_product_checked_idx');
            $table->index(['user_id', 'decision_status'], 'tr_booster_snap_user_decision_idx');
            $table->index(['user_id', 'stock_status'], 'tr_booster_snap_user_stock_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trendyol_booster_snapshots');
    }
};
