<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Organization Settings & Memberships (Dalga AQ)
        Schema::create('support_organization_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_entity_id')->constrained('legal_entities')->cascadeOnDelete();
            $table->string('system_actor_email', 190)->nullable();
            $table->json('security_policy')->nullable();
            $table->timestamps();
        });

        Schema::create('support_organization_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_entity_id')->constrained('legal_entities')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 40)->default('member'); // admin, supervisor, member
            $table->timestamps();

            $table->unique(['legal_entity_id', 'user_id']);
        });

        Schema::create('support_service_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_entity_id')->constrained('legal_entities')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('email', 190)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_service_accounts');
        Schema::dropIfExists('support_organization_memberships');
        Schema::dropIfExists('support_organization_settings');
    }
};
