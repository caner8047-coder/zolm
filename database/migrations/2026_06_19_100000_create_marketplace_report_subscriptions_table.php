<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_report_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->nullable()->constrained('marketplace_stores')->nullOnDelete();
            $table->string('name', 140)->default('Pazaryeri Kâr Özeti');
            $table->string('frequency', 20)->default('daily');
            $table->json('channels_json')->nullable();
            $table->json('recipients_json')->nullable();
            $table->json('filters_json')->nullable();
            $table->json('sections_json')->nullable();
            $table->boolean('enabled')->default(true);
            $table->string('send_time', 5)->default('08:30');
            $table->string('timezone', 64)->default('Europe/Istanbul');
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->string('last_status', 30)->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'enabled', 'next_run_at'], 'mp_report_sub_user_due_idx');
            $table->index(['user_id', 'frequency'], 'mp_report_sub_user_frequency_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_report_subscriptions');
    }
};
