<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mp_products', function (Blueprint $table): void {
            $table->json('video_urls')->nullable()->after('image_urls');
        });
    }

    public function down(): void
    {
        Schema::table('mp_products', function (Blueprint $table): void {
            $table->dropColumn('video_urls');
        });
    }
};
