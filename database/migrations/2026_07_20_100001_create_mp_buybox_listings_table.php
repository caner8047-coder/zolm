<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mp_buybox_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->string('barcode')->index();
            $table->string('listing_id')->nullable()->index();
            $table->integer('seller_rank')->nullable();
            $table->decimal('buybox_price', 15, 2)->nullable();
            $table->decimal('seller_price', 15, 2)->nullable();
            $table->decimal('second_price', 15, 2)->nullable();
            $table->decimal('third_price', 15, 2)->nullable();
            $table->boolean('has_multiple_sellers')->default(false);
            $table->json('raw_payload')->nullable();
            $table->timestamp('retrieved_at')->useCurrent();
            $table->timestamps();

            $table->unique(['store_id', 'barcode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mp_buybox_listings');
    }
};
