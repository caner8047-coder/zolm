<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->nullable()->constrained('marketplace_stores')->nullOnDelete();
            $table->string('type', 60);
            $table->string('severity', 20)->default('info');
            $table->string('event_key', 191)->nullable();
            $table->string('title', 180);
            $table->text('body')->nullable();
            $table->nullableMorphs('subject');
            $table->json('data_json')->nullable();
            $table->string('action_url', 500)->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('seen_at')->nullable();
            $table->timestamp('triggered_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'event_key'], 'app_notifications_user_event_unique');
            $table->index(['user_id', 'read_at', 'created_at'], 'app_notifications_user_read_created_idx');
            $table->index(['user_id', 'type', 'created_at'], 'app_notifications_user_type_created_idx');
            $table->index(['store_id', 'created_at'], 'app_notifications_store_created_idx');
        });

        Schema::create('user_notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('sound_enabled')->default(false);
            $table->json('muted_types_json')->nullable();
            $table->timestamps();

            $table->unique('user_id', 'user_notification_preferences_user_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notification_preferences');
        Schema::dropIfExists('app_notifications');
    }
};
