<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile('storage/app/private/livewire-tmp/rhcw5kGbqDckgvptvi8XbkvBCN0c4j4Bb5HNRTLA.xlsx');
$reader->setReadDataOnly(true);
$spreadsheet = $reader->load('storage/app/private/livewire-tmp/rhcw5kGbqDckgvptvi8XbkvBCN0c4j4Bb5HNRTLA.xlsx');
$sheet = $spreadsheet->getActiveSheet();

$header = [];
$data = [];
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
    else { $data[] = array_combine($header, $rowData); }
    $r++;
}

$c = app(\App\Services\CampaignAnalysisService::class);
$c->initProductIndex();

$aliases = [
        'stock_code'       => ['SATICI STOK KODU', 'Satıcı Stok Kodu', 'Stok Kodu', 'STOK KODU'],
        'barcode'          => ['BARKOD', 'Barkod', 'Ürün Barkodu'],
        'product_name'     => ['ÜRÜN İSMİ', 'Ürün İsmi', 'Ürün Adı', 'ÜRÜN ADI'],
        'model_code'       => ['MODEL KODU', 'Model Kodu'],
        'current_price'    => ['GÜNCEL TSF', 'Güncel TSF', 'Güncel Fiyat', 'GÜNCEL FIYAT', 'TSF'],
        'current_commission' => ['GÜNCEL KOMİSYON', 'Güncel Komisyon', 'GÜNCEL KOMISYON', 'Komisyon'],
        'tariff1_price'    => ['1.Fiyat Alt Limit', '1. Fiyat Alt Limit'],
        'tariff2_price'    => ['2.Fiyat Üst Limiti', '2. Fiyat Üst Limiti', '2.FIYAT ÜST LİMİTİ'],
        'tariff2_commission' => ['2.KOMİSYON', '2. KOMİSYON', '2.Komisyon'],
        'tariff3_price'    => ['3.Fiyat Üst Limiti', '3. Fiyat Üst Limiti', '3.FIYAT ÜST LİMİTİ'],
        'tariff3_commission' => ['3.KOMİSYON', '3. KOMİSYON', '3.Komisyon'],
        'tariff4_price'    => ['4.Fiyat Üst Limiti', '4. Fiyat Üst Limiti', '4.FIYAT ÜST LİMİTİ'],
        'tariff4_commission' => ['4.KOMİSYON', '4. KOMİSYON', '4.Komisyon'],
        'tariff1_commission' => ['1.KOMİSYON', '1. KOMİSYON', '1.Komisyon'],
    ];
$map = $c->mapColumns($data[0], $aliases);

$opportunities = 0;
foreach ($data as $row) {
    $stockCode = $row[$map['stock_code'] ?? ''] ?? '';
    $p = $c->matchProduct('', $stockCode, '', '');
    if (!$p) continue;
    $costs = $c->getProductCosts($p);
    $totalCost = $costs['total_cost'];
    
    $currPrice = $c->parseNumber($row[$map['current_price']] ?? 0);
    $currComm = $c->parseNumber($row[$map['current_commission']] ?? 0);
    $currProfit = $c->calculateNetProfit($currPrice, $currComm, $totalCost);
    
    $bestProfit = $currProfit;
    $bestTariff = 'Mevcut';
    
    for ($i = 2; $i <= 4; $i++) {
        $tPrice = $c->parseNumber($row[$map["tariff{$i}_price"]] ?? 0);
        $tComm = $c->parseNumber($row[$map["tariff{$i}_commission"]] ?? 0);
        if ($tPrice > 0 && $tComm > 0) {
            $tProfit = $c->calculateNetProfit($tPrice, $tComm, $totalCost);
            if ($tProfit > $bestProfit) {
                $bestProfit = $tProfit;
                $bestTariff = "Tarife {$i}";
            }
        }
    }
    if ($bestProfit > $currProfit && $bestProfit > 0) {
        $opportunities++;
        echo "Found opportunity for $stockCode! CurrProfit: $currProfit, Tariff $bestTariff Profit: $bestProfit\n";
    }
}
echo "Total Opportunities: $opportunities\n";
