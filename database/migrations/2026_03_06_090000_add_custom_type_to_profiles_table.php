<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // profiles.type başlangıçta ENUM('production','operation').
        // Özel motor profilleri için "custom" tipini ekliyoruz.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE profiles MODIFY COLUMN type ENUM('production','operation','custom') NOT NULL");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::table('profiles')->where('type', 'custom')->update(['type' => 'operation']);
            DB::statement("ALTER TABLE profiles MODIFY COLUMN type ENUM('production','operation') NOT NULL");
        }
    }
};
