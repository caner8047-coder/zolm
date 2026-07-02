<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_report_digest_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_report_subscription_id')->nullable();
            $table->foreignId('report_id')->nullable();
            $table->foreignId('user_id');
            $table->foreignId('store_id')->nullable();
            $table->string('frequency', 20);
            $table->date('period_start');
            $table->date('period_end');
            $table->string('recipient_email', 180);
            $table->string('subject', 220);
            $table->string('status', 30)->default('pending');
            $table->json('summary_json')->nullable();
            $table->json('payload_json')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status', 'created_at'], 'mp_report_runs_user_status_created_idx');
            $table->index(['marketplace_report_subscription_id', 'period_start', 'period_end'], 'mp_report_runs_subscription_period_idx');

            $table->foreign('marketplace_report_subscription_id', 'mp_report_runs_subscription_fk')
                ->references('id')
                ->on('marketplace_report_subscriptions')
                ->nullOnDelete();
            $table->foreign('report_id', 'mp_report_runs_report_fk')
                ->references('id')
                ->on('reports')
                ->nullOnDelete();
            $table->foreign('user_id', 'mp_report_runs_user_fk')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
            $table->foreign('store_id', 'mp_report_runs_store_fk')
                ->references('id')
                ->on('marketplace_stores')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_report_digest_runs');
    }
};
