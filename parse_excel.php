<?php
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

$path = "/Volumes/TWINMOS/zolm/üretim ciro/Ürün Ciro Takip.xlsx";
$reader = IOFactory::createReaderForFile($path);
$reader->setReadDataOnly(true);
$spreadsheet = $reader->load($path);

foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
    echo "Sheet: " . $sheet->getTitle() . "\n";
    $data = $sheet->rangeToArray("A1:J10", null, true, false, false);
    print_r($data);
    break; // Just the first sheet
}
