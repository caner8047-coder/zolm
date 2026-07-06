<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_keyword_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('ad_campaigns')->cascadeOnDelete();
            $table->foreignId('import_batch_id')->constrained('ad_import_batches')->cascadeOnDelete();
            $table->string('keyword', 500);
            $table->string('normalized_keyword', 500);
            $table->string('match_type', 20)->nullable();
            $table->date('period_start');
            $table->date('period_end');
            $table->timestamp('captured_at');
            $table->decimal('spend', 12, 2)->default(0);
            $table->integer('impressions')->default(0);
            $table->integer('clicks')->default(0);
            $table->decimal('ctr', 8, 4)->default(0);
            $table->integer('sales_total')->default(0);
            $table->decimal('revenue_total', 12, 2)->default(0);
            $table->decimal('roas', 8, 4)->default(0);
            $table->decimal('recommended_gbm', 8, 4)->nullable();
            $table->decimal('selected_gbm', 8, 4)->nullable();
            $table->decimal('actual_gbm', 8, 4)->nullable();
            $table->decimal('actual_cpc', 8, 4)->nullable();
            $table->timestamps();
            $table->unique(['import_batch_id', 'campaign_id', 'keyword'], 'ad_kw_snap_batch_camp_kw_unique');
            $table->index(['campaign_id', 'period_start', 'period_end']);
            $table->index(['campaign_id', 'keyword']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_keyword_snapshots');
    }
};
