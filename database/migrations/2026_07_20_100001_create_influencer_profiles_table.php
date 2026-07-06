<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('influencer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 20)->default('unknown');
            $table->string('handle', 255);
            $table->string('display_name', 255)->nullable();
            $table->string('profile_url', 500)->nullable();
            $table->string('avatar_url', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'handle']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('influencer_profiles');
    }
};
