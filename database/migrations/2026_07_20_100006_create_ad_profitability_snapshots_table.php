<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_profitability_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained('ad_campaigns')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('keyword_snapshot_id')->nullable()->constrained('ad_keyword_snapshots')->nullOnDelete();
            $table->foreignId('influencer_profile_id')->nullable()->constrained('influencer_profiles')->nullOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('net_revenue', 12, 2)->default(0);
            $table->decimal('product_cost', 12, 2)->default(0);
            $table->decimal('marketplace_commission', 12, 2)->default(0);
            $table->decimal('shipping_cost', 12, 2)->default(0);
            $table->decimal('packaging_cost', 12, 2)->default(0);
            $table->decimal('discount_cost', 12, 2)->default(0);
            $table->decimal('return_cost', 12, 2)->default(0);
            $table->decimal('ad_spend', 12, 2)->default(0);
            $table->decimal('influencer_cost', 12, 2)->default(0);
            $table->decimal('gross_profit', 12, 2)->default(0);
            $table->decimal('contribution_profit_before_ads', 12, 2)->default(0);
            $table->decimal('net_contribution_profit', 12, 2)->default(0);
            $table->decimal('net_margin_percent', 8, 4)->default(0);
            $table->decimal('break_even_roas', 8, 4)->nullable();
            $table->string('calculation_status', 20)->default('complete');
            $table->json('missing_inputs')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'campaign_id', 'period_start'], 'ad_prof_snap_user_camp_idx');
            $table->index(['user_id', 'product_id', 'period_start'], 'ad_prof_snap_user_prod_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_profitability_snapshots');
    }
};
