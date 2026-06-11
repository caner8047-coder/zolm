<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mp_product_change_logs')) {
            return;
        }

        Schema::create('mp_product_change_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('mp_product_id')->nullable()->constrained('mp_products')->nullOnDelete();
            $table->foreignId('channel_listing_id')->nullable()->constrained('channel_listings')->nullOnDelete();
            $table->foreignId('store_id')->nullable()->constrained('marketplace_stores')->nullOnDelete();
            $table->string('batch_id', 100)->nullable()->index();
            $table->string('change_scope', 30)->default('product')->index();
            $table->string('field_key', 80)->index();
            $table->string('field_label', 120);
            $table->string('value_type', 30)->default('string');
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->decimal('old_value_number', 16, 4)->nullable();
            $table->decimal('new_value_number', 16, 4)->nullable();
            $table->decimal('delta_number', 16, 4)->nullable();
            $table->decimal('delta_percent', 10, 4)->nullable();
            $table->string('source', 60)->default('manual')->index();
            $table->string('source_label', 120)->nullable();
            $table->string('note', 255)->nullable();
            $table->json('old_snapshot')->nullable();
            $table->json('new_snapshot')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at')->nullable()->index();
            $table->timestamps();

            $table->index(['mp_product_id', 'changed_at'], 'mp_pcl_product_changed_idx');
            $table->index(['channel_listing_id', 'changed_at'], 'mp_pcl_listing_changed_idx');
            $table->index(['source', 'changed_at'], 'mp_pcl_source_changed_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mp_product_change_logs');
    }
};
