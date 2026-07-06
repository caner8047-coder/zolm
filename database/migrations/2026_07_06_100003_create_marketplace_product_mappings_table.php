<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_product_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ad_account_id')->constrained('ad_accounts')->cascadeOnDelete();
            $table->string('marketplace_content_id', 100)->nullable();
            $table->string('marketplace_model_code', 100)->nullable();
            $table->string('barcode', 100)->nullable();
            $table->string('sku', 100)->nullable();
            $table->string('product_name_snapshot', 500);
            $table->foreignId('zolm_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('match_method', 20)->default('auto');
            $table->string('status', 20)->default('matched');
            $table->timestamps();
            $table->unique(['ad_account_id', 'marketplace_content_id'], 'ad_product_mapping_content_unique');
            $table->index(['user_id', 'barcode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_product_mappings');
    }
};
