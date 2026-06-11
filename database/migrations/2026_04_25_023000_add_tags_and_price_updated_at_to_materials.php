<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            if (!Schema::hasColumn('materials', 'tags')) {
                $table->json('tags')->nullable()->after('notes');
            }
            if (!Schema::hasColumn('materials', 'last_price_updated_at')) {
                $table->timestamp('last_price_updated_at')->nullable()->after('tags');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropColumn(['tags', 'last_price_updated_at']);
        });
    }
};
