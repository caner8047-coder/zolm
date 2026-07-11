<?php

namespace App\Services\Accounting;

use App\Models\AccountingPilotFeedback;
use Illuminate\Support\Facades\Schema;

class AccountingPilotBacklogService
{
    /** Modüller arası risk bonusu uygulanan yüksek riskli modüller */
    protected const HIGH_RISK_MODULES = [
        'accounting', 'stock', 'sales', 'purchase', 'pos', 'e_document', 'integration',
    ];

    /**
     * Açık feedback kayıtlarından önceliklendirilmiş backlog listesi üretir.
     */
    public function build(?int $userId = null): array
    {
        if (!Schema::hasTable('accounting_pilot_feedbacks')) {
            return [];
        }

        $query = AccountingPilotFeedback::where('status', 'open')
            ->orderBy('created_at', 'asc');

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        return $query->get()->map(function (AccountingPilotFeedback $fb) {
            $score = $this->priorityScore($fb);
            $action = $this->recommendedAction(['priority_score' => $score]);
            $owner = $this->ownerHint($fb);
            $phase = $this->targetPhase($action);

            return [
                'id' => $fb->id,
                'module' => $fb->module,
                'title' => $fb->title,
                'severity' => $fb->severity,
                'type' => $fb->type,
                'status' => $fb->status,
                'priority_score' => $score,
                'recommended_action' => $action,
                'owner_hint' => $owner,
                'target_phase' => $phase,
                'created_at' => $fb->created_at?->toDateTimeString(),
            ];
        })->sortByDesc('priority_score')->values()->toArray();
    }

    /**
     * Öncelik puanını hesaplar.
     * @param array|AccountingPilotFeedback $feedback
     */
    public function priorityScore(array|AccountingPilotFeedback $feedback): int
    {
        $severity = is_array($feedback) ? ($feedback['severity'] ?? 'low') : $feedback->severity;
        $type     = is_array($feedback) ? ($feedback['type'] ?? 'question') : $feedback->type;
        $module   = is_array($feedback) ? ($feedback['module'] ?? '') : $feedback->module;
        $createdAt = is_array($feedback)
            ? ($feedback['created_at'] ?? now())
            : ($feedback->created_at ?? now());

        // Severity puanı
        $score = match ($severity) {
            'critical' => 80,
            'high'     => 60,
            'medium'   => 35,
            'low'      => 15,
            default    => 15,
        };

        // Type bonusu
        $score += match ($type) {
            'bug', 'data' => 15,
            'risk'        => 10,
            'ux'          => 5,
            default       => 0,
        };

        // Modül risk bonusu
        $moduleLower = strtolower((string) $module);
        foreach (self::HIGH_RISK_MODULES as $riskMod) {
            if (str_contains($moduleLower, $riskMod)) {
                $score += 10;
                break;
            }
        }

        // Yaş bonusu — CarbonInterface veya string
        $ageInDays = 0;
        if ($createdAt instanceof \Carbon\CarbonInterface) {
            $ageInDays = (int) $createdAt->diffInDays(now());
        } elseif ($createdAt instanceof \DateTimeInterface) {
            $ageInDays = (int) \Carbon\Carbon::instance($createdAt)->diffInDays(now());
        } elseif (is_string($createdAt)) {
            try {
                $ageInDays = (int) \Carbon\Carbon::parse($createdAt)->diffInDays(now());
            } catch (\Throwable) {
                $ageInDays = 0;
            }
        }

        if ($ageInDays >= 7) {
            $score += 10;
        } elseif ($ageInDays >= 3) {
            $score += 5;
        }

        return min(100, $score);
    }

    /**
     * Puana göre önerilen eylemi döndürür.
     * @param array|AccountingPilotFeedback $feedback
     */
    public function recommendedAction(array|AccountingPilotFeedback $feedback): string
    {
        $score = is_array($feedback)
            ? ($feedback['priority_score'] ?? $this->priorityScore($feedback))
            : $this->priorityScore($feedback);

        return match (true) {
            $score >= 85 => 'fix_now',
            $score >= 60 => 'fix_next',
            $score >= 30 => 'watch',
            default      => 'document',
        };
    }

    /**
     * Backlog özet istatistiklerini döndürür.
     */
    public function summary(?int $userId = null): array
    {
        $items = $this->build($userId);

        if (empty($items)) {
            return [
                'total_open'       => 0,
                'fix_now_count'    => 0,
                'fix_next_count'   => 0,
                'watch_count'      => 0,
                'document_count'   => 0,
                'top_priority_score' => 0,
                'blocked_modules'  => [],
            ];
        }

        $fixNow  = array_filter($items, fn ($i) => $i['recommended_action'] === 'fix_now');
        $fixNext = array_filter($items, fn ($i) => $i['recommended_action'] === 'fix_next');
        $watch   = array_filter($items, fn ($i) => $i['recommended_action'] === 'watch');
        $doc     = array_filter($items, fn ($i) => $i['recommended_action'] === 'document');

        $blockedModules = array_values(array_unique(
            array_column(array_filter($items, fn ($i) => $i['recommended_action'] === 'fix_now'), 'module')
        ));

        return [
            'total_open'         => count($items),
            'fix_now_count'      => count($fixNow),
            'fix_next_count'     => count($fixNext),
            'watch_count'        => count($watch),
            'document_count'     => count($doc),
            'top_priority_score' => (int) ($items[0]['priority_score'] ?? 0),
            'blocked_modules'    => $blockedModules,
        ];
    }

    /**
     * Owner hint belirleme.
     */
    protected function ownerHint(AccountingPilotFeedback $fb): string
    {
        $type   = strtolower($fb->type ?? '');
        $module = strtolower($fb->module ?? '');

        if (in_array($module, ['integration', 'e_document', 'pos'])) {
            return 'ops';
        }

        return match ($type) {
            'bug', 'data', 'risk' => 'engineering',
            'ux', 'question'      => 'product',
            default               => 'support',
        };
    }

    /**
     * Target faz belirleme.
     */
    protected function targetPhase(string $action): string
    {
        return match ($action) {
            'fix_now'  => 'P23-hotfix',
            'fix_next' => 'P24',
            default    => 'later',
        };
    }
}
