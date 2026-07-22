<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mp_brands', function (Blueprint $table) {
            $table->id();
            $table->string('marketplace')->index();
            $table->string('platform_brand_id')->index();
            $table->string('name');
            $table->string('normalized_name')->index();
            $table->boolean('is_active')->default(true);
            $table->json('raw_payload')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['marketplace', 'platform_brand_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mp_brands');
    }
};
