<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. assistant_queries tablosuna yeni alanlar ekle
        if (Schema::hasTable('assistant_queries')) {
            Schema::table('assistant_queries', function (Blueprint $table) {
                if (!Schema::hasColumn('assistant_queries', 'intent')) {
                    $table->string('intent', 60)->nullable()->after('status');
                }
                if (!Schema::hasColumn('assistant_queries', 'confidence_score')) {
                    $table->decimal('confidence_score', 5, 2)->default(0)->after('intent');
                }
                if (!Schema::hasColumn('assistant_queries', 'filters_json')) {
                    $table->json('filters_json')->nullable()->after('confidence_score');
                }
                if (!Schema::hasColumn('assistant_queries', 'sources_json')) {
                    $table->json('sources_json')->nullable()->after('filters_json');
                }
                if (!Schema::hasColumn('assistant_queries', 'suggestions_json')) {
                    $table->json('suggestions_json')->nullable()->after('sources_json');
                }
                if (!Schema::hasColumn('assistant_queries', 'error_message')) {
                    $table->string('error_message')->nullable()->after('suggestions_json');
                }
                if (!Schema::hasColumn('assistant_queries', 'answered_at')) {
                    $table->timestamp('answered_at')->nullable()->after('error_message');
                }
            });

            // Index: user_id + created_at (sorgu geçmişi sıralama için)
            $indexes = collect(Schema::getIndexes('assistant_queries'))->pluck('name');
            if (!$indexes->contains('aq_user_created_idx')) {
                Schema::table('assistant_queries', function (Blueprint $table) {
                    $table->index(['user_id', 'created_at'], 'aq_user_created_idx');
                });
            }
        }

        // 2. assistant_saved_questions: unique index ve ek index
        if (Schema::hasTable('assistant_saved_questions')) {
            $indexes = collect(Schema::getIndexes('assistant_saved_questions'))->pluck('name');

            if (!$indexes->contains('asq_user_query_unique')) {
                Schema::table('assistant_saved_questions', function (Blueprint $table) {
                    // query_text text tipinde olduğundan prefix ile unique ekle — SQLite'ta prefix yok, MySQL'de gerekli
                    // Güvenli yaklaşım: sadece MySQL'de uygula
                    if (config('database.default') === 'mysql') {
                        \Illuminate\Support\Facades\DB::statement(
                            'ALTER TABLE assistant_saved_questions ADD UNIQUE INDEX asq_user_query_unique (user_id, query_text(200))'
                        );
                    }
                });
            }

            if (!$indexes->contains('asq_user_created_idx')) {
                Schema::table('assistant_saved_questions', function (Blueprint $table) {
                    $table->index(['user_id', 'created_at'], 'asq_user_created_idx');
                });
            }
        }

        // 3. assistant_action_suggestions: yeni tablo (sadece öneri saklar, işlem yapmaz)
        if (!Schema::hasTable('assistant_action_suggestions')) {
            Schema::create('assistant_action_suggestions', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('user_id');
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

                $table->unsignedBigInteger('assistant_query_id')->nullable();
                $table->foreign('assistant_query_id')->references('id')->on('assistant_queries')->nullOnDelete();

                $table->string('suggestion_type', 60);  // follow_up, risk, opportunity
                $table->string('title', 200);
                $table->text('description')->nullable();
                $table->string('severity', 30)->default('info'); // info, warning, critical
                $table->json('payload_json')->nullable();
                $table->string('status', 30)->default('suggested'); // suggested, reviewed, dismissed
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamp('dismissed_at')->nullable();

                $table->timestamps();

                $table->index(['user_id', 'status'], 'aas_user_status_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_action_suggestions');

        if (Schema::hasTable('assistant_queries')) {
            Schema::table('assistant_queries', function (Blueprint $table) {
                $indexes = collect(Schema::getIndexes('assistant_queries'))->pluck('name');
                if ($indexes->contains('aq_user_created_idx')) {
                    $table->dropIndex('aq_user_created_idx');
                }

                $columnsToDrop = [
                    'intent', 'confidence_score', 'filters_json',
                    'sources_json', 'suggestions_json', 'error_message', 'answered_at'
                ];
                foreach ($columnsToDrop as $col) {
                    if (Schema::hasColumn('assistant_queries', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('assistant_saved_questions')) {
            Schema::table('assistant_saved_questions', function (Blueprint $table) {
                $indexes = collect(Schema::getIndexes('assistant_saved_questions'))->pluck('name');
                if ($indexes->contains('asq_user_created_idx')) {
                    $table->dropIndex('asq_user_created_idx');
                }
                if ($indexes->contains('asq_user_query_unique')) {
                    // SQLite ve MySQL'de unique drop için dropUnique kullanılabilir
                    $table->dropUnique('asq_user_query_unique');
                }
            });
        }
    }
};
