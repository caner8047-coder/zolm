<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$reader = new \App\Services\Marketplace\TrendyolSearchResultReader();
$result = $reader->fetch('puf');
print_r($result);
