<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->constrained('ad_campaigns')->cascadeOnDelete();
            $table->foreignId('summary_import_batch_id')->constrained('ad_import_batches')->cascadeOnDelete();
            $table->foreignId('detail_import_batch_id')->constrained('ad_import_batches')->cascadeOnDelete();
            $table->string('comparison_type', 50);
            $table->decimal('campaign_spend', 12, 2)->default(0);
            $table->decimal('detail_spend', 12, 2)->default(0);
            $table->decimal('spend_difference', 12, 2)->default(0);
            $table->decimal('campaign_revenue', 12, 2)->default(0);
            $table->decimal('detail_revenue', 12, 2)->default(0);
            $table->decimal('revenue_difference', 12, 2)->default(0);
            $table->integer('campaign_sales')->default(0);
            $table->integer('detail_sales')->default(0);
            $table->integer('sales_difference')->default(0);
            $table->decimal('difference_percent', 8, 4)->default(0);
            $table->string('status', 20);
            $table->json('evidence')->nullable();
            $table->timestamp('calculated_at');
            $table->timestamps();
            $table->unique(['campaign_id', 'summary_import_batch_id', 'detail_import_batch_id', 'comparison_type'], 'ad_reconciliation_unique');
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_reconciliations');
    }
};
