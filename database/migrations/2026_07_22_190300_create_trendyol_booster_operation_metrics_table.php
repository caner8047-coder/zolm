<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trendyol_booster_operation_metrics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('route_name', 160);
            $table->string('http_method', 10);
            $table->unsignedSmallInteger('status_code');
            $table->unsignedInteger('duration_ms');
            $table->string('release_ring', 16)->default('ga');
            $table->string('outcome', 16);
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['occurred_at', 'outcome'], 'tb_metrics_time_outcome_idx');
            $table->index(['route_name', 'occurred_at'], 'tb_metrics_route_time_idx');
            $table->index(['user_id', 'occurred_at'], 'tb_metrics_user_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trendyol_booster_operation_metrics');
    }
};
