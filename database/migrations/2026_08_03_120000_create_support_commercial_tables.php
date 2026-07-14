<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Commercial Packaging & Entitlements (Dalga AS)
        Schema::create('support_commercial_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80); // trial, starter, growth, pro, enterprise
            $table->string('slug', 40)->unique();
            $table->json('entitlements')->nullable(); // { max_drafts_monthly: 100, max_auto_replies_monthly: 50, ... }
            $table->timestamps();
        });

        Schema::create('support_commercial_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('support_commercial_plans')->cascadeOnDelete();
            $table->string('status', 30)->default('active'); // active, expired, trialing
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });

        Schema::create('support_entitlement_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->string('feature', 80); // auto_reply, ai_draft, suggestion...
            $table->string('status', 20); // allowed, blocked
            $table->json('context')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_entitlement_events');
        Schema::dropIfExists('support_commercial_subscriptions');
        Schema::dropIfExists('support_commercial_plans');
    }
};
