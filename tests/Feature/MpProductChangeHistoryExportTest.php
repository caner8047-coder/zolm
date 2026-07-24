<?php

namespace Tests\Feature;

use App\Livewire\MpProductsManager;
use App\Models\MarketplaceStore;
use App\Models\MpProduct;
use App\Models\MpProductChangeLog;
use App\Models\User;
use App\Services\MpProductChangeHistoryExportService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class MpProductChangeHistoryExportTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'mysql');
        config()->set('database.connections.mysql.host', 'mysql');
        config()->set('database.connections.mysql.port', '3306');
        config()->set('database.connections.mysql.database', $this->mysqlTestDatabaseName());
        config()->set('database.connections.mysql.username', 'sail');
        config()->set('database.connections.mysql.password', 'password');
        DB::purge('mysql');
        DB::reconnect('mysql');
        DB::setDefaultConnection('mysql');
    }

    public function test_export_service_creates_professional_typed_and_auditable_xlsx_file(): void
    {
        $user = User::factory()->create();

        $product = MpProduct::query()->create([
            'user_id' => $user->id,
            'product_name' => 'Test Puf Koltuk',
            'model_code' => 'TEST-PUF-101',
            'barcode' => '8690001112233',
            'sale_price' => 599.90,
            'cogs' => 250.00,
            'stock_quantity' => 20,
        ]);

        MpProductChangeLog::query()->create([
            'user_id' => $user->id,
            'mp_product_id' => $product->id,
            'change_scope' => 'product',
            'field_key' => 'sale_price',
            'field_label' => 'Satış fiyatı',
            'value_type' => 'money',
            'old_value' => '499.90',
            'new_value' => '599.90',
            'old_value_number' => 499.90,
            'new_value_number' => 599.90,
            'delta_number' => 100.00,
            'delta_percent' => 20.00,
            'source' => 'manual',
            'source_label' => 'Satır içi düzenleme',
            'changed_by' => $user->id,
            'changed_at' => now(),
        ]);

        MpProductChangeLog::query()->create([
            'user_id' => $user->id,
            'mp_product_id' => $product->id,
            'change_scope' => 'product',
            'field_key' => 'commission_rate',
            'field_label' => 'Komisyon oranı',
            'value_type' => 'percent',
            'old_value' => '18',
            'new_value' => '22',
            'old_value_number' => 18,
            'new_value_number' => 22,
            'delta_number' => 4,
            'delta_percent' => 22.2222,
            'source' => 'manual',
            'source_label' => '=FORMÜL ÇALIŞMAMALI',
            'changed_by' => $user->id,
            'changed_at' => now()->subMinute(),
        ]);

        MpProductChangeLog::query()->create([
            'user_id' => $user->id,
            'mp_product_id' => $product->id,
            'change_scope' => 'product',
            'field_key' => 'cogs',
            'field_label' => 'Ürün maliyeti',
            'value_type' => 'money',
            'old_value' => '200.00',
            'new_value' => '250.00',
            'old_value_number' => 200.00,
            'new_value_number' => 250.00,
            'delta_number' => 50.00,
            'delta_percent' => 25.00,
            'source' => 'manual',
            'source_label' => 'Maliyet düzenleme',
            'changed_by' => $user->id,
            'changed_at' => now()->subMinutes(2),
        ]);

        $logs = MpProductChangeLog::query()
            ->with(['changedByUser:id,name', 'store:id,marketplace,store_name'])
            ->where('mp_product_id', $product->id)
            ->latest('changed_at')
            ->latest('id')
            ->get();

        $tempPath = storage_path('app/temp/test_export_'.uniqid().'.xlsx');

        $service = new MpProductChangeHistoryExportService;
        $outputPath = $service->export($product, $logs, $tempPath);

        $this->assertFileExists($outputPath);

        $reader = IOFactory::createReader('Xlsx');
        $reader->setIncludeCharts(true);
        $spreadsheet = $reader->load($outputPath);
        $this->assertEquals(3, $spreadsheet->getSheetCount());

        $summarySheet = $spreadsheet->getSheetByName('Analiz ve Grafik');
        $this->assertNotNull($summarySheet);
        $this->assertEquals('ZOLM', $summarySheet->getCell('A1')->getValue());
        $this->assertEquals('Test Puf Koltuk', $summarySheet->getCell('A3')->getValue());
        $this->assertEquals(3, $summarySheet->getCell('A7')->getValue());
        $this->assertCount(2, $summarySheet->getChartCollection());
        $this->assertEquals('Maliyet ve satış fiyatı değişimi', $summarySheet->getCell('A43')->getValue());
        $this->assertEquals('Tarih ve Saat', $summarySheet->getCell('A45')->getValue());
        $this->assertEquals('Ürün Maliyeti (₺)', $summarySheet->getCell('B45')->getValue());
        $this->assertEquals('Satış Fiyatı (₺) · Ana Ürün', $summarySheet->getCell('C45')->getValue());
        $this->assertSame(DataType::TYPE_STRING, $summarySheet->getCell('A46')->getDataType());
        $this->assertSame(DataType::TYPE_NUMERIC, $summarySheet->getCell('B46')->getDataType());
        $this->assertSame(DataType::TYPE_NUMERIC, $summarySheet->getCell('C46')->getDataType());
        $this->assertEqualsWithDelta(200.00, (float) $summarySheet->getCell('B46')->getValue(), 0.0001);
        $this->assertEqualsWithDelta(499.90, (float) $summarySheet->getCell('C46')->getValue(), 0.0001);

        $logsSheet = $spreadsheet->getSheetByName('Kayıtlar');
        $this->assertNotNull($logsSheet);
        $this->assertEquals('Kayıt ID', $logsSheet->getCell('A9')->getValue());
        $this->assertSame(DataType::TYPE_STRING, $logsSheet->getCell('A10')->getDataType());
        $this->assertSame(DataType::TYPE_NUMERIC, $logsSheet->getCell('B10')->getDataType());
        $this->assertSame(DataType::TYPE_NUMERIC, $logsSheet->getCell('N10')->getDataType());
        $this->assertEqualsWithDelta(499.90, (float) $logsSheet->getCell('N10')->getValue(), 0.0001);
        $this->assertSame(DataType::TYPE_NUMERIC, $logsSheet->getCell('Q10')->getDataType());
        $this->assertEqualsWithDelta(0.20, (float) $logsSheet->getCell('Q10')->getValue(), 0.0001);

        $this->assertEquals('Komisyon oranı', $logsSheet->getCell('F11')->getValue());
        $this->assertSame(DataType::TYPE_NUMERIC, $logsSheet->getCell('N11')->getDataType());
        $this->assertEqualsWithDelta(0.18, (float) $logsSheet->getCell('N11')->getValue(), 0.0001);
        $this->assertSame(DataType::TYPE_STRING, $logsSheet->getCell('T11')->getDataType());
        $this->assertSame('=FORMÜL ÇALIŞMAMALI', $logsSheet->getCell('T11')->getValue());

        $dictionarySheet = $spreadsheet->getSheetByName('Veri Sözlüğü');
        $this->assertNotNull($dictionarySheet);
        $this->assertEquals('RAPOR METADATASI VE VERİ SÖZLÜĞÜ', $dictionarySheet->getCell('B1')->getValue());

        if (file_exists($outputPath)) {
            unlink($outputPath);
        }
    }

    public function test_livewire_action_exports_product_change_history(): void
    {
        $user = User::factory()->create();

        $product = MpProduct::query()->create([
            'user_id' => $user->id,
            'product_name' => 'Ahşap Sandalye',
            'model_code' => 'SND-01',
            'sale_price' => 1200.00,
        ]);

        MpProductChangeLog::query()->create([
            'user_id' => $user->id,
            'mp_product_id' => $product->id,
            'change_scope' => 'product',
            'field_key' => 'cogs',
            'field_label' => 'Ürün maliyeti',
            'value_type' => 'money',
            'old_value' => '400.00',
            'new_value' => '500.00',
            'old_value_number' => 400.00,
            'new_value_number' => 500.00,
            'delta_number' => 100.00,
            'delta_percent' => 25.00,
            'source_label' => 'Maliyet Güncelleme',
            'changed_at' => now(),
        ]);

        $this->actingAs($user);

        Livewire::test(MpProductsManager::class)
            ->call('exportProductChangeHistory', $product->id)
            ->assertFileDownloaded();
    }

    public function test_sale_price_chart_does_not_mix_different_store_series(): void
    {
        $user = User::factory()->create();
        $product = MpProduct::query()->create([
            'user_id' => $user->id,
            'product_name' => 'Çok Mağazalı Ürün',
            'model_code' => 'MULTI-STORE-01',
            'sale_price' => 950.00,
            'cogs' => 400.00,
        ]);

        $costLog = new MpProductChangeLog([
            'mp_product_id' => $product->id,
            'change_scope' => 'product',
            'field_key' => 'cogs',
            'field_label' => 'Ürün maliyeti',
            'value_type' => 'money',
            'old_value' => '300.00',
            'new_value' => '400.00',
            'old_value_number' => 300.00,
            'new_value_number' => 400.00,
            'changed_at' => now()->subMinutes(3),
        ]);
        $costLog->id = 100;

        $olderStorePrice = new MpProductChangeLog([
            'mp_product_id' => $product->id,
            'channel_listing_id' => 501,
            'store_id' => 51,
            'change_scope' => 'listing',
            'field_key' => 'sale_price',
            'field_label' => 'Kanal satış fiyatı',
            'value_type' => 'money',
            'old_value' => '700.00',
            'new_value' => '750.00',
            'old_value_number' => 700.00,
            'new_value_number' => 750.00,
            'changed_at' => now()->subMinutes(2),
        ]);
        $olderStorePrice->id = 101;
        $olderStorePrice->setRelation('store', new MarketplaceStore([
            'marketplace' => 'trendyol',
            'store_name' => 'Mağaza A',
        ]));

        $latestStorePrice = new MpProductChangeLog([
            'mp_product_id' => $product->id,
            'channel_listing_id' => 502,
            'store_id' => 52,
            'change_scope' => 'listing',
            'field_key' => 'sale_price',
            'field_label' => 'Kanal satış fiyatı',
            'value_type' => 'money',
            'old_value' => '900.00',
            'new_value' => '950.00',
            'old_value_number' => 900.00,
            'new_value_number' => 950.00,
            'changed_at' => now()->subMinute(),
        ]);
        $latestStorePrice->id = 102;
        $latestStorePrice->setRelation('store', new MarketplaceStore([
            'marketplace' => 'hepsiburada',
            'store_name' => 'Mağaza B',
        ]));

        $tempPath = storage_path('app/temp/test_multi_store_export_'.uniqid().'.xlsx');
        $outputPath = (new MpProductChangeHistoryExportService)->export(
            $product,
            collect([$costLog, $olderStorePrice, $latestStorePrice]),
            $tempPath
        );

        $reader = IOFactory::createReader('Xlsx');
        $reader->setIncludeCharts(true);
        $spreadsheet = $reader->load($outputPath);
        $summarySheet = $spreadsheet->getSheetByName('Analiz ve Grafik');

        $this->assertNotNull($summarySheet);
        $this->assertEquals('Satış Fiyatı (₺) · Hepsiburada · Mağaza B', $summarySheet->getCell('C45')->getValue());
        $this->assertEqualsWithDelta(900.00, (float) $summarySheet->getCell('C46')->getValue(), 0.0001);
        $this->assertEqualsWithDelta(900.00, (float) $summarySheet->getCell('C47')->getValue(), 0.0001);
        $this->assertEqualsWithDelta(950.00, (float) $summarySheet->getCell('C48')->getValue(), 0.0001);
        $this->assertCount(2, $summarySheet->getChartCollection());

        if (file_exists($outputPath)) {
            unlink($outputPath);
        }
    }

    public function test_export_history_query_does_not_silently_truncate_after_one_thousand_records(): void
    {
        $user = User::factory()->create();
        $product = MpProduct::query()->create([
            'user_id' => $user->id,
            'product_name' => 'Yoğun Geçmişli Ürün',
            'model_code' => 'HISTORY-1001',
            'sale_price' => 1000,
        ]);

        $now = now();
        $rows = [];
        foreach (range(1, 1001) as $index) {
            $rows[] = [
                'user_id' => $user->id,
                'mp_product_id' => $product->id,
                'change_scope' => 'product',
                'field_key' => 'stock_quantity',
                'field_label' => 'Stok',
                'value_type' => 'integer',
                'old_value' => (string) ($index - 1),
                'new_value' => (string) $index,
                'old_value_number' => $index - 1,
                'new_value_number' => $index,
                'delta_number' => 1,
                'source' => 'test',
                'source_label' => 'Test',
                'changed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('mp_product_change_logs')->insert($chunk);
        }

        $this->actingAs($user);
        $history = Livewire::test(MpProductsManager::class)
            ->instance()
            ->productChangeHistory($product, null);

        $this->assertCount(1001, $history);
    }
}
