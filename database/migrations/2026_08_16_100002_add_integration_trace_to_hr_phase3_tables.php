<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('hr_expenses', function (Blueprint $table) {
            $table->string('finance_reference', 190)->nullable()->after('payment_reference');
        });
        Schema::table('hr_advances', function (Blueprint $table) {
            $table->string('finance_reference', 190)->nullable()->after('payment_reference');
        });
        Schema::table('hr_assets', function (Blueprint $table) {
            $table->string('stock_item_reference', 190)->nullable()->after('barcode');
        });
        Schema::table('hr_asset_assignments', function (Blueprint $table) {
            $table->timestamp('accepted_at')->nullable()->after('expected_return_at');
            $table->foreignId('accepted_by')->nullable()->after('accepted_at')->constrained('users')->nullOnDelete();
            $table->string('acceptance_ip', 45)->nullable()->after('accepted_by');
            $table->string('acceptance_statement_version', 30)->nullable()->after('acceptance_ip');
        });
    }

    public function down(): void
    {
        Schema::table('hr_asset_assignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('accepted_by');
            $table->dropColumn(['accepted_at', 'acceptance_ip', 'acceptance_statement_version']);
        });
        Schema::table('hr_assets', fn (Blueprint $table) => $table->dropColumn('stock_item_reference'));
        Schema::table('hr_advances', fn (Blueprint $table) => $table->dropColumn('finance_reference'));
        Schema::table('hr_expenses', fn (Blueprint $table) => $table->dropColumn('finance_reference'));
    }
};
