<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ad_account_id')->constrained('ad_accounts')->cascadeOnDelete();
            $table->string('channel_code', 50);
            $table->string('external_campaign_id', 100)->nullable();
            $table->string('campaign_identity_hash', 64);
            $table->string('campaign_key', 500);
            $table->string('name', 500);
            $table->string('status', 50)->default('active');
            $table->string('targeting_type', 20)->nullable();
            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->decimal('daily_budget', 12, 2)->nullable();
            $table->decimal('total_budget', 12, 2)->nullable();
            $table->decimal('remaining_budget', 12, 2)->nullable();
            $table->string('bid_strategy', 50)->nullable();
            $table->decimal('selected_gbm', 8, 4)->nullable();
            $table->decimal('recommended_gbm', 8, 4)->nullable();
            $table->decimal('actual_gbm', 8, 4)->nullable();
            $table->decimal('actual_cpc', 8, 4)->nullable();
            $table->string('redirect_url', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['ad_account_id', 'campaign_identity_hash'], 'ad_campaign_identity_unique');
            $table->unique(['ad_account_id', 'external_campaign_id'], 'ad_campaign_account_external_unique');
            $table->index(['user_id', 'channel_code', 'created_at']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_campaigns');
    }
};
