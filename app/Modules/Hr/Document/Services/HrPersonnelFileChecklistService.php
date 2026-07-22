<?php

namespace App\Modules\Hr\Document\Services;

use App\Modules\Hr\Document\Models\HrEmployeeDocument;
use App\Modules\Hr\Personnel\Models\HrEmployee;

class HrPersonnelFileChecklistService
{
    public const REQUIRED_DOCUMENTS = [
        'identity_copy' => 'Kimlik Fotokopisi',
        'residence_certificate' => 'İkametgah Belgesi',
        'criminal_record' => 'Adli Sicil Kaydı',
        'population_register' => 'Nüfus Kayıt Örneği',
        'diploma' => 'Diploma Fotokopisi',
        'health_report' => 'Sağlık Raporu',
        'blood_type_card' => 'Kan Grubu Kartı',
        'employment_contract' => 'İş Sözleşmesi',
    ];

    public function analyzeEmployeeFile(int $tenantId, int $employeeId): array
    {
        try {
            $documents = HrEmployeeDocument::withoutGlobalScope('tenant')
                ->where('legal_entity_id', $tenantId)
                ->where('employee_id', $employeeId)
                ->get();
        } catch (\Throwable $e) {
            $documents = collect();
        }

        $presentTypes = $documents->pluck('document_type')->toArray();
        $totalRequired = count(self::REQUIRED_DOCUMENTS);

        $checklist = [];
        $presentCount = 0;

        foreach (self::REQUIRED_DOCUMENTS as $typeKey => $typeName) {
            $isPresent = in_array($typeKey, $presentTypes, true) || $documents->contains(fn($doc) => str_contains(strtolower($doc->title ?? ''), strtolower($typeName)));
            if ($isPresent) {
                $presentCount++;
            }
            $checklist[] = [
                'type_key' => $typeKey,
                'name' => $typeName,
                'is_present' => $isPresent,
                'document' => $isPresent ? $documents->first() : null,
            ];
        }

        $completionRate = $totalRequired > 0 ? (int) round(($presentCount / $totalRequired) * 100) : 0;

        return [
            'total_required' => $totalRequired,
            'present_count' => $presentCount,
            'missing_count' => $totalRequired - $presentCount,
            'completion_rate' => $completionRate,
            'is_complete' => $completionRate === 100,
            'checklist' => $checklist,
        ];
    }
}
