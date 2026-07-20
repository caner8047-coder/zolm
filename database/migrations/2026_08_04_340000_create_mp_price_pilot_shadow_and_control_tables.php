<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add optimistic locking and verification fields to mp_price_actions
        Schema::table('mp_price_actions', function (Blueprint $table) {
            $table->decimal('expected_current_price', 15, 2)->nullable()->after('old_price');
            $table->decimal('actual_current_price_at_execution', 15, 2)->nullable()->after('expected_current_price');
            $table->string('recommendation_version', 40)->nullable()->after('requested_price');
            $table->string('policy_version', 40)->nullable()->after('recommendation_version');
            $table->string('cost_snapshot_hash', 64)->nullable()->after('policy_version');
            $table->string('buybox_snapshot_hash', 64)->nullable()->after('cost_snapshot_hash');

            $table->string('verification_status', 40)->default('unverified')->index()->after('status');
            $table->decimal('observed_listing_price', 15, 2)->nullable()->after('verification_status');
            $table->timestamp('verified_at')->nullable()->after('observed_listing_price');
        });

        // mp_price_shadow_records: Shadow Mode predictions
        Schema::create('mp_price_shadow_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->string('barcode')->index();
            $table->timestamp('simulated_at')->index();

            $table->decimal('current_price', 15, 2)->default(0);
            $table->decimal('buybox_price', 15, 2)->nullable();
            $table->decimal('recommended_price', 15, 2)->nullable();
            $table->decimal('minimum_safe_price', 15, 2)->default(0);
            $table->decimal('expected_profit', 15, 2)->default(0);
            $table->decimal('expected_profit_margin', 8, 2)->default(0);

            $table->string('recommendation_type', 60);
            $table->string('risk_level', 30);
            $table->boolean('is_actionable')->default(false);
            $table->json('blocking_reasons')->nullable();

            $table->json('buybox_snapshot')->nullable();
            $table->json('cost_snapshot')->nullable();
            $table->json('policy_snapshot')->nullable();
            $table->string('simulated_action_type', 40)->default('price_change');
            $table->timestamps();

            $table->index(['store_id', 'barcode', 'simulated_at'], 'shadow_rec_store_barcode_idx');
        });

        // mp_price_shadow_evaluations: Evaluation of Shadow Mode accuracy after subsequent Buybox syncs
        Schema::create('mp_price_shadow_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shadow_record_id')->constrained('mp_price_shadow_records')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->string('barcode')->index();

            $table->timestamp('evaluated_at')->index();
            $table->decimal('actual_buybox_price_after', 15, 2)->nullable();
            $table->integer('actual_seller_rank_after')->nullable();

            $table->boolean('would_win_buybox')->default(false);
            $table->boolean('would_preserve_margin')->default(false);
            $table->boolean('was_unnecessary_drop')->default(false);
            $table->boolean('was_raise_opportunity_correct')->default(false);

            $table->decimal('price_deviation', 15, 2)->default(0);
            $table->integer('validity_duration_minutes')->default(0);
            $table->json('evaluation_notes')->nullable();
            $table->timestamps();
        });

        // mp_price_pilot_products: Store pilot product whitelist
        Schema::create('mp_price_pilot_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->string('barcode')->index();
            $table->string('mode', 40)->default('shadow'); // disabled, shadow, manual_pilot, canary_auto, paused, emergency_stopped
            $table->string('inclusion_reason')->nullable();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('added_at')->useCurrent();
            $table->timestamps();

            $table->unique(['store_id', 'barcode'], 'pilot_prod_store_barcode_unique');
        });

        // mp_price_manual_locks: Product-level manual price locks
        Schema::create('mp_price_manual_locks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->string('barcode')->index();
            $table->boolean('is_locked')->default(true);
            $table->string('lock_reason')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_indefinite')->default(true);
            $table->timestamps();

            $table->unique(['store_id', 'barcode'], 'manual_lock_store_barcode_unique');
        });

        // mp_price_policy_versions: Historic versions of store pricing policy
        Schema::create('mp_price_policy_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->string('version_hash', 40)->index();
            $table->json('policy_json');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('change_reason')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('effective_from')->useCurrent();
            $table->timestamp('effective_until')->nullable();
            $table->timestamps();
        });

        // mp_price_emergency_stops: Emergency stops (Global / Store level)
        Schema::create('mp_price_emergency_stops', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 40)->default('store')->index(); // global, tenant, store, category, product
            $table->foreignId('store_id')->nullable()->constrained('marketplace_stores')->cascadeOnDelete();
            $table->string('barcode')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->string('reason');
            $table->foreignId('stopped_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('stopped_at')->useCurrent();
            $table->timestamp('resumed_at')->nullable();
            $table->foreignId('resumed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mp_price_emergency_stops');
        Schema::dropIfExists('mp_price_policy_versions');
        Schema::dropIfExists('mp_price_manual_locks');
        Schema::dropIfExists('mp_price_pilot_products');
        Schema::dropIfExists('mp_price_shadow_evaluations');
        Schema::dropIfExists('mp_price_shadow_records');

        Schema::table('mp_price_actions', function (Blueprint $table) {
            $table->dropColumn([
                'expected_current_price',
                'actual_current_price_at_execution',
                'recommendation_version',
                'policy_version',
                'cost_snapshot_hash',
                'buybox_snapshot_hash',
                'verification_status',
                'observed_listing_price',
                'verified_at',
            ]);
        });
    }
};
