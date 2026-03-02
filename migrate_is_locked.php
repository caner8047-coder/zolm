<?php
// ZOLM DB Migration Script (CLI bypass)
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

try {
    if (!Schema::hasColumn('mp_periods', 'is_locked')) {
        DB::statement("ALTER TABLE mp_periods ADD COLUMN is_locked TINYINT(1) NOT NULL DEFAULT 0 AFTER total_audit_errors");
        echo "Migration successful: is_locked column added to mp_periods table.\n";
    } else {
        echo "Column is_locked already exists.\n";
    }
} catch (\Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
