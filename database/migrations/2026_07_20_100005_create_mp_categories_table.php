<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mp_categories', function (Blueprint $table) {
            $table->id();
            $table->string('marketplace')->index();
            $table->string('platform_category_id')->index();
            $table->string('parent_id')->nullable()->index();
            $table->string('name');
            $table->text('full_path')->nullable();
            $table->integer('level')->default(0);
            $table->boolean('is_leaf')->default(true);
            $table->boolean('is_active')->default(true);
            $table->json('raw_payload')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['marketplace', 'platform_category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mp_categories');
    }
};
