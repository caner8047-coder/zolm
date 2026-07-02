<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trendyol_booster_commission_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('marketplace', 40)->default('trendyol');
            $table->string('category_name', 180);
            $table->string('sub_category_name', 180)->nullable();
            $table->string('product_group', 240)->nullable();
            $table->unsignedSmallInteger('maturity_days')->default(21);
            $table->decimal('commission_rate', 6, 2)->default(0);
            $table->decimal('level_5_rate', 6, 2)->nullable();
            $table->decimal('level_4_rate', 6, 2)->nullable();
            $table->decimal('level_3_rate', 6, 2)->nullable();
            $table->string('special_group', 180)->nullable();
            $table->string('source', 160)->default('manual');
            $table->date('effective_from')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'category_name'], 'tr_booster_comm_user_category_idx');
            $table->index(['user_id', 'commission_rate'], 'tr_booster_comm_user_rate_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trendyol_booster_commission_rates');
    }
};
