<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bloklayıcı 5: source_key idempotency için collections ve payments tablolarına
 * source_key sütunu ekle.
 * unique: user_id + source_key (nullable safe — iki NULL birbirini ihlal etmez).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            if (!Schema::hasColumn('collections', 'source_key')) {
                $table->string('source_key', 191)->nullable()->after('status');
                $table->unique(['user_id', 'source_key'], 'collections_user_source_key_unique');
            }
        });

        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasColumn('payments', 'source_key')) {
                $table->string('source_key', 191)->nullable()->after('status');
                $table->unique(['user_id', 'source_key'], 'payments_user_source_key_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            if (Schema::hasColumn('collections', 'source_key')) {
                $table->dropUnique('collections_user_source_key_unique');
                $table->dropColumn('source_key');
            }
        });

        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'source_key')) {
                $table->dropUnique('payments_user_source_key_unique');
                $table->dropColumn('source_key');
            }
        });
    }
};
