<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mp_price_canary_stage_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->string('barcode')->index();
            $table->string('stage', 40)->default('single_product'); // single_product, three_products
            $table->string('status', 40)->default('pending')->index(); // pending, observing, technical_success, listing_verified, commercial_success, commercial_failure, approved_for_expansion, rejected_for_expansion
            $table->json('metrics')->nullable();
            $table->timestamp('observed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mp_price_canary_stage_results');
    }
};
