<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_product_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('ad_campaigns')->cascadeOnDelete();
            $table->foreignId('ad_campaign_product_id')->constrained('ad_campaign_products')->cascadeOnDelete();
            $table->foreignId('import_batch_id')->constrained('ad_import_batches')->cascadeOnDelete();
            $table->string('metric_type', 20)->default('period_total');
            $table->date('period_start');
            $table->date('period_end');
            $table->timestamp('captured_at');
            $table->decimal('spend', 12, 2)->default(0);
            $table->integer('impressions')->default(0);
            $table->integer('clicks')->default(0);
            $table->decimal('ctr', 8, 4)->default(0);
            $table->integer('sales_direct')->default(0);
            $table->integer('sales_indirect')->default(0);
            $table->integer('sales_total')->default(0);
            $table->decimal('revenue_direct', 12, 2)->default(0);
            $table->decimal('revenue_indirect', 12, 2)->default(0);
            $table->decimal('revenue_total', 12, 2)->default(0);
            $table->decimal('roas', 8, 4)->default(0);
            $table->timestamps();
            $table->unique(['import_batch_id', 'ad_campaign_product_id'], 'ad_prod_snap_batch_prod_unique');
            $table->index(['campaign_id', 'period_start', 'period_end']);
            $table->index(['ad_campaign_product_id', 'captured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_product_snapshots');
    }
};
