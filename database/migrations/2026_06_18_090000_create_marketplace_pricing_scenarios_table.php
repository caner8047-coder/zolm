<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_pricing_scenarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mp_product_id')->nullable()->constrained('mp_products')->nullOnDelete();
            $table->foreignId('channel_listing_id')->nullable()->constrained('channel_listings')->nullOnDelete();
            $table->string('name', 160);
            $table->string('marketplace', 50)->default('trendyol');
            $table->string('currency', 3)->default('TRY');
            $table->json('input_json');
            $table->json('result_json');
            $table->string('status', 30)->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'created_at'], 'pricing_scenarios_user_created_idx');
            $table->index(['user_id', 'mp_product_id'], 'pricing_scenarios_user_product_idx');
            $table->index(['user_id', 'marketplace', 'status'], 'pricing_scenarios_user_market_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_pricing_scenarios');
    }
};
