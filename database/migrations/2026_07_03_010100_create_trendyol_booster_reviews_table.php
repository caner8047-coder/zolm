<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trendyol_booster_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sync_run_id')->nullable()
                ->constrained('trendyol_booster_review_syncs')->nullOnDelete();
            $table->string('trendyol_product_id', 80)->index();
            $table->string('trendyol_review_id', 80)->unique();
            $table->string('trendyol_product_barcode', 120)->nullable()->index();
            $table->string('product_title', 500);
            $table->string('product_image_url', 1000)->nullable();

            // Reviewer (KVKK maskeli)
            $table->string('reviewer_name_masked', 180);
            $table->string('reviewer_name_hash', 80)->index();
            $table->string('reviewer_avatar_url', 1000)->nullable();

            $table->tinyInteger('rating')->unsigned();
            $table->text('comment');
            $table->unsignedInteger('comment_length')->default(0);
            $table->json('review_media')->nullable();
            $table->unsignedInteger('helpful_count')->default(0);
            $table->string('seller_name', 180)->nullable();
            $table->datetime('reviewed_at')->index();
            $table->datetime('fetched_at');

            // Eşleştirme
            $table->unsignedBigInteger('mp_product_id')->nullable()->index();
            $table->unsignedBigInteger('wc_product_id')->nullable()->index();
            $table->string('wc_product_sku', 100)->nullable();
            $table->string('match_status', 20)->default('pending');
            $table->float('match_score', 5, 2)->nullable();

            // Push durumu
            $table->string('wc_push_status', 20)->default('pending');
            $table->datetime('wc_pushed_at')->nullable();
            $table->text('wc_push_error')->nullable();

            // Spam tespiti
            $table->float('spam_score', 5, 2)->default(0);
            $table->boolean('is_spam')->default(false)->index();
            $table->json('spam_flags')->nullable();

            // Onay / soft-delete / audit
            $table->string('status', 20)->default('pending')->index();
            $table->boolean('is_featured')->default(false);
            $table->integer('display_order')->default(0);
            $table->json('audit_history')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'trendyol_product_id', 'status'], 'tb_reviews_product_status_idx');
            $table->index(['user_id', 'status', 'wc_push_status'], 'tb_reviews_push_idx');
            $table->index(['wc_product_id', 'status'], 'tb_reviews_wc_idx');
            $table->index(['match_status', 'status'], 'tb_reviews_match_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trendyol_booster_reviews');
    }
};
