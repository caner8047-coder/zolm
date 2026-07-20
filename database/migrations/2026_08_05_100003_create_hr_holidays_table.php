<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_holidays', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legal_entity_id');
            $table->string('name', 255);
            $table->date('date');
            $table->unsignedSmallInteger('year');
            $table->string('type', 20)->default('national');
            $table->boolean('is_recurring')->default(true);
            $table->timestamps();

            $table->unique(['legal_entity_id', 'date']);
            $table->foreign('legal_entity_id')->references('id')->on('legal_entities')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_holidays');
    }
};
