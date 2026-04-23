<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_bridge_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('whatsapp_bridge_enabled')->nullable();
            $table->foreignId('system_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('verify_token')->nullable();
            $table->text('access_token')->nullable();
            $table->text('app_secret')->nullable();
            $table->string('graph_base_url', 255)->nullable();
            $table->string('graph_version', 32)->nullable();
            $table->unsignedInteger('message_window_minutes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_bridge_settings');
    }
};
