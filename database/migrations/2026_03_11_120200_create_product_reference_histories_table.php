<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_reference_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('stok_kodu', 60)->index();
            $table->string('change_source', 40)->default('manual')->index();
            $table->string('note', 255)->nullable();
            $table->json('previous_snapshot')->nullable();
            $table->json('new_snapshot')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['stok_kodu', 'created_at'], 'prh_stok_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_reference_histories');
    }
};
