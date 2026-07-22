<?php

namespace App\Modules\Hr\Document\Services;

use App\Models\LegalEntity;
use App\Modules\Hr\Asset\Models\HrAssetAssignment;
use App\Modules\Hr\Attendance\Models\HrAttendanceAnomaly;
use App\Modules\Hr\Leave\Models\HrLeaveRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class HrPdfDocumentGenerator
{
    public function streamLeaveFormPdf(HrLeaveRequest $leaveRequest): Response
    {
        $employee = $leaveRequest->employee;
        $employment = $employee->activeEmployment;
        $legalEntity = LegalEntity::find($leaveRequest->legal_entity_id);

        $pdf = Pdf::loadView('hr.pdf.leave-form', [
            'companyName' => $legalEntity?->name ?? 'ZOLM İK TEKNOLOJİ A.Ş.',
            'leaveRequest' => $leaveRequest,
            'employee' => $employee,
            'employment' => $employment,
        ]);

        $fileName = "Izin_Formu_{$employee->employee_number}_" . date('Ymd') . ".pdf";
        return $pdf->download($fileName);
    }

    public function streamAssetAssignmentPdf(HrAssetAssignment $assignment): Response
    {
        $employee = $assignment->employee;
        $employment = $employee->activeEmployment;
        $asset = $assignment->asset;
        $legalEntity = LegalEntity::find($assignment->legal_entity_id);

        $pdf = Pdf::loadView('hr.pdf.asset-assignment-form', [
            'companyName' => $legalEntity?->name ?? 'ZOLM İK TEKNOLOJİ A.Ş.',
            'assignment' => $assignment,
            'asset' => $asset,
            'employee' => $employee,
            'employment' => $employment,
        ]);

        $fileName = "Zimmet_Tutanagi_{$asset->asset_code}_{$employee->employee_number}.pdf";
        return $pdf->download($fileName);
    }

    public function streamAbsenceNoticePdf(HrAttendanceAnomaly $anomaly): Response
    {
        $employee = $anomaly->employee;
        $employment = $employee->activeEmployment;
        $legalEntity = LegalEntity::find($anomaly->legal_entity_id);

        $pdf = Pdf::loadView('hr.pdf.absence-notice-form', [
            'companyName' => $legalEntity?->name ?? 'ZOLM İK TEKNOLOJİ A.Ş.',
            'anomaly' => $anomaly,
            'employee' => $employee,
            'employment' => $employment,
        ]);

        $fileName = "Devamsizlik_Tutanagi_{$employee->employee_number}_" . date('Ymd') . ".pdf";
        return $pdf->download($fileName);
    }

    public function streamDefenseRequestPdf($evaluation): Response
    {
        $employee = is_object($evaluation) ? ($evaluation->employee ?? null) : null;
        $employment = $employee?->activeEmployment;

        $pdf = Pdf::loadView('hr.pdf.defense-request-form', [
            'employee_name' => $employee ? "{$employee->first_name} {$employee->last_name}" : 'Ahmet Yılmaz',
            'national_id' => $employee?->national_id ? '*****' . substr($employee->national_id, -4) : '12345678901',
            'employee_number' => $employee?->employee_number ?? 'EMP-001',
            'department' => $employment?->department?->name ?? 'Yazılım ve Teknoloji',
            'job_title' => $employment?->job_title ?? 'Kıdemli Geliştirici',
            'evaluation_period' => is_object($evaluation) ? ($evaluation->period_name ?? date('Y') . ' Dönemi') : '2026 Q2 Dönemi',
            'performance_notes' => is_object($evaluation) ? ($evaluation->review_notes ?? '') : '',
        ]);

        $fileName = "Yazili_Savunma_Istemi_" . date('Ymd') . ".pdf";
        return $pdf->download($fileName);
    }
}
