<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trendyol_booster_collections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('color', 24)->default('slate');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'name'], 'booster_collection_user_name_unique');
        });

        Schema::create('trendyol_booster_collection_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('trendyol_booster_collection_id');
            $table->unsignedBigInteger('trendyol_booster_product_id');
            $table->text('note')->nullable();
            $table->timestamps();
            $table->foreign('trendyol_booster_collection_id', 'booster_collection_item_collection_fk')
                ->references('id')
                ->on('trendyol_booster_collections')
                ->cascadeOnDelete();
            $table->foreign('trendyol_booster_product_id', 'booster_collection_item_product_fk')
                ->references('id')
                ->on('trendyol_booster_products')
                ->cascadeOnDelete();
            $table->unique(['trendyol_booster_collection_id', 'trendyol_booster_product_id'], 'booster_collection_product_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trendyol_booster_collection_items');
        Schema::dropIfExists('trendyol_booster_collections');
    }
};
