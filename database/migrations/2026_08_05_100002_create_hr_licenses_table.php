<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_licenses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legal_entity_id');
            $table->string('module_key', 100);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('max_employees')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['legal_entity_id', 'module_key']);
            $table->foreign('legal_entity_id')->references('id')->on('legal_entities')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_licenses');
    }
};
