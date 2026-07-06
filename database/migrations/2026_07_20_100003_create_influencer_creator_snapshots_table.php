<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('influencer_creator_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('ad_campaigns')->cascadeOnDelete();
            $table->foreignId('influencer_profile_id')->constrained('influencer_profiles')->cascadeOnDelete();
            $table->foreignId('import_batch_id')->constrained('ad_import_batches')->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->timestamp('captured_at');
            $table->integer('link_visits')->default(0);
            $table->integer('sales_total')->default(0);
            $table->decimal('revenue_total', 12, 2)->default(0);
            $table->integer('new_customers')->default(0);
            $table->decimal('estimated_payment', 12, 2)->nullable();
            $table->decimal('actual_payment', 12, 2)->nullable();
            $table->timestamps();
            $table->index(['campaign_id', 'influencer_profile_id'], 'inf_creator_snap_camp_prof_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('influencer_creator_snapshots');
    }
};
