<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mp_profit_action_items') || Schema::hasTable('mp_profit_action_events')) {
            return;
        }

        Schema::create('mp_profit_action_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mp_profit_action_item_id')
                ->constrained('mp_profit_action_items')
                ->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 40);
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();
            $table->json('meta_json')->nullable();
            $table->timestamps();

            $table->index(['mp_profit_action_item_id', 'created_at'], 'mp_profit_action_events_item_created_idx');
            $table->index(['user_id', 'event_type', 'created_at'], 'mp_profit_action_events_user_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mp_profit_action_events');
    }
};
