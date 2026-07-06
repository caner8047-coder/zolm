<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('influencer_product_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('ad_campaigns')->cascadeOnDelete();
            $table->foreignId('influencer_profile_id')->constrained('influencer_profiles')->cascadeOnDelete();
            $table->foreignId('zolm_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('product_name_snapshot', 500);
            $table->date('sale_date');
            $table->decimal('revenue_total', 12, 2)->default(0);
            $table->integer('sales_total')->default(0);
            $table->integer('link_visits_that_day')->nullable();
            $table->foreignId('import_batch_id')->nullable()->constrained('ad_import_batches')->nullOnDelete();
            $table->timestamps();
            $table->index(['campaign_id', 'influencer_profile_id', 'sale_date'], 'inf_prod_sale_camp_prof_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('influencer_product_sales');
    }
};
