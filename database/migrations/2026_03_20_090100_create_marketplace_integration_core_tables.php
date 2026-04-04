<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_stores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legal_entity_id')->constrained('legal_entities')->cascadeOnDelete();
            $table->string('marketplace', 50);
            $table->string('store_name', 150);
            $table->string('store_code', 100)->nullable();
            $table->string('seller_id', 100)->nullable();
            $table->string('status', 30)->default('draft');
            $table->string('timezone', 64)->default('Europe/Istanbul');
            $table->string('currency', 3)->default('TRY');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['marketplace', 'seller_id'], 'marketplace_stores_marketplace_seller_unique');
            $table->index(['user_id', 'marketplace'], 'marketplace_stores_user_marketplace_idx');
            $table->index(['legal_entity_id', 'is_active'], 'marketplace_stores_entity_active_idx');
        });

        Schema::create('integration_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->string('provider', 50);
            $table->string('auth_type', 50)->default('api_key_secret');
            $table->longText('credentials_encrypted')->nullable();
            $table->string('webhook_secret', 120)->nullable();
            $table->string('webhook_url', 255)->nullable();
            $table->string('api_base_url', 255)->nullable();
            $table->string('status', 30)->default('draft');
            $table->timestamp('last_verified_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique('store_id');
            $table->index(['provider', 'status'], 'integration_connections_provider_status_idx');
        });

        Schema::create('integration_sync_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->unsignedInteger('orders_poll_minutes')->default(15);
            $table->unsignedInteger('finance_poll_minutes')->default(60);
            $table->unsignedInteger('products_poll_minutes')->default(360);
            $table->string('backfill_mode', 30)->default('30_days');
            $table->unsignedInteger('backfill_days')->nullable()->default(30);
            $table->timestamp('backfill_custom_from')->nullable();
            $table->timestamp('backfill_custom_to')->nullable();
            $table->boolean('orders_enabled')->default(true);
            $table->boolean('finance_enabled')->default(true);
            $table->boolean('products_enabled')->default(true);
            $table->boolean('webhook_enabled')->default(true);
            $table->boolean('price_push_enabled')->default(false);
            $table->boolean('stock_push_enabled')->default(false);
            $table->boolean('auto_match_enabled')->default(true);
            $table->boolean('barcode_fallback_enabled')->default(true);
            $table->boolean('strict_unique_match_enabled')->default(true);
            $table->boolean('nightly_repair_sync_enabled')->default(true);
            $table->unsignedTinyInteger('max_parallel_jobs')->default(1);
            $table->unsignedTinyInteger('request_jitter_seconds')->default(5);
            $table->json('extra_settings')->nullable();
            $table->timestamps();

            $table->unique('store_id');
        });

        Schema::create('integration_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->string('sync_type', 50);
            $table->string('trigger_type', 30)->default('manual');
            $table->string('status', 30)->default('queued');
            $table->string('cursor_before', 255)->nullable();
            $table->string('cursor_after', 255)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedInteger('items_received')->default(0);
            $table->unsignedInteger('items_created')->default(0);
            $table->unsignedInteger('items_updated')->default(0);
            $table->unsignedInteger('items_skipped')->default(0);
            $table->unsignedInteger('rate_limit_hits')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->json('notes_json')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'sync_type', 'status'], 'integration_sync_runs_store_type_status_idx');
            $table->index(['store_id', 'started_at'], 'integration_sync_runs_store_started_idx');
        });

        Schema::create('integration_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->nullable()->constrained('marketplace_stores')->nullOnDelete();
            $table->string('provider', 50);
            $table->string('event_type', 100)->nullable();
            $table->string('external_event_id', 120)->nullable();
            $table->boolean('signature_valid')->default(false);
            $table->json('payload_json')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->string('status', 30)->default('received');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'external_event_id'], 'integration_webhook_events_provider_external_unique');
            $table->index(['store_id', 'status'], 'integration_webhook_events_store_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_webhook_events');
        Schema::dropIfExists('integration_sync_runs');
        Schema::dropIfExists('integration_sync_profiles');
        Schema::dropIfExists('integration_connections');
        Schema::dropIfExists('marketplace_stores');
    }
};
