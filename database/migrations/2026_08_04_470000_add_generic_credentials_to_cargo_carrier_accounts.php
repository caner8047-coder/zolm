<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cargo_carrier_accounts') || Schema::hasColumn('cargo_carrier_accounts', 'credentials_encrypted')) {
            return;
        }

        Schema::table('cargo_carrier_accounts', function (Blueprint $table) {
            $table->longText('credentials_encrypted')
                ->nullable()
                ->after('cod_password_encrypted');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('cargo_carrier_accounts') || ! Schema::hasColumn('cargo_carrier_accounts', 'credentials_encrypted')) {
            return;
        }

        Schema::table('cargo_carrier_accounts', function (Blueprint $table) {
            $table->dropColumn('credentials_encrypted');
        });
    }
};
