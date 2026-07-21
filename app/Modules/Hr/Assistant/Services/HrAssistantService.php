<?php

namespace App\Modules\Hr\Assistant\Services;

use App\Modules\Hr\Analytics\Models\HrAnalyticsSnapshot;
use App\Modules\Hr\Assistant\Models\HrAssistantQuery;
use App\Modules\Hr\Attendance\Models\HrAttendanceAnomaly;
use App\Modules\Hr\Compensation\Models\HrSalaryRecord;
use App\Modules\Hr\Core\Services\HrAuditService;
use App\Modules\Hr\Core\Services\TenantContext;
use App\Modules\Hr\Leave\Enums\LeaveRequestStatus;
use App\Modules\Hr\Leave\Models\HrLeaveRequest;
use App\Modules\Hr\Personnel\Models\HrEmployee;
use App\Modules\Hr\Recruitment\Models\HrJobPosting;
use App\Modules\Hr\Safety\Models\HrHealthRecord;
use App\Modules\Hr\Safety\Models\HrSafetyIncident;
use App\Modules\Hr\Support\Models\HrSupportTicket;
use App\Modules\Hr\Training\Models\HrTrainingEnrollment;
use App\Modules\Hr\Workforce\Models\HrWorkforcePlan;
use Illuminate\Support\Str;

class HrAssistantService
{
    public function __construct(private HrAuditService $audit) {}

    public function ask(string $question): HrAssistantQuery
    {
        abort_unless(auth()->user()?->hasHrPermission('hr.assistant.query'), 403);
        $question = trim(strip_tags($question));
        abort_if($question === '' || mb_strlen($question) > 1000, 422, 'Soru 1-1000 karakter arasında olmalıdır.');

        $normalized = Str::lower($question);
        [$intent, $status, $answer, $sources] = $this->isActionRequest($normalized)
            ? ['blocked', 'blocked', 'İK Asistanı salt okunurdur; kayıt oluşturamaz, değiştiremez, onaylayamaz veya silemez. İlgili modülde insan onayıyla ilerleyin.', []]
            : $this->answer($normalized);

        $query = HrAssistantQuery::create([
            'legal_entity_id' => app(TenantContext::class)->getId(),
            'user_id' => auth()->id(),
            'query_encrypted' => $question,
            'intent' => $intent,
            'status' => $status,
            'response_encrypted' => $answer,
            'sources' => $sources,
            'answered_at' => now(),
        ]);
        $this->audit->log('hr_assistant_queried', $query, null, ['intent' => $intent, 'status' => $status]);

        return $query;
    }

    private function answer(string $question): array
    {
        $tenantId = app(TenantContext::class)->getId();

        if (Str::contains($question, ['maaş', 'ücret', 'maliyet'])) {
            if (! auth()->user()?->hasHrPermission('hr.salary.view')) {
                return ['compensation', 'denied', 'Ücret ve maliyet özeti için `hr.salary.view` yetkisi gerekir.', ['hr_salary_records (yetki nedeniyle okunmadı)']];
            }

            $totals = HrSalaryRecord::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)
                ->whereIn('status', ['approved', 'superseded'])->whereDate('effective_from', '<=', today())
                ->orderByDesc('effective_from')->orderByDesc('version')->get()->unique('employee_id')
                ->groupBy('currency')->map(fn ($records) => $records->sum(fn (HrSalaryRecord $record) => $record->grossSalary()));
            $summary = $totals->map(fn ($total, $currency) => number_format($total, 2, ',', '.').' '.$currency)->implode(', ');

            return ['compensation', 'completed', 'Güncel onaylı aylık brüt ücret toplamı: '.($summary ?: 'kayıt yok').'. Para birimleri birbirine çevrilmeden ayrı gösterildi.', ['hr_salary_records.status/effective_from/currency']];
        }

        if (Str::contains($question, ['sağlık', 'muayene', 'işe uygun'])) {
            if (! auth()->user()?->hasHrPermission('hr.isg.view_health')) {
                return ['health', 'denied', 'Sağlık verileri ayrı ve hassas bir yetki alanıdır; `hr.isg.view_health` yetkisi olmadan yanıtlanmaz.', ['hr_health_records (yetki nedeniyle okunmadı)']];
            }
            $expiring = HrHealthRecord::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)
                ->whereBetween('expires_on', [today(), today()->addDays(30)])->count();

            return ['health', 'completed', "Önümüzdeki 30 günde süresi dolacak {$expiring} sağlık kaydı var. Asistan kişi veya sağlık sonucu açıklamaz.", ['hr_health_records.expires_on (toplulaştırılmış)']];
        }

        if (Str::contains($question, ['destek', 'ticket'])) {
            $query = HrSupportTicket::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId);
            if (! auth()->user()?->hasHrPermission('hr.support.manage')) {
                $query->where('requester_user_id', auth()->id());
            }
            $open = (clone $query)->whereIn('status', ['open', 'in_progress'])->count();
            $urgent = (clone $query)->where('priority', 'urgent')->where('status', '!=', 'closed')->count();

            return ['support', 'completed', "Görmeye yetkili olduğunuz {$open} açık destek talebi var; bunların {$urgent} tanesi acil öncelikte.", ['hr_support_tickets.status/priority']];
        }

        if (Str::contains($question, ['isg', 'iş güvenliği', 'kaza', 'ramak'])) {
            $query = HrSafetyIncident::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId);
            if (! auth()->user()?->hasHrPermission('hr.isg.manage')) {
                $employeeId = HrEmployee::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->where('user_id', auth()->id())->value('id');
                $query->where('reporter_employee_id', $employeeId ?: 0);
            }
            $open = (clone $query)->where('status', '!=', 'closed')->count();
            $critical = (clone $query)->whereIn('severity', ['high', 'critical'])->where('status', '!=', 'closed')->count();

            return ['safety', 'completed', "Görmeye yetkili olduğunuz {$open} açık İSG olayı var; {$critical} olay yüksek veya kritik şiddette.", ['hr_safety_incidents.status/severity']];
        }

        if (Str::contains($question, ['kadro', 'fte', 'bütçe'])) {
            $plan = HrWorkforcePlan::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->where('status', 'approved')->with('lines')->latest('approved_at')->first();
            if (! $plan) {
                return ['workforce', 'completed', 'Onaylanmış kadro planı bulunmuyor.', ['hr_workforce_plans.status']];
            }
            $planned = $plan->lines->sum('planned_fte');
            $actual = $plan->lines->sum('actual_fte_snapshot');

            return ['workforce', 'completed', "{$plan->name} planında ".number_format($planned, 2, ',', '.')." FTE planlandı; onay anlık görüntüsünde ".number_format($actual, 2, ',', '.')." FTE dolu.", ['hr_workforce_plans.status', 'hr_workforce_plan_lines.planned_fte/actual_fte_snapshot']];
        }

        if (Str::contains($question, ['izin'])) {
            $pending = HrLeaveRequest::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)
                ->whereIn('status', [LeaveRequestStatus::PendingManager->value, LeaveRequestStatus::PendingHr->value])->count();
            $today = HrLeaveRequest::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)
                ->where('status', LeaveRequestStatus::Approved->value)->whereDate('start_date', '<=', today())->whereDate('end_date', '>=', today())->count();

            return ['leave', 'completed', "{$pending} izin talebi onay bekliyor; bugün {$today} çalışan onaylı izinde.", ['hr_leave_requests.status/start_date/end_date']];
        }

        if (Str::contains($question, ['pdks', 'devam', 'anomali'])) {
            $count = HrAttendanceAnomaly::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->whereNull('resolved_at')->count();

            return ['attendance', 'completed', "Çözüm bekleyen {$count} PDKS anomalisi var.", ['hr_attendance_anomalies.resolved_at']];
        }

        if (Str::contains($question, ['eğitim', 'sertifika'])) {
            $completed = HrTrainingEnrollment::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->where('status', 'completed')->whereMonth('completed_at', now()->month)->whereYear('completed_at', now()->year)->count();

            return ['training', 'completed', "Bu ay {$completed} eğitim katılımı tamamlandı.", ['hr_training_enrollments.status/completed_at']];
        }

        if (Str::contains($question, ['aday', 'pozisyon', 'işe alım'])) {
            $open = HrJobPosting::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->where('status', 'published')->sum('headcount');

            return ['recruitment', 'completed', "Yayındaki ilanlarda toplam {$open} kişilik açık kadro var.", ['hr_job_postings.status/headcount']];
        }

        if (Str::contains($question, ['çalışan', 'personel', 'headcount', 'özet'])) {
            $active = HrEmployee::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->where('status', 'active')->count();
            $lastSnapshot = HrAnalyticsSnapshot::withoutGlobalScope('tenant')->where('legal_entity_id', $tenantId)->latest('generated_at')->first();
            $suffix = $lastSnapshot ? ' Son analitik anlık görüntüsü '.$lastSnapshot->generated_at->format('d.m.Y H:i').' tarihinde üretildi.' : '';

            return ['headcount', 'completed', "Aktif çalışan sayısı {$active}.{$suffix}", ['hr_employees.status', 'hr_analytics_snapshots.generated_at']];
        }

        return ['unknown', 'completed', 'Soruyu güvenli bir İK metriğiyle eşleştiremedim. Çalışan sayısı, izin, PDKS, eğitim, açık kadro, destek, İSG, kadro bütçesi veya yetkiniz varsa ücret özeti sorabilirsiniz.', []];
    }

    private function isActionRequest(string $question): bool
    {
        return preg_match('/\b(oluştur|sil|onayla|reddet|güncelle|değiştir|öde|işten çıkar|kapat)\b/u', $question) === 1;
    }
}
