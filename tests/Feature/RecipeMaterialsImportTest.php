<?php

namespace Tests\Feature;

use App\Livewire\RecipeMaterialsManager;
use App\Models\Material;
use App\Models\MaterialPriceHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class RecipeMaterialsImportTest extends TestCase
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

    public function test_material_excel_import_updates_existing_price_by_stock_code(): void
    {
        $user = User::factory()->create();
        $material = Material::query()->create([
            'user_id' => $user->id,
            'code' => 'HKMŞYLM00004',
            'name' => 'HM KUMAŞ DİĞER YILMAZ BELETTE MİLANO(3.78$)',
            'category' => 'fabric',
            'base_unit' => 'm',
            'default_waste_rate' => 0.10,
            'unit_price' => 181.33,
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $this->actingAs($user);

        Livewire::test(RecipeMaterialsManager::class)
            ->set('importFile', $this->makeExcelUpload([
                ['Stok Kodu', 'Stok Adı', 'Kategori', 'Birim', 'Fiyat', 'Para Birimi', 'Fire Oranı', 'Kumaş Eni (cm)', 'Durum'],
                ['HKMŞYLM00004', 'HM KUMAŞ DİĞER YILMAZ BELETTE MİLANO(3.78$)', 'Kumaş', 'm', '200.8658', 'TRY', '0.1', null, 'Aktif'],
            ], 'HamMaddeler_fiyatlari_guncellenmis.xlsx'))
            ->call('importExcel');

        $material->refresh();

        $this->assertSame(200.8658, (float) $material->unit_price);
        $this->assertNotNull($material->last_price_updated_at);
        $this->assertDatabaseHas('material_price_histories', [
            'material_id' => $material->id,
            'reason' => 'material_excel_import',
        ]);
        $this->assertSame(1, MaterialPriceHistory::query()->where('material_id', $material->id)->count());
    }

    public function test_price_sync_import_updates_existing_price_from_mf_fiyati_column(): void
    {
        $user = User::factory()->create();
        $material = Material::query()->create([
            'user_id' => $user->id,
            'code' => 'HAHŞDGR00001',
            'name' => 'HM AHŞAP DİĞER KAVAK KERESTE TAHTA M3',
            'category' => 'wood',
            'base_unit' => 'm3',
            'default_waste_rate' => 0.10,
            'unit_price' => 10000,
            'currency' => 'TRY',
            'is_active' => true,
        ]);

        $this->actingAs($user);

        Livewire::test(RecipeMaterialsManager::class)
            ->set('priceSyncFile', $this->makeExcelUpload([
                ['Kartlardan Dökümler', null, null],
                ['Stok Kodu', 'Açıklama', 'MF Fiyatı'],
                ['HAHŞDGR00001', 'HM AHŞAP DİĞER KAVAK KERESTE TAHTA M3', '12.000,0000'],
            ], 'zem11_turkce_karakter_duzeltilmis.xlsx'))
            ->call('importPriceSync');

        $material->refresh();

        $this->assertSame(12000.0, (float) $material->unit_price);
        $this->assertNotNull($material->last_price_updated_at);
        $this->assertDatabaseHas('material_price_histories', [
            'material_id' => $material->id,
            'reason' => 'smart_excel_sync',
        ]);
    }

    /**
     * @param  array<int, array<int, scalar|null>>  $rows
     */
    private function makeExcelUpload(array $rows, string $filename): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($rows, null, 'A1', true);

        $path = storage_path('framework/testing/'.uniqid('recipe-materials-', true).'-'.$filename);
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($path);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return UploadedFile::fake()
            ->createWithContent($filename, (string) file_get_contents($path))
            ->mimeType('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }
}
