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
    if (!Schema::hasColumn('mp_orders', 'cargo_company')) {
        DB::statement("ALTER TABLE mp_orders ADD COLUMN cargo_company VARCHAR(100) NULL AFTER barcode");
        echo "Migration successful: cargo_company column added to mp_orders table.\n";
    } else {
        echo "Column cargo_company already exists.\n";
    }
} catch (\Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
