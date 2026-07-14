<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_ai_runs', function (Blueprint $table) {
            $table->string('detected_language', 12)->nullable()->after('status');
            $table->decimal('language_confidence', 5, 4)->nullable()->after('detected_language');
            $table->string('response_language', 12)->nullable()->after('language_confidence');
        });
        Schema::table('support_ai_eval_runs', function (Blueprint $table) {
            $table->string('language', 12)->default('tr')->after('dataset_version');
            $table->string('dataset_profile', 40)->default('standard')->after('language');
        });
        Schema::create('support_language_quality_gates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->string('language', 12);
            $table->string('dataset_version', 40);
            $table->unsignedInteger('sample_size');
            $table->decimal('average_score', 5, 2);
            $table->decimal('source_accuracy', 5, 2);
            $table->unsignedInteger('critical_error_count')->default(0);
            $table->boolean('passed');
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('evaluated_at');
            $table->timestamps();
            $table->unique(['store_id', 'language', 'dataset_version'], 'slqg_store_lang_version_unique');
            $table->index(['store_id', 'language', 'passed', 'evaluated_at'], 'slqg_store_lang_passed_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_language_quality_gates');
        Schema::table('support_ai_eval_runs', fn (Blueprint $table) => $table->dropColumn(['language', 'dataset_profile']));
        Schema::table('support_ai_runs', fn (Blueprint $table) => $table->dropColumn(['detected_language', 'language_confidence', 'response_language']));
    }
};
