<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_intake_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('source', 40)->default('zolm_mobile');
            $table->string('intake_mode', 30)->default('undamaged');
            $table->string('status', 40)->default('submitted');
            $table->timestamp('captured_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'captured_at'], 'return_batches_user_captured_idx');
            $table->index(['source', 'status'], 'return_batches_source_status_idx');
        });

        Schema::create('return_intake_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('return_intake_batches')->cascadeOnDelete();
            $table->foreignId('submitted_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('store_id')->nullable()->constrained('marketplace_stores')->nullOnDelete();
            $table->foreignId('channel_claim_id')->nullable()->constrained('channel_claims')->nullOnDelete();
            $table->foreignId('channel_order_id')->nullable()->constrained('channel_orders')->nullOnDelete();
            $table->foreignId('channel_order_package_id')->nullable()->constrained('channel_order_packages')->nullOnDelete();
            $table->string('intake_type', 30)->default('undamaged');
            $table->string('intake_status', 40)->default('queued');
            $table->string('condition_status', 40)->default('unknown');
            $table->string('product_verification_status', 40)->default('unverified');
            $table->string('decision_status', 40)->default('pending');
            $table->decimal('matching_confidence', 5, 2)->nullable();
            $table->string('matched_by', 40)->nullable();
            $table->string('detected_tracking_number', 120)->nullable();
            $table->string('detected_order_number', 120)->nullable();
            $table->string('detected_barcode', 120)->nullable();
            $table->string('detected_customer_name', 200)->nullable();
            $table->string('cargo_provider', 120)->nullable();
            $table->string('manual_reference', 120)->nullable();
            $table->text('warehouse_note')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('analysis_started_at')->nullable();
            $table->timestamp('analysis_completed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('raw_summary_json')->nullable();
            $table->timestamps();

            $table->index(['intake_status', 'decision_status'], 'return_items_status_decision_idx');
            $table->index(['condition_status', 'product_verification_status'], 'return_items_condition_verify_idx');
            $table->index(['detected_tracking_number'], 'return_items_tracking_idx');
            $table->index(['detected_order_number'], 'return_items_order_idx');
            $table->index(['detected_barcode'], 'return_items_barcode_idx');
            $table->index(['submitted_by_user_id', 'arrived_at'], 'return_items_user_arrived_idx');
        });

        Schema::create('return_intake_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_intake_item_id')->constrained('return_intake_items')->cascadeOnDelete();
            $table->string('kind', 40);
            $table->string('disk', 40)->default('public');
            $table->string('path', 500);
            $table->string('mime_type', 120)->nullable();
            $table->string('extension', 20)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('checksum', 64)->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->timestamps();

            $table->index(['return_intake_item_id', 'kind'], 'return_media_item_kind_idx');
            $table->index(['checksum'], 'return_media_checksum_idx');
        });

        Schema::create('return_intake_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_intake_item_id')->constrained('return_intake_items')->cascadeOnDelete();
            $table->string('provider', 40)->nullable();
            $table->string('model', 120)->nullable();
            $table->string('prompt_version', 40)->nullable();
            $table->decimal('confidence', 5, 2)->nullable();
            $table->json('ocr_json')->nullable();
            $table->json('classification_json')->nullable();
            $table->json('raw_response_json')->nullable();
            $table->timestamps();

            $table->index(['return_intake_item_id', 'created_at'], 'return_analyses_item_created_idx');
        });

        Schema::create('return_intake_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_intake_item_id')->constrained('return_intake_items')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decision', 40);
            $table->string('decision_mode', 30)->default('manual');
            $table->string('reason_code', 80)->nullable();
            $table->text('note')->nullable();
            $table->timestamp('marketplace_pushed_at')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index(['return_intake_item_id', 'created_at'], 'return_decisions_item_created_idx');
            $table->index(['decision', 'decision_mode'], 'return_decisions_type_mode_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_intake_decisions');
        Schema::dropIfExists('return_intake_analyses');
        Schema::dropIfExists('return_intake_media');
        Schema::dropIfExists('return_intake_items');
        Schema::dropIfExists('return_intake_batches');
    }
};
