<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_production_readiness_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->index();
            $table->unsignedBigInteger('run_by')->nullable()->index();
            $table->integer('readiness_score')->default(0);
            $table->string('status')->default('not_ready'); // ready, not_ready
            $table->timestamps();
        });

        Schema::create('support_production_freeze_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->index();
            $table->unsignedBigInteger('run_id')->index();
            $table->text('snapshot_data_encrypted');
            $table->unsignedBigInteger('approved_by')->nullable()->index();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_production_freeze_snapshots');
        Schema::dropIfExists('support_production_readiness_runs');
    }
};
