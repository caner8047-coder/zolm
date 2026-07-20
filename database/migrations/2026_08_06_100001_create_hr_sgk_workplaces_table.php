<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_sgk_workplaces', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legal_entity_id');
            $table->string('name', 255);
            $table->string('code', 50);
            $table->string('sgk_workplace_no', 50)->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['legal_entity_id', 'code']);
            $table->foreign('legal_entity_id')->references('id')->on('legal_entities')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_sgk_workplaces');
    }
};
