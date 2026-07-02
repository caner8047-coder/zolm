<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trendyol_booster_campaign_scenarios', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('trendyol_booster_product_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('campaign_type', 40)->default('discount');
            $table->decimal('discount_rate', 6, 2)->default(0);
            $table->decimal('campaign_price', 12, 2)->default(0);
            $table->decimal('commission_discount_rate', 6, 2)->default(0);
            $table->decimal('advertising_rate', 6, 2)->default(0);
            $table->unsignedInteger('expected_units')->default(1);
            $table->decimal('current_net_profit', 12, 2)->default(0);
            $table->decimal('campaign_net_profit', 12, 2)->default(0);
            $table->decimal('profit_delta_per_unit', 12, 2)->default(0);
            $table->decimal('total_profit_delta', 14, 2)->default(0);
            $table->decimal('campaign_margin_percent', 8, 2)->default(0);
            $table->string('decision_status', 40)->default('watch');
            $table->string('decision_note', 500)->nullable();
            $table->json('simulation_json')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'decision_status'], 'tr_booster_campaign_user_decision_idx');
            $table->index(['trendyol_booster_product_id', 'created_at'], 'tr_booster_campaign_product_created_idx');
            $table->foreign('trendyol_booster_product_id', 'tr_booster_campaign_product_fk')
                ->references('id')
                ->on('trendyol_booster_products')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trendyol_booster_campaign_scenarios');
    }
};
