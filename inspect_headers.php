<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$files = glob(storage_path('app/marketplace-imports/*.xlsx'));
if (empty($files)) {
    echo "No files found\n";
    exit;
}

$file = end($files); // latest
echo "Checking file: " . basename($file) . "\n";

$reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($file);
$reader->setReadDataOnly(true);
$spreadsheet = $reader->load($file);
$sheet = $spreadsheet->getActiveSheet();

foreach ($sheet->getRowIterator(1, 5) as $row) {
    echo "ROW " . $row->getRowIndex() . ":\n";
    foreach ($row->getCellIterator() as $col => $cell) {
        $val = $cell->getValue();
        if ($val) {
            echo "  $col => $val\n";
        }
    }
}
