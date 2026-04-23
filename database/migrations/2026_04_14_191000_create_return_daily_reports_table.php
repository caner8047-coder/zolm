<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_daily_reports', function (Blueprint $table) {
            $table->id();
            $table->date('report_date')->unique();
            $table->json('totals_json')->nullable();
            $table->json('decision_breakdown_json')->nullable();
            $table->json('condition_breakdown_json')->nullable();
            $table->json('operator_breakdown_json')->nullable();
            $table->json('store_breakdown_json')->nullable();
            $table->json('auto_policy_json')->nullable();
            $table->json('hot_items_json')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->index(['generated_at'], 'return_daily_reports_generated_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_daily_reports');
    }
};
