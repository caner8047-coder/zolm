<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('marketplace_finance_bridge_runs')) {
            Schema::create('marketplace_finance_bridge_runs', function (Blueprint $table) {
                $table->id();
                
                $table->unsignedBigInteger('user_id');
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

                $table->unsignedBigInteger('marketplace_store_id')->nullable();
                $table->foreign('marketplace_store_id', 'mfbr_store_fk')
                      ->references('id')->on('marketplace_stores')
                      ->nullOnDelete();

                $table->unsignedBigInteger('channel_order_id')->nullable();
                $table->foreign('channel_order_id', 'mfbr_order_fk')
                      ->references('id')->on('channel_orders')
                      ->nullOnDelete();

                $table->unsignedBigInteger('order_financial_event_id')->nullable();
                $table->foreign('order_financial_event_id', 'mfbr_event_fk')
                      ->references('id')->on('order_financial_events')
                      ->nullOnDelete();

                $table->string('bridge_type', 40); // order, financial_event
                $table->string('source_key', 191)->nullable();
                $table->string('status', 30)->default('pending'); // pending, processing, succeeded, failed, skipped
                
                $table->string('target_type')->nullable();
                $table->unsignedBigInteger('target_id')->nullable();
                
                $table->text('error_message')->nullable();
                $table->json('payload_json')->nullable();
                $table->json('result_json')->nullable();
                
                $table->timestamp('attempted_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                // Unique index
                $table->unique(['user_id', 'source_key'], 'mfbr_user_source_unique');

                // Indexes
                $table->index(['user_id', 'status', 'created_at'], 'mfbr_user_status_created_idx');
                $table->index(['user_id', 'bridge_type', 'status'], 'mfbr_user_type_status_idx');
                $table->index(['user_id', 'marketplace_store_id', 'status'], 'mfbr_user_store_status_idx');
                $table->index(['user_id', 'channel_order_id'], 'mfbr_user_order_idx');
                $table->index(['user_id', 'order_financial_event_id'], 'mfbr_user_event_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('marketplace_finance_bridge_runs')) {
            Schema::table('marketplace_finance_bridge_runs', function (Blueprint $table) {
                // Drop indexes safely
                $indexes = collect(Schema::getIndexes('marketplace_finance_bridge_runs'))->pluck('name');
                
                if ($indexes->contains('mfbr_user_source_unique')) {
                    $table->dropUnique('mfbr_user_source_unique');
                }
                if ($indexes->contains('mfbr_user_status_created_idx')) {
                    $table->dropIndex('mfbr_user_status_created_idx');
                }
                if ($indexes->contains('mfbr_user_type_status_idx')) {
                    $table->dropIndex('mfbr_user_type_status_idx');
                }
                if ($indexes->contains('mfbr_user_store_status_idx')) {
                    $table->dropIndex('mfbr_user_store_status_idx');
                }
                if ($indexes->contains('mfbr_user_order_idx')) {
                    $table->dropIndex('mfbr_user_order_idx');
                }
                if ($indexes->contains('mfbr_user_event_idx')) {
                    $table->dropIndex('mfbr_user_event_idx');
                }

                // Drop foreign keys safely
                if ($indexes->contains('marketplace_finance_bridge_runs_user_id_foreign')) {
                    $table->dropForeign(['user_id']);
                }
                if ($indexes->contains('mfbr_store_fk')) {
                    $table->dropForeign('mfbr_store_fk');
                }
                if ($indexes->contains('mfbr_order_fk')) {
                    $table->dropForeign('mfbr_order_fk');
                }
                if ($indexes->contains('mfbr_event_fk')) {
                    $table->dropForeign('mfbr_event_fk');
                }
            });

            Schema::dropIfExists('marketplace_finance_bridge_runs');
        }
    }
};
