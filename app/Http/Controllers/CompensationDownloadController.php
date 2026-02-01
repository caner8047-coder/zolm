<?php

namespace App\Http\Controllers;

use App\Models\Compensation;
use App\Services\CompensationPdfService;
use Illuminate\Http\Request;

class CompensationDownloadController extends Controller
{
    protected $pdfService;

    public function __construct(CompensationPdfService $pdfService)
    {
        $this->pdfService = $pdfService;
    }

    /**
     * Dilekçe İndir
     */
    public function downloadPetition($id)
    {
        $compensation = Compensation::findOrFail($id);
        $pdfContent = $this->pdfService->generatePetition($compensation);
        
        return response($pdfContent)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="Dilekce_' . $compensation->id . '.pdf"');
    }

    /**
     * Form İndir
     */
    public function downloadForm($id)
    {
        $compensation = Compensation::findOrFail($id);
        $pdfContent = $this->pdfService->generateForm($compensation);
        
        return response($pdfContent)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="Tazmin_Formu_' . $compensation->id . '.pdf"');
    }

    /**
     * Tümünü ZIP olarak indir
     */
    public function downloadAll($id)
    {
        $compensation = Compensation::findOrFail($id);
        $zipPath = $this->pdfService->createZipPackage($compensation);
        
        return response()->download($zipPath)->deleteFileAfterSend(true);
    }
}
