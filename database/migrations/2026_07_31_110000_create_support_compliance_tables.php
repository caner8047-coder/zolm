<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_data_subject_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->string('customer_id', 120);
            $table->string('request_type', 40); // export, rectification, anonymize, delete
            $table->json('details_json')->nullable();
            $table->string('status', 30)->default('pending'); // pending, processing, completed, failed
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('support_consent_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->string('customer_id', 120);
            $table->string('channel_key', 60);
            $table->string('consent_type', 40); // operational, marketing
            $table->string('status', 30)->default('granted'); // granted, revoked
            $table->timestamp('recorded_at')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'customer_id', 'channel_key', 'consent_type'], 'store_cust_channel_consent_unique');
        });

        Schema::create('support_legal_holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->string('customer_id', 120);
            $table->text('reason')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['store_id', 'customer_id'], 'store_customer_hold_unique');
        });

        Schema::create('support_data_lineage_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->string('customer_id', 120);
            $table->unsignedBigInteger('message_id')->nullable();
            $table->string('action_type', 60);
            $table->string('target_type', 80);
            $table->unsignedBigInteger('target_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_data_lineage_events');
        Schema::dropIfExists('support_legal_holds');
        Schema::dropIfExists('support_consent_records');
        Schema::dropIfExists('support_data_subject_requests');
    }
};
