<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_branches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legal_entity_id');
            $table->unsignedBigInteger('sgk_workplace_id')->nullable();
            $table->string('name', 255);
            $table->string('code', 50);
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('phone', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['legal_entity_id', 'code']);
            $table->foreign('legal_entity_id')->references('id')->on('legal_entities')->onDelete('cascade');
            $table->foreign('sgk_workplace_id')->references('id')->on('hr_sgk_workplaces')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_branches');
    }
};
