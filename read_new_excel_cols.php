<?php
require __DIR__ . '/vendor/autoload.php';
$file = 'C:/laragon/www/zolm/Siparişler_Detaylı/prod_121057_65c3a49e-8713-4fba-8a4e-2d03ece469b6_Siparişleriniz_25.02.2026-12.22.xlsx';
$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);
$worksheet = $spreadsheet->getActiveSheet();
$headers = [];
foreach ($worksheet->getRowIterator(1, 5) as $row) {
    echo "ROW " . $row->getRowIndex() . ":\n";
    $cellIterator = $row->getCellIterator();
    $cellIterator->setIterateOnlyExistingCells(false);
    $rowVals = [];
    foreach ($cellIterator as $cell) {
        $val = $cell->getValue();
        if ($val) $rowVals[] = $val;
    }
    print_r($rowVals);
}
