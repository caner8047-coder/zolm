<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_sales_attributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained('support_conversations')->cascadeOnDelete();
            $table->string('external_order_hash', 64);
            $table->string('attribution_method', 40);
            $table->decimal('order_amount', 14, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->json('evidence_json');
            $table->timestamp('verified_at');
            $table->timestamps();
            $table->unique(['store_id', 'external_order_hash'], 'ssa_store_order_unique');
        });

        Schema::create('support_pilot_baselines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->unique()->constrained('marketplace_stores')->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedInteger('sample_size');
            $table->decimal('average_human_handle_seconds', 12, 2);
            $table->foreignId('approved_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_pilot_baselines');
        Schema::dropIfExists('support_sales_attributions');
    }
};
