<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('ad_import_batches')->cascadeOnDelete();
            $table->integer('row_number');
            $table->json('raw_payload');
            $table->json('normalized_payload')->nullable();
            $table->json('validation_errors')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamps();
            $table->unique(['batch_id', 'row_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_import_rows');
    }
};
