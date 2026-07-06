<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_campaign_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('ad_campaigns')->cascadeOnDelete();
            $table->foreignId('zolm_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('marketplace_content_id', 100)->nullable();
            $table->string('marketplace_model_code', 100)->nullable();
            $table->string('product_name_snapshot', 500);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['campaign_id', 'marketplace_content_id'], 'ad_campaign_product_content_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_campaign_products');
    }
};
