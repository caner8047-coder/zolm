<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('mp_products', 'product_type')) {
            Schema::table('mp_products', function (Blueprint $table) {
                $table->string('product_type', 20)
                    ->default('single')
                    ->after('status')
                    ->index()
                    ->comment('single, set, bundle');
            });
        }

        if (!Schema::hasColumn('mp_products', 'cost_source')) {
            Schema::table('mp_products', function (Blueprint $table) {
                $table->string('cost_source', 30)
                    ->default('manual')
                    ->after('product_type')
                    ->comment('manual, recipe, set');
            });
        }

        if (!Schema::hasColumn('mp_products', 'logistics_source')) {
            Schema::table('mp_products', function (Blueprint $table) {
                $table->string('logistics_source', 30)
                    ->default('manual')
                    ->after('cost_source')
                    ->comment('manual, set');
            });
        }

        if (!Schema::hasTable('product_sets')) {
            Schema::create('product_sets', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('parent_mp_product_id')
                    ->constrained('mp_products')
                    ->cascadeOnDelete();
                $table->string('name')->nullable();
                $table->string('status', 20)->default('active')->index();
                $table->string('cost_mode', 30)->default('sum_components');
                $table->string('logistics_mode', 30)->default('sum_components');
                $table->json('totals_cache_json')->nullable();
                $table->timestamp('calculated_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique('parent_mp_product_id', 'product_sets_parent_unique');
                $table->index(['user_id', 'status'], 'product_sets_user_status_idx');
            });
        }

        if (!Schema::hasTable('product_set_items')) {
            Schema::create('product_set_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_set_id')
                    ->constrained('product_sets')
                    ->cascadeOnDelete();
                $table->foreignId('component_mp_product_id')
                    ->constrained('mp_products')
                    ->cascadeOnDelete();
                $table->decimal('quantity', 10, 3)->default(1);
                $table->boolean('include_cost')->default(true);
                $table->boolean('include_packaging')->default(true);
                $table->boolean('include_logistics')->default(true);
                $table->decimal('cost_override', 12, 2)->nullable();
                $table->decimal('cargo_cost_override', 12, 2)->nullable();
                $table->decimal('desi_override', 8, 2)->nullable();
                $table->unsignedInteger('pieces_override')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['product_set_id', 'component_mp_product_id'], 'product_set_items_unique_component');
                $table->index('component_mp_product_id', 'product_set_items_component_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_set_items');
        Schema::dropIfExists('product_sets');

        Schema::table('mp_products', function (Blueprint $table) {
            foreach (['logistics_source', 'cost_source', 'product_type'] as $column) {
                if (Schema::hasColumn('mp_products', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
