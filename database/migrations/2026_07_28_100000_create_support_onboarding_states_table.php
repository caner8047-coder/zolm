<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_onboarding_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->unique()->constrained('marketplace_stores')->cascadeOnDelete();
            $table->integer('current_step')->default(1);
            $table->json('steps_completed')->nullable(); // JSON list of completed steps like [1, 2]
            $table->string('status', 20)->default('in_progress'); // in_progress, completed
            $table->string('recommended_mode', 20)->nullable(); // manual, copilot, automatic
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_onboarding_states');
    }
};
