<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_projection_cursors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->unsignedBigInteger('channel_id')->nullable();
            $table->string('channel_type', 40); // whatsapp, trendyol, meta, google_reviews, web_chat
            $table->string('cursor_key', 120);
            $table->string('last_seen_external_id', 120)->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('checksum_snapshot', 120)->nullable();
            $table->string('status', 30)->default('unknown'); // synced, drift_detected, unknown
            $table->timestamps();

            $table->unique(['store_id', 'channel_type', 'cursor_key'], 'store_chan_cursor_unique');
        });

        Schema::create('support_reconciliation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('status', 30)->default('running'); // running, completed, failed
            $table->json('summary_json')->nullable();
            $table->timestamps();
        });

        Schema::create('support_reconciliation_findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('support_reconciliation_runs')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->string('finding_type', 60); // missing_conversation, missing_message, duplicate_projection, orphan_dispatch, channel_store_mismatch, stale_cursor, failed_projection
            $table->json('details_json')->nullable();
            $table->string('status', 30)->default('detected'); // detected, repaired, ignored
            $table->timestamp('repaired_at')->nullable();
            $table->unsignedBigInteger('repaired_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_reconciliation_findings');
        Schema::dropIfExists('support_reconciliation_runs');
        Schema::dropIfExists('support_projection_cursors');
    }
};
