<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->foreignId('channel_product_id')->nullable()->constrained('channel_products')->nullOnDelete();
            $table->foreignId('channel_listing_id')->nullable()->constrained('channel_listings')->nullOnDelete();
            $table->foreignId('channel_order_id')->nullable()->constrained('channel_orders')->nullOnDelete();
            $table->string('external_question_id');
            $table->string('question_type')->default('product');
            $table->string('status')->default('open');
            $table->string('customer_name')->nullable();
            $table->string('customer_external_id')->nullable();
            $table->string('product_name')->nullable();
            $table->string('product_sku')->nullable();
            $table->string('product_barcode')->nullable();
            $table->text('product_url')->nullable();
            $table->text('question_text');
            $table->text('answer_text')->nullable();
            $table->text('ai_suggested_answer')->nullable();
            $table->unsignedTinyInteger('ai_confidence')->nullable();
            $table->string('ai_status')->default('none');
            $table->foreignId('matched_rule_id')->nullable();
            $table->foreignId('answered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('asked_at')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'external_question_id'], 'mp_questions_store_external_unique');
            $table->index(['store_id', 'status', 'asked_at'], 'mp_questions_store_status_asked_idx');
            $table->index(['product_sku', 'product_barcode'], 'mp_questions_product_identity_idx');
        });

        Schema::create('marketplace_question_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_question_id')->constrained('marketplace_questions')->cascadeOnDelete();
            $table->string('direction')->default('customer');
            $table->string('external_message_id')->nullable();
            $table->text('body');
            $table->json('attachments_json')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index(['marketplace_question_id', 'sent_at'], 'mp_question_messages_question_sent_idx');
        });

        Schema::create('marketplace_question_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('category')->nullable();
            $table->string('marketplace')->nullable();
            $table->text('body');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'marketplace', 'is_active'], 'mp_question_templates_scope_idx');
        });

        Schema::create('marketplace_question_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('store_id')->nullable()->constrained('marketplace_stores')->cascadeOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('marketplace_question_templates')->nullOnDelete();
            $table->string('name');
            $table->string('match_type')->default('contains');
            $table->json('keywords_json')->nullable();
            $table->text('response_text')->nullable();
            $table->string('action_mode')->default('draft');
            $table->boolean('requires_approval')->default(true);
            $table->unsignedInteger('priority')->default(100);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('trigger_count')->default(0);
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'store_id', 'is_active', 'priority'], 'mp_question_rules_scope_idx');
        });

        Schema::table('marketplace_questions', function (Blueprint $table) {
            $table->foreign('matched_rule_id', 'mp_questions_matched_rule_fk')
                ->references('id')
                ->on('marketplace_question_rules')
                ->nullOnDelete();
        });

        Schema::create('marketplace_question_answer_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_question_id')->constrained('marketplace_questions')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('marketplace_question_templates')->nullOnDelete();
            $table->foreignId('rule_id')->nullable()->constrained('marketplace_question_rules')->nullOnDelete();
            $table->string('source')->default('manual');
            $table->text('answer_text');
            $table->string('status')->default('draft');
            $table->string('external_answer_id')->nullable();
            $table->text('error_message')->nullable();
            $table->json('response_json')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['marketplace_question_id', 'status'], 'mp_question_answer_logs_question_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_question_answer_logs');
        Schema::table('marketplace_questions', function (Blueprint $table) {
            $table->dropForeign('mp_questions_matched_rule_fk');
        });
        Schema::dropIfExists('marketplace_question_rules');
        Schema::dropIfExists('marketplace_question_templates');
        Schema::dropIfExists('marketplace_question_messages');
        Schema::dropIfExists('marketplace_questions');
    }
};
