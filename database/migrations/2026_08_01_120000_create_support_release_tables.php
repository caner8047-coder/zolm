<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_release_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->string('title', 150);
            $table->string('status', 30)->default('draft'); // draft, review, approved, staged, published, rolled_back, rejected
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('support_release_package_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained('support_release_packages')->cascadeOnDelete();
            $table->string('artifact_type', 60); // knowledge_article, brand_voice, policy_rule, prompt_template, answer_template
            $table->unsignedBigInteger('artifact_id')->nullable();
            $table->string('action', 30)->default('create'); // create, update, delete
            $table->json('diff_json')->nullable();
            $table->json('new_content_json')->nullable();
            $table->timestamps();
        });

        Schema::create('support_release_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained('support_release_packages')->cascadeOnDelete();
            $table->string('event_type', 60);
            $table->json('details_json')->nullable();
            $table->timestamps();
        });

        Schema::create('support_artifact_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->string('artifact_type', 60); // knowledge_article, brand_voice, policy_rule, prompt_template, answer_template
            $table->unsignedBigInteger('artifact_id')->nullable();
            $table->integer('version_number');
            $table->json('content_json');
            $table->boolean('is_current')->default(false);
            $table->timestamps();

            $table->index(['store_id', 'artifact_type', 'artifact_id', 'is_current'], 'artifact_current_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_artifact_versions');
        Schema::dropIfExists('support_release_events');
        Schema::dropIfExists('support_release_package_items');
        Schema::dropIfExists('support_release_packages');
    }
};
