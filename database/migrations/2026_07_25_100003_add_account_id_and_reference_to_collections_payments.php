<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            if (!Schema::hasColumn('collections', 'account_id')) {
                $table->unsignedBigInteger('account_id')->nullable()->after('journal_entry_id');
            }
            if (!Schema::hasColumn('collections', 'reference_number')) {
                $table->string('reference_number', 100)->nullable()->after('payment_method');
            }
        });

        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasColumn('payments', 'account_id')) {
                $table->unsignedBigInteger('account_id')->nullable()->after('journal_entry_id');
            }
            if (!Schema::hasColumn('payments', 'reference_number')) {
                $table->string('reference_number', 100)->nullable()->after('payment_method');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'reference_number')) {
                $table->dropColumn('reference_number');
            }
            if (Schema::hasColumn('payments', 'account_id')) {
                $table->dropColumn('account_id');
            }
        });

        Schema::table('collections', function (Blueprint $table) {
            if (Schema::hasColumn('collections', 'reference_number')) {
                $table->dropColumn('reference_number');
            }
            if (Schema::hasColumn('collections', 'account_id')) {
                $table->dropColumn('account_id');
            }
        });
    }
};
