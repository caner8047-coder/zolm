<?php

namespace App\Services;

use App\Models\Profile;
use App\Models\Report;
use App\Models\ReportFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ProductionEngine
{
    protected ExcelService $excelService;

    public function __construct(ExcelService $excelService)
    {
        $this->excelService = $excelService;
    }

    /**
     * Run the production engine
     */
    public function run(UploadedFile $uploadedFile, Profile $profile, Report $report): array
    {
        $report->update(['status' => 'processing']);

        try {
            $inputConfig = $profile->input_config ?? [];
            $outputConfig = $profile->output_config ?? [];

            // Excel'i oku
            $sheetName = $inputConfig['sheet_name'] ?? 'Siparişlerim-Detaylı';
            $data = $this->excelService->importOrderXls($uploadedFile, $sheetName);

            // Kategori sütunu
            $categoryColumn = $inputConfig['category_column'] ?? 'Renk Etiketi';
            $categories = $inputConfig['categories'] ?? [
                'BERJER' => 'BERJER',
                'KÖŞE & KANEPE' => 'KÖŞE VE KANEPE',
                'PUF & BENCH' => 'PUF',
            ];

            // Adet'i numericyap
            $data = $data->map(function ($item) {
                $item['Adet'] = (int) ($item['Adet'] ?? 0);
                return $item;
            });

            // Grup ata
            $data = $data->map(function ($item) use ($categoryColumn, $categories) {
                $colorLabel = $item[$categoryColumn] ?? '';
                $item['GRUP'] = $categories[$colorLabel] ?? null;
                return $item;
            });

            // Sadece üretime gidecek satırlar
            $productionData = $data->whereNotNull('GRUP');

            $generatedFiles = [];
            $storageDir = 'reports/' . $report->id;
            Storage::disk('local')->makeDirectory($storageDir);

            // 1. GÜNLÜK SİPARİŞLER dosyası
            $dailySheets = [];
            foreach (['BERJER', 'KÖŞE VE KANEPE', 'PUF'] as $group) {
                $groupData = $productionData->where('GRUP', $group);
                if ($groupData->isNotEmpty()) {
                    $dailySheets[] = [
                        'name' => "{$group} TOPLAM SİPARİŞ",
                        'data' => $this->excelService->createSummary($groupData),
                    ];
                    $dailySheets[] = [
                        'name' => "{$group} SİPARİŞ TAKİP",
                        'data' => $this->excelService->createDetail($groupData),
                    ];
                }
            }

            if (!empty($dailySheets)) {
                $filename = 'GÜNLÜK SİPARİŞLER_OTOMATİK.xlsx';
                $filePath = $storageDir . '/' . $filename;
                $fullPath = Storage::disk('local')->path($filePath);
                $this->excelService->exportToXlsx($dailySheets, $fullPath);
                
                $generatedFiles[] = ReportFile::create([
                    'report_id' => $report->id,
                    'filename' => $filename,
                    'file_path' => $filePath,
                    'sheet_type' => 'daily',
                ]);
            }

            // 2. Her grup için ayrı dosyalar
            foreach (['BERJER', 'PUF', 'KÖŞE VE KANEPE'] as $group) {
                $groupData = $productionData->where('GRUP', $group);
                if ($groupData->isNotEmpty()) {
                    $sheets = [
                        ['name' => 'DENİZLİ TOPLAM SİPARİŞ', 'data' => $this->excelService->createSummary($groupData)],
                        ['name' => 'NAZİLLİ SİPARİŞ TAKİP', 'data' => $this->excelService->createDetail($groupData)],
                        ['name' => 'NAZİLLİ KARGO TAKİP', 'data' => $this->excelService->createDetail($groupData)],
                    ];

                    $filename = "{$group}_OTOMATİK.xlsx";
                    $filePath = $storageDir . '/' . $filename;
                    $fullPath = Storage::disk('local')->path($filePath);
                    $this->excelService->exportToXlsx($sheets, $fullPath);

                    $generatedFiles[] = ReportFile::create([
                        'report_id' => $report->id,
                        'filename' => $filename,
                        'file_path' => $filePath,
                        'sheet_type' => strtolower($group),
                    ]);
                }
            }

            $report->update(['status' => 'success']);

            return [
                'success' => true,
                'files' => $generatedFiles,
                'message' => count($generatedFiles) . ' dosya başarıyla oluşturuldu.',
            ];

        } catch (\Exception $e) {
            $report->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'files' => [],
                'message' => 'Hata: ' . $e->getMessage(),
            ];
        }
    }
}
