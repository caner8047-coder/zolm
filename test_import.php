<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\MpOrder;
use App\Models\MpTransaction;
use App\Models\MpInvoice;
use App\Models\MpPeriod;
use App\Models\MpAuditLog;
use App\Services\MarketplaceImportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;

// 1. Clean DB safely
Schema::disableForeignKeyConstraints();
MpAuditLog::truncate();
MpOrder::truncate();
MpTransaction::truncate();
MpInvoice::truncate();
MpPeriod::truncate();
Schema::enableForeignKeyConstraints();
echo "Database truncated safely.\n";

// 2. Create Period
$period = MpPeriod::create([
    'month' => '01',
    'year' => '2025',
    'status' => 'draft',
    'has_cogs' => false
]);

// 3. Mock UploadedFile
$filePath = 'C:\laragon\www\zolm\pazaryerimuh\Sipariş Kayıtları\SiparisKayitlari_2025-01-01_2025-03-31_26174.xlsx';
$fileName = basename($filePath);
$mimeType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

$uploadedFile = new UploadedFile($filePath, $fileName, $mimeType, null, true);

// 4. Run Import
$service = new MarketplaceImportService();
echo "Starting import for Orders...\n";
$result = $service->importOrders($uploadedFile, $period);
echo "Import Result: " . json_encode($result) . "\n";

// 5. Verify Database Values
$orders = MpOrder::take(3)->get(['order_number', 'status', 'gross_amount', 'cargo_amount', 'net_hakedis', 'commission_amount']);
echo "\nSample Imported Orders:\n";
echo json_encode($orders, JSON_PRETTY_PRINT) . "\n\n";

echo "Total Gross Amount in DB: " . MpOrder::sum('gross_amount') . "\n";
echo "Total Orders in DB: " . MpOrder::count() . "\n";
echo "Teslim Edilenler: " . MpOrder::where('status', 'Teslim Edilenler')->count() . "\n";
echo "İptal Edildi: " . MpOrder::where('status', 'İptal Edildi')->count() . "\n";
