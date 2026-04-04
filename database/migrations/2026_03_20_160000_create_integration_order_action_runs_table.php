<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_order_action_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->foreignId('channel_order_id')->constrained('channel_orders')->cascadeOnDelete();
            $table->foreignId('channel_order_package_id')->nullable()->constrained('channel_order_packages')->nullOnDelete();
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action_type', 50);
            $table->string('status', 30)->default('queued');
            $table->unsignedInteger('attempt_count')->default(0);
            $table->string('external_action_id', 120)->nullable();
            $table->json('request_context_json')->nullable();
            $table->json('response_json')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'status'], 'integration_order_action_runs_store_status_idx');
            $table->index(['channel_order_id', 'action_type'], 'integration_order_action_runs_order_action_idx');
            $table->index(['channel_order_package_id', 'action_type'], 'integration_order_action_runs_package_action_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_order_action_runs');
    }
};
