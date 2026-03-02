<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('mp_product_id')->nullable()->constrained('mp_products')->nullOnDelete();

            $table->string('name', 255);
            $table->string('version', 20)->default('v1');
            $table->string('status', 15)->default('draft');
            // draft, active, archived
            $table->text('notes')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['user_id', 'status']);
            $table->unique(['mp_product_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipes');
    }
};
