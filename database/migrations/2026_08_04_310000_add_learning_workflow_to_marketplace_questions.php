<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_questions', function (Blueprint $table) {
            $table->string('learning_status', 30)->default('new')->after('ai_status');
            $table->foreignId('learning_suggestion_id')->nullable()->after('learning_status');
            $table->boolean('is_golden_candidate')->default(false)->after('learning_suggestion_id');
            $table->string('learning_excluded_reason', 255)->nullable()->after('is_golden_candidate');
            $table->foreignId('learning_reviewed_by_user_id')->nullable()->after('learning_excluded_reason');
            $table->timestamp('learning_reviewed_at')->nullable()->after('learning_reviewed_by_user_id');

            $table->foreign('learning_suggestion_id', 'mpq_learning_suggestion_fk')
                ->references('id')
                ->on('support_knowledge_suggestions')
                ->nullOnDelete();
            $table->foreign('learning_reviewed_by_user_id', 'mpq_learning_reviewer_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index(['store_id', 'learning_status'], 'mpq_store_learning_status_idx');
            $table->index(['store_id', 'is_golden_candidate'], 'mpq_store_golden_candidate_idx');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_questions', function (Blueprint $table) {
            $table->dropIndex('mpq_store_learning_status_idx');
            $table->dropIndex('mpq_store_golden_candidate_idx');
            $table->dropForeign('mpq_learning_suggestion_fk');
            $table->dropForeign('mpq_learning_reviewer_fk');
            $table->dropColumn([
                'learning_status',
                'learning_suggestion_id',
                'is_golden_candidate',
                'learning_excluded_reason',
                'learning_reviewed_by_user_id',
                'learning_reviewed_at',
            ]);
        });
    }
};
