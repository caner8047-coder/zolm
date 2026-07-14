<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_success_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->unsignedTinyInteger('health_score')->nullable(); // 0-100, null = unknown
            $table->string('health_label', 20)->default('unknown'); // healthy, degraded, critical, unknown
            $table->json('component_scores')->nullable(); // { queue: 80, eval: 60, ... }
            $table->json('unknown_components')->nullable(); // list of components with no data
            $table->unsignedBigInteger('computed_by')->nullable();
            $table->timestamp('computed_at')->nullable();
            $table->boolean('is_stale')->default(false);
            $table->timestamps();
        });

        Schema::create('support_success_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->foreignId('snapshot_id')->nullable()->constrained('support_success_snapshots')->nullOnDelete();
            $table->string('task_type', 60); // golden_eval_refresh, queue_backlog, webhook_secret_rotate, policy_block_rate, sla_violations
            $table->string('status', 20)->default('open'); // open, in_progress, resolved
            $table->text('description')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('support_success_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->unsignedBigInteger('author_id')->nullable();
            $table->text('body_encrypted'); // Crypt::encryptString ile şifreli
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_success_notes');
        Schema::dropIfExists('support_success_tasks');
        Schema::dropIfExists('support_success_snapshots');
    }
};
