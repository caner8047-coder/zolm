<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mp_price_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->foreignId('marketplace_product_id')->nullable()->constrained('mp_products')->nullOnDelete();
            $table->foreignId('mp_buybox_listing_id')->nullable()->constrained('mp_buybox_listings')->nullOnDelete();
            $table->string('barcode')->index();
            $table->string('listing_id')->nullable()->index();

            $table->decimal('current_price', 15, 2)->default(0);
            $table->decimal('buybox_price', 15, 2)->nullable();
            $table->decimal('second_price', 15, 2)->nullable();
            $table->decimal('third_price', 15, 2)->nullable();

            $table->decimal('recommended_price', 15, 2)->nullable();
            $table->decimal('minimum_safe_price', 15, 2)->default(0);
            $table->decimal('maximum_allowed_price', 15, 2)->nullable();

            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->decimal('commission_amount', 15, 2)->default(0);
            $table->decimal('cargo_cost', 15, 2)->default(0);
            $table->decimal('vat_amount', 15, 2)->default(0);
            $table->decimal('service_cost', 15, 2)->default(0);
            $table->decimal('other_cost', 15, 2)->default(0);

            $table->decimal('expected_profit', 15, 2)->default(0);
            $table->decimal('expected_profit_margin', 8, 2)->default(0);
            $table->decimal('current_profit', 15, 2)->default(0);
            $table->decimal('current_profit_margin', 8, 2)->default(0);
            $table->decimal('price_difference', 15, 2)->default(0);

            $table->string('recommendation_type', 60)->index();
            $table->string('risk_level', 30)->default('low')->index(); // low, medium, high, blocked
            $table->json('reason_codes')->nullable();
            $table->json('calculation_snapshot')->nullable();

            $table->string('status', 40)->default('new')->index(); // new, reviewed, approved, rejected, queued, sent, success, partial_success, failed, expired, cancelled, rolled_back
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'barcode'], 'mpr_store_barcode_unique');
            $table->index(['store_id', 'recommendation_type'], 'mpr_store_rec_type_idx');
            $table->index(['store_id', 'status'], 'mpr_store_status_idx');
        });

        Schema::create('mp_price_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->foreignId('recommendation_id')->nullable()->constrained('mp_price_recommendations')->nullOnDelete();
            $table->string('barcode')->index();

            $table->decimal('old_price', 15, 2)->default(0);
            $table->decimal('requested_price', 15, 2)->default(0);
            $table->decimal('confirmed_price', 15, 2)->nullable();

            $table->string('action_type', 40)->default('price_change'); // price_change, rollback
            $table->string('trigger_type', 40)->default('manual'); // manual, bulk_manual, rule_based, automatic
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->foreignId('integration_push_run_id')->nullable()->constrained('integration_push_runs')->nullOnDelete();
            $table->string('batch_request_id')->nullable()->index();
            $table->string('status', 40)->default('pending')->index(); // pending, processing, success, partial_success, failed, timeout, rolled_back, cancelled

            $table->string('failure_code', 80)->nullable();
            $table->text('failure_message')->nullable();

            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();

            $table->timestamp('completed_at')->nullable();
            $table->timestamp('rolled_back_at')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'status'], 'mpa_store_status_idx');
            $table->index(['store_id', 'barcode', 'created_at'], 'mpa_store_barcode_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mp_price_actions');
        Schema::dropIfExists('mp_price_recommendations');
    }
};
