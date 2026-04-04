<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_push_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->foreignId('channel_listing_id')->nullable()->constrained('channel_listings')->nullOnDelete();
            $table->foreignId('mp_product_id')->nullable()->constrained('mp_products')->nullOnDelete();
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('push_type', 30);
            $table->string('status', 30)->default('queued');
            $table->decimal('target_price', 12, 2)->nullable();
            $table->integer('target_quantity')->nullable();
            $table->string('currency', 3)->default('TRY');
            $table->json('request_context_json')->nullable();
            $table->json('response_json')->nullable();
            $table->string('external_batch_id', 120)->nullable();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'push_type', 'status'], 'integration_push_runs_store_type_status_idx');
            $table->index(['channel_listing_id', 'created_at'], 'integration_push_runs_listing_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_push_runs');
    }
};
