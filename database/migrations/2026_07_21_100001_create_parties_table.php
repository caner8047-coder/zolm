<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legal_entity_id')->nullable()->constrained('legal_entities')->nullOnDelete();
            $table->string('display_name', 180);
            $table->string('normalized_name', 180)->nullable();
            $table->string('party_type', 30)->default('unknown');
            $table->string('primary_email', 180)->nullable();
            $table->string('primary_phone', 40)->nullable();
            $table->string('normalized_phone', 40)->nullable();
            $table->string('tax_number', 40)->nullable();
            $table->string('tax_office', 120)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('district', 120)->nullable();
            $table->string('status', 30)->default('active');
            $table->boolean('is_blacklisted')->default(false);
            $table->json('meta_json')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'normalized_name'], 'parties_user_name_idx');
            $table->index(['user_id', 'normalized_phone'], 'parties_user_phone_idx');
            $table->index(['user_id', 'tax_number'], 'parties_user_tax_idx');
            $table->index(['user_id', 'status'], 'parties_user_status_idx');
            $table->index(['user_id', 'legal_entity_id'], 'parties_user_entity_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parties');
    }
};
