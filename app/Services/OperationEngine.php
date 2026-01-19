<?php

namespace App\Services;

use App\Models\Profile;
use App\Models\Report;
use App\Models\ReportFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class OperationEngine
{
    protected ExcelService $excelService;

    public function __construct(ExcelService $excelService)
    {
        $this->excelService = $excelService;
    }

    /**
     * Run the operation engine
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

            // Adet'i numeric yap
            $data = $data->map(function ($item) {
                $item['Adet'] = (int) ($item['Adet'] ?? 0);
                return $item;
            });

            $generatedFiles = [];
            $storageDir = 'reports/' . $report->id;
            Storage::disk('local')->makeDirectory($storageDir);

            // Operasyon listesi oluştur
            $operationData = $this->excelService->createDetail($data);

            $sheets = [
                ['name' => 'OPERASYON LİSTE', 'data' => $operationData],
            ];

            $filename = 'OPERASYON_OTOMATİK.xlsx';
            $filePath = $storageDir . '/' . $filename;
            $fullPath = Storage::disk('local')->path($filePath);
            $this->excelService->exportToXlsx($sheets, $fullPath);

            $generatedFiles[] = ReportFile::create([
                'report_id' => $report->id,
                'filename' => $filename,
                'file_path' => $filePath,
                'sheet_type' => 'operation',
            ]);

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
