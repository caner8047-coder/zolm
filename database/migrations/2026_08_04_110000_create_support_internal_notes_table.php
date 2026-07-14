<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_internal_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversation_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->text('note_encrypted');
            $table->timestamps();
        });

        Schema::create('support_agent_presences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversation_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->timestamp('last_active_at')->useCurrent();
            $table->timestamps();
        });

        Schema::create('support_saved_views', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('store_id')->index();
            $table->string('name');
            $table->json('filters_json');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_saved_views');
        Schema::dropIfExists('support_agent_presences');
        Schema::dropIfExists('support_internal_notes');
    }
};
