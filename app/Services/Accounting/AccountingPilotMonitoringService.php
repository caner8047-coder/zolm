<?php

namespace App\Services\Accounting;

use App\Models\AccountingPilotFeedback;
use App\Models\AccountingPilotHealthSnapshot;
use Illuminate\Support\Facades\Schema;

class AccountingPilotMonitoringService
{
    /**
     * Get aggregate summary of pilot feedback and health status.
     */
    public function summary(?int $userId = null): array
    {
        $openCount = 0;
        $criticalCount = 0;
        $highCount = 0;
        $resolvedCount = 0;

        if (Schema::hasTable('accounting_pilot_feedbacks')) {
            $openFeedbackQuery = AccountingPilotFeedback::where('status', 'open');
            $resolvedFeedbackQuery = AccountingPilotFeedback::where('status', 'resolved');

            if ($userId !== null) {
                $openFeedbackQuery->where('user_id', $userId);
                $resolvedFeedbackQuery->where('user_id', $userId);
            }

            $openFeedbacks = $openFeedbackQuery->get();
            $resolvedCount = $resolvedFeedbackQuery->count();

            $openCount = $openFeedbacks->count();
            $criticalCount = $openFeedbacks->where('severity', 'critical')->count();
            $highCount = $openFeedbacks->where('severity', 'high')->count();
        }

        // Latest health snapshot (with schema check safety)
        $latestStatus = 'unknown';
        $latestScore = 100;
        $latestFailed = 0;
        $latestWarning = 0;
        $lastCheckedAt = null;

        if (Schema::hasTable('accounting_pilot_health_snapshots')) {
            $snapshotQuery = AccountingPilotHealthSnapshot::orderBy('created_at', 'desc');
            if ($userId !== null) {
                $snapshotQuery->where('user_id', $userId);
            }
            $latestSnapshot = $snapshotQuery->first();

            if ($latestSnapshot) {
                $latestStatus = $latestSnapshot->status;
                $latestScore = $latestSnapshot->score;
                $latestFailed = $latestSnapshot->failed_count;
                $latestWarning = $latestSnapshot->warning_count;
                $lastCheckedAt = $latestSnapshot->created_at ? $latestSnapshot->created_at->toDateTimeString() : null;
            }
        }

        // Decision call
        $decision = $this->calculateDecision($criticalCount, $highCount, $latestFailed, $latestWarning);

        return [
            'open_feedback_count' => $openCount,
            'critical_feedback_count' => $criticalCount,
            'high_feedback_count' => $highCount,
            'resolved_feedback_count' => $resolvedCount,
            'latest_health_status' => $latestStatus,
            'latest_health_score' => $latestScore,
            'latest_failed_count' => $latestFailed,
            'latest_warning_count' => $latestWarning,
            'last_health_checked_at' => $lastCheckedAt,
            'pilot_decision' => $decision['status'],
        ];
    }

    /**
     * Generate breakdown metrics grouped by severity, status, and category.
     */
    public function feedbackBreakdown(?int $userId = null): array
    {
        $severityBreakdown = ['low' => 0, 'medium' => 0, 'high' => 0, 'critical' => 0];
        $statusBreakdown = ['open' => 0, 'resolved' => 0];
        $categories = ['ui', 'accounting', 'stock', 'sales', 'purchase', 'pos', 'e_document', 'report', 'assistant', 'integration', 'other'];
        $categoryBreakdown = array_fill_keys($categories, 0);

        if (Schema::hasTable('accounting_pilot_feedbacks')) {
            $query = AccountingPilotFeedback::query();
            if ($userId !== null) {
                $query->where('user_id', $userId);
            }

            $feedbacks = $query->get();

            $severityBreakdown = [
                'low' => $feedbacks->where('severity', 'low')->count(),
                'medium' => $feedbacks->where('severity', 'medium')->count(),
                'high' => $feedbacks->where('severity', 'high')->count(),
                'critical' => $feedbacks->where('severity', 'critical')->count(),
            ];

            $statusBreakdown = [
                'open' => $feedbacks->where('status', 'open')->count(),
                'resolved' => $feedbacks->where('status', 'resolved')->count(),
            ];

            foreach ($feedbacks as $fb) {
                $category = 'other';
                if (isset($fb->category) && in_array($fb->category, $categories)) {
                    $category = $fb->category;
                } elseif (isset($fb->meta_json) && is_array($fb->meta_json) && isset($fb->meta_json['category']) && in_array($fb->meta_json['category'], $categories)) {
                    $category = $fb->meta_json['category'];
                } elseif (isset($fb->meta_json) && is_object($fb->meta_json) && isset($fb->meta_json->category) && in_array($fb->meta_json->category, $categories)) {
                    $category = $fb->meta_json->category;
                }
                
                $categoryBreakdown[$category]++;
            }
        }

        return [
            'severity' => $severityBreakdown,
            'status' => $statusBreakdown,
            'category' => $categoryBreakdown,
        ];
    }

    /**
     * Get health snapshots trend history.
     */
    public function healthTrend(?int $userId = null, int $limit = 10): array
    {
        $limit = max(1, min(50, $limit));

        if (!Schema::hasTable('accounting_pilot_health_snapshots')) {
            return [];
        }

        $query = AccountingPilotHealthSnapshot::orderBy('created_at', 'desc');
        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        return $query->limit($limit)->get()->map(function ($snapshot) {
            return [
                'checked_at' => $snapshot->created_at ? $snapshot->created_at->toDateTimeString() : null,
                'status' => $snapshot->status,
                'score' => $snapshot->score,
                'failed_count' => $snapshot->failed_count,
                'warning_count' => $snapshot->warning_count,
            ];
        })->toArray();
    }

    /**
     * Calculate automatic pilot decision.
     */
    public function decision(?int $userId = null): array
    {
        $sum = $this->summary($userId);
        return $this->calculateDecision(
            $sum['critical_feedback_count'],
            $sum['high_feedback_count'],
            $sum['latest_failed_count'],
            $sum['latest_warning_count']
        );
    }

    /**
     * Internal decision logic calculator.
     */
    protected function calculateDecision(int $criticalCount, int $highCount, int $latestFailed, int $latestWarning): array
    {
        $reasons = [];

        if ($criticalCount > 0) {
            $reasons[] = "Sistemde {$criticalCount} adet açık kritik geri bildirim bulunuyor.";
            return [
                'status' => 'blocked',
                'label' => 'Pilot durdurulmalı',
                'reasons' => $reasons,
            ];
        }

        if ($latestFailed > 0) {
            $reasons[] = "Son sağlık taramasında {$latestFailed} adet hata tespit edildi.";
            return [
                'status' => 'blocked',
                'label' => 'Sağlık hataları giderilmeli',
                'reasons' => $reasons,
            ];
        }

        if ($highCount > 0 || $latestWarning > 0) {
            if ($highCount > 0) {
                $reasons[] = "Sistemde {$highCount} adet açık yüksek öncelikli geri bildirim var.";
            }
            if ($latestWarning > 0) {
                $reasons[] = "Son sağlık taramasında {$latestWarning} adet uyarı tespit edildi.";
            }
            return [
                'status' => 'proceed_with_fixes',
                'label' => 'Düzeltme sprinti ile devam',
                'reasons' => $reasons,
            ];
        }

        return [
            'status' => 'proceed',
            'label' => 'Pilot devam edebilir',
            'reasons' => ['Herhangi bir bloklayıcı hata veya açık kritik geri bildirim bulunamadı.'],
        ];
    }
}
