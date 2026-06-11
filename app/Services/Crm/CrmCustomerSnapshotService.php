<?php

namespace App\Services\Crm;

use App\Models\CrmContact;
use App\Models\CrmTimelineEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class CrmCustomerSnapshotService
{
    /**
     * @var array<string, array<string, mixed>|null>
     */
    protected array $cache = [];

    protected ?bool $crmReady = null;

    /**
     * @return array<string, mixed>|null
     */
    public function forSubject(?User $user, string $source, Model|int|string|null $subject): ?array
    {
        if (!$user || !$this->crmTablesReady()) {
            return null;
        }

        $subjectId = $subject instanceof Model ? $subject->getKey() : $subject;

        if (!$subjectId) {
            return null;
        }

        $subjectType = $subject instanceof Model
            ? $subject::class
            : app(CrmSourceLinkService::class)->subjectTypeFor($source);

        if (!$subjectType) {
            return null;
        }

        $cacheKey = implode(':', [$user->id, $subjectType, $subjectId]);

        if (array_key_exists($cacheKey, $this->cache)) {
            return $this->cache[$cacheKey];
        }

        $contactId = CrmTimelineEvent::query()
            ->where('user_id', $user->id)
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->whereNotNull('contact_id')
            ->latest('occurred_at')
            ->latest('id')
            ->value('contact_id');

        if (!$contactId) {
            return $this->cache[$cacheKey] = null;
        }

        return $this->cache[$cacheKey] = $this->forContactId($user, (int) $contactId);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function forContactId(?User $user, int $contactId): ?array
    {
        if (!$user || !$this->crmTablesReady()) {
            return null;
        }

        $cacheKey = implode(':', [$user->id, 'contact', $contactId]);

        if (array_key_exists($cacheKey, $this->cache)) {
            return $this->cache[$cacheKey];
        }

        $contact = CrmContact::query()
            ->where('user_id', $user->id)
            ->whereKey($contactId)
            ->withCount([
                'openCases as crm_alert_count' => fn ($query) => $query
                    ->where('source_type', 'crm')
                    ->where('category', 'crm_alert'),
            ])
            ->first([
                'id',
                'display_name',
                'primary_phone',
                'primary_email',
                'order_count',
                'gross_revenue_total',
                'open_case_count',
                'risk_score',
                'value_score',
                'last_event_title',
                'last_event_at',
            ]);

        if (!$contact) {
            return $this->cache[$cacheKey] = null;
        }

        return $this->cache[$cacheKey] = [
            'contact_id' => $contact->id,
            'display_name' => $contact->display_name,
            'primary_contact' => $contact->primary_phone ?: $contact->primary_email,
            'url' => app(CrmSourceLinkService::class)->contactUrl($contact->id),
            'risk_score' => (int) $contact->risk_score,
            'risk_label' => $this->scoreLabel((int) $contact->risk_score, high: 'Yüksek', medium: 'Orta', low: 'Düşük'),
            'risk_tone' => $this->riskTone((int) $contact->risk_score),
            'value_score' => (int) $contact->value_score,
            'value_label' => $this->scoreLabel((int) $contact->value_score, high: 'Yüksek', medium: 'Orta', low: 'Gelişiyor'),
            'value_tone' => $this->valueTone((int) $contact->value_score),
            'open_case_count' => (int) $contact->open_case_count,
            'crm_alert_count' => (int) $contact->crm_alert_count,
            'order_count' => (int) $contact->order_count,
            'gross_revenue_total' => (float) $contact->gross_revenue_total,
            'gross_revenue_label' => '₺' . number_format((float) $contact->gross_revenue_total, 0, ',', '.'),
            'last_event_title' => $contact->last_event_title,
            'last_event_at' => $contact->last_event_at,
            'last_event_label' => $contact->last_event_at?->format('d.m.Y H:i'),
        ];
    }

    protected function crmTablesReady(): bool
    {
        if ($this->crmReady !== null) {
            return $this->crmReady;
        }

        return $this->crmReady = Schema::hasTable('crm_contacts')
            && Schema::hasTable('crm_timeline_events');
    }

    protected function scoreLabel(int $score, string $high, string $medium, string $low): string
    {
        return match (true) {
            $score >= 70 => $high,
            $score >= 40 => $medium,
            default => $low,
        };
    }

    protected function riskTone(int $score): string
    {
        return match (true) {
            $score >= 70 => 'danger',
            $score >= 40 => 'warning',
            default => 'success',
        };
    }

    protected function valueTone(int $score): string
    {
        return match (true) {
            $score >= 70 => 'success',
            $score >= 40 => 'info',
            default => 'default',
        };
    }
}
