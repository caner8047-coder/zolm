<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_entities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('tax_number', 32);
            $table->string('tax_office', 120)->nullable();
            $table->string('mersis_number', 32)->nullable();
            $table->string('company_type', 50)->default('company');
            $table->string('phone', 32)->nullable();
            $table->string('email', 150)->nullable();
            $table->text('address')->nullable();
            $table->string('iban', 64)->nullable();
            $table->string('bank_name', 120)->nullable();
            $table->string('currency', 3)->default('TRY');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'tax_number'], 'legal_entities_user_tax_unique');
            $table->index(['user_id', 'is_active'], 'legal_entities_user_active_idx');
        });

        Schema::create('legal_entity_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_entity_id')->constrained('legal_entities')->cascadeOnDelete();
            $table->json('settings_json')->nullable();
            $table->timestamps();

            $table->unique('legal_entity_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_entity_settings');
        Schema::dropIfExists('legal_entities');
    }
};
