<?php
require __DIR__ . '/vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;

$file = 'C:/laragon/www/zolm/Siparişler_Detaylı/prod_121057_65c3a49e-8713-4fba-8a4e-2d03ece469b6_Siparişleriniz_25.02.2026-12.22.xlsx';
$spreadsheet = IOFactory::load($file);
$worksheet = $spreadsheet->getActiveSheet();
$headers = [];

foreach ($worksheet->getRowIterator(1, 3) as $row) {
    if ($row->getRowIndex() === 1) continue; // Skip legal text
    
    $rowVals = [];
    foreach ($row->getCellIterator() as $cell) {
        $val = $cell->getValue();
        if ($val instanceof RichText) {
            $val = $val->getPlainText();
        }
        if ($val) $rowVals[] = trim($val);
    }
    if (!empty($rowVals)) {
        $headers[] = $rowVals;
    }
}
echo json_encode($headers, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
