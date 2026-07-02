<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trendyol_booster_cost_recommendations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('trendyol_booster_product_id')->nullable();
            $table->char('source_url_hash', 64)->index();
            $table->string('category_name', 180)->nullable();
            $table->decimal('seller_score', 5, 2)->nullable();
            $table->unsignedTinyInteger('seller_level')->nullable();
            $table->decimal('commission_rate', 6, 2)->nullable();
            $table->string('commission_source', 60)->nullable();
            $table->decimal('commission_confidence', 5, 2)->default(0);
            $table->decimal('estimated_desi', 8, 2)->nullable();
            $table->unsignedSmallInteger('billable_desi')->nullable();
            $table->string('desi_source', 60)->nullable();
            $table->decimal('desi_confidence', 5, 2)->default(0);
            $table->string('cargo_company', 80)->nullable();
            $table->decimal('cargo_cost_net', 10, 2)->nullable();
            $table->decimal('cargo_cost_gross', 10, 2)->nullable();
            $table->string('cargo_source', 60)->nullable();
            $table->decimal('cargo_confidence', 5, 2)->default(0);
            $table->json('evidence')->nullable();
            $table->json('scenarios')->nullable();
            $table->timestamp('estimated_at');
            $table->timestamps();

            $table->index(
                ['user_id', 'trendyol_booster_product_id', 'estimated_at'],
                'tr_booster_cost_rec_product_idx'
            );
            $table->foreign('user_id', 'tr_cost_rec_user_fk')
                ->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('trendyol_booster_product_id', 'tr_cost_rec_product_fk')
                ->references('id')->on('trendyol_booster_products')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trendyol_booster_cost_recommendations');
    }
};
