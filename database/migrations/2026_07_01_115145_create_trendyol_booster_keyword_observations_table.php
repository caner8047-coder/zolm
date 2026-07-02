<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('trendyol_booster_keyword_observations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('trendyol_booster_keyword_id');
            $table->foreign('trendyol_booster_keyword_id', 'tr_booster_kw_obs_kw_fk')
                ->references('id')->on('trendyol_booster_keywords')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('observed_rank')->nullable();
            $table->unsignedMediumInteger('result_count')->default(0);
            $table->unsignedMediumInteger('checked_result_count')->default(0);
            $table->string('visibility_status', 30)->nullable();
            $table->timestamp('created_at')->useCurrent();
            
            $table->index(['trendyol_booster_keyword_id', 'created_at'], 'tr_booster_kw_obs_keyword_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trendyol_booster_keyword_observations');
    }
};
