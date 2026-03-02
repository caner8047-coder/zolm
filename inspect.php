<?php
require 'vendor/autoload.php';

$file = 'C:\laragon\www\zolm\pazaryerimuh\Ödeme Detay\Kasım\OdemeDetay_TR_2025-11-06_121057_53128120.xlsx';

$reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
$reader->setReadDataOnly(true);
$spreadsheet = $reader->load($file);
$sheet = $spreadsheet->getActiveSheet();

$data = array_slice($sheet->toArray(), 0, 5);
echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
