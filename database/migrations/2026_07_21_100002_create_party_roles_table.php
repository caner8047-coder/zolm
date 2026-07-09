<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('party_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('party_id')->constrained('parties')->cascadeOnDelete();
            $table->string('role', 30);
            $table->string('role_code', 60)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->string('status', 30)->default('active');
            $table->timestamp('assigned_at')->nullable();
            $table->json('meta_json')->nullable();
            $table->timestamps();

            // Karar #1: role_code unique'e dahil edilmez; nullable + ayrı index.
            $table->unique(['user_id', 'party_id', 'role'], 'party_roles_user_party_role_unique');
            $table->index(['user_id', 'role_code'], 'party_roles_user_role_code_idx');
            $table->index(['user_id', 'role', 'status'], 'party_roles_user_role_status_idx');
            $table->index(['party_id', 'is_primary'], 'party_roles_party_primary_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('party_roles');
    }
};
