<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trendyol_booster_keyword_lookups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('keyword', 180);
            $table->string('keyword_hash', 64);
            $table->string('source_url', 1000)->nullable();
            $table->unsignedInteger('result_count')->default(0);
            $table->json('top_products')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamp('searched_at')->useCurrent();
            $table->timestamps();

            $table->index(['user_id', 'searched_at'], 'tr_booster_kw_lookup_user_searched_idx');
            $table->index(['user_id', 'keyword_hash'], 'tr_booster_kw_lookup_user_keyword_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trendyol_booster_keyword_lookups');
    }
};
