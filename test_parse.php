<?php
require 'vendor/autoload.php';
$reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile('storage/app/private/livewire-tmp/rhcw5kGbqDckgvptvi8XbkvBCN0c4j4Bb5HNRTLA.xlsx');
$reader->setReadDataOnly(true);
$spreadsheet = $reader->load('storage/app/private/livewire-tmp/rhcw5kGbqDckgvptvi8XbkvBCN0c4j4Bb5HNRTLA.xlsx');
$sheet = $spreadsheet->getActiveSheet();

$header = [];
$firstDataRow = [];
$r = 0;
foreach ($sheet->getRowIterator() as $row) {
    $cellIterator = $row->getCellIterator();
    $cellIterator->setIterateOnlyExistingCells(false);
    $rowData = [];
    foreach ($cellIterator as $cell) {
        $v = $cell->getValue();
        if ($cell->getDataType() === \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA) {
           $v = $cell->getCalculatedValue();
        }
        $rowData[] = $v;
    }
    if ($r === 0) { $header = $rowData; }
    elseif ($r === 1) { $firstDataRow = $rowData; break; }
    $r++;
}
print_r(array_combine($header, $firstDataRow));
