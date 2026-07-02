<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trendyol_booster_cost_presets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('category_name', 180)->nullable();
            $table->decimal('commission_rate', 6, 2)->default(0);
            $table->decimal('cargo_cost', 12, 2)->default(0);
            $table->decimal('return_cargo_cost', 12, 2)->default(0);
            $table->decimal('packaging_cost', 12, 2)->default(0);
            $table->decimal('service_fee_rate', 6, 2)->default(0);
            $table->decimal('advertising_rate', 6, 2)->default(0);
            $table->decimal('return_rate', 6, 2)->default(0);
            $table->decimal('vat_rate', 6, 2)->default(20);
            $table->decimal('cost_vat_rate', 6, 2)->default(20);
            $table->decimal('expense_vat_rate', 6, 2)->default(20);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'name'], 'tr_booster_cost_preset_user_name_unique');
            $table->index(['user_id', 'category_name'], 'tr_booster_cost_preset_user_category_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trendyol_booster_cost_presets');
    }
};
