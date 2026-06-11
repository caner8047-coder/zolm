<?php

namespace App\Services\Crm;

use App\Models\CrmCase;
use App\Models\CrmContact;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class CrmAlertRuleService
{
    /**
     * @return array{created:int, updated:int, resolved:int}
     */
    public function runForUser(User $user): array
    {
        $summary = [
            'created' => 0,
            'updated' => 0,
            'resolved' => 0,
        ];

        CrmContact::query()
            ->where('user_id', $user->id)
            ->withCount([
                'openCases as open_operational_case_count' => fn (Builder $query) => $this->operationalCases($query),
                'openCases as open_supply_case_count' => fn (Builder $query) => $this->operationalCases($query)->where('category', 'supply'),
                'openCases as open_message_case_count' => fn (Builder $query) => $this->operationalCases($query)->where('category', 'message'),
                'openCases as open_return_case_count' => fn (Builder $query) => $this->operationalCases($query)->where('category', 'return'),
                'openCases as open_cargo_case_count' => fn (Builder $query) => $this->operationalCases($query)->where('category', 'cargo'),
                'openCases as open_profit_case_count' => fn (Builder $query) => $this->operationalCases($query)->where('category', 'profit'),
            ])
            ->chunkById(150, function ($contacts) use (&$summary) {
                foreach ($contacts as $contact) {
                    $this->syncContactAlerts($contact, $summary);
                }
            });

        return $summary;
    }

    /**
     * @param array{created:int, updated:int, resolved:int} $summary
     */
    protected function syncContactAlerts(CrmContact $contact, array &$summary): void
    {
        $counts = [
            'operational' => (int) $contact->open_operational_case_count,
            'supply' => (int) $contact->open_supply_case_count,
            'message' => (int) $contact->open_message_case_count,
            'return' => (int) $contact->open_return_case_count,
            'cargo' => (int) $contact->open_cargo_case_count,
            'profit' => (int) $contact->open_profit_case_count,
        ];

        $this->syncAlert($contact, $summary, [
            'key' => 'multi-pressure',
            'active' => $counts['operational'] >= 3,
            'priority' => $counts['operational'] >= 4 ? 'critical' : 'high',
            'title' => 'Çoklu operasyon baskısı',
            'summary' => "Bu müşteride {$counts['operational']} açık operasyon vakası var. CRM üzerinden tek takip planı oluşturulmalı.",
            'sla_due_at' => now()->addHours(6),
            'meta' => ['counts' => $counts],
        ]);

        $this->syncAlert($contact, $summary, [
            'key' => 'supply-experience',
            'active' => $counts['supply'] > 0 && ($counts['message'] + $counts['return'] + $counts['cargo']) > 0,
            'priority' => 'high',
            'title' => 'Tedarik gecikmesi müşteri deneyimini etkiliyor',
            'summary' => 'Tedarik kaynaklı açık vaka ile müşteri temas/iade/kargo vakası aynı müşteride birleşti.',
            'sla_due_at' => now()->addHours(8),
            'meta' => ['counts' => $counts],
        ]);

        $this->syncAlert($contact, $summary, [
            'key' => 'return-cargo-collision',
            'active' => $counts['return'] > 0 && $counts['cargo'] > 0,
            'priority' => 'high',
            'title' => 'İade ve kargo riski çakışıyor',
            'summary' => 'Aynı müşteride hem iade hem kargo farkı açık. Finansal ve deneyim etkisi birlikte incelenmeli.',
            'sla_due_at' => now()->addDay(),
            'meta' => ['counts' => $counts],
        ]);

        $this->syncAlert($contact, $summary, [
            'key' => 'valuable-risk',
            'active' => (int) $contact->risk_score >= 70 && (int) $contact->value_score >= 35,
            'priority' => 'critical',
            'title' => 'Yüksek değerli müşteri riskte',
            'summary' => 'Müşteri değeri yüksek ve risk skoru kritik seviyede. Yönetici takibi önerilir.',
            'sla_due_at' => now()->addHours(4),
            'meta' => [
                'counts' => $counts,
                'risk_score' => (int) $contact->risk_score,
                'value_score' => (int) $contact->value_score,
            ],
        ]);
    }

    /**
     * @param array{created:int, updated:int, resolved:int} $summary
     * @param array{key:string, active:bool, priority:string, title:string, summary:string, sla_due_at:mixed, meta:array<string,mixed>} $rule
     */
    protected function syncAlert(CrmContact $contact, array &$summary, array $rule): void
    {
        $caseKey = "crm-alert:{$rule['key']}:{$contact->id}";

        if (!$rule['active']) {
            $summary['resolved'] += CrmCase::query()
                ->where('user_id', $contact->user_id)
                ->where('contact_id', $contact->id)
                ->where('case_key', $caseKey)
                ->whereNotIn('status', ['resolved', 'closed'])
                ->update([
                    'status' => 'resolved',
                    'resolved_at' => now(),
                    'meta_json' => array_merge($rule['meta'], [
                        'rule' => $rule['key'],
                        'auto_resolved_at' => now()->toIso8601String(),
                    ]),
                ]);

            return;
        }

        $case = CrmCase::firstOrNew([
            'user_id' => $contact->user_id,
            'case_key' => $caseKey,
        ]);
        $wasNew = !$case->exists;
        $wasResolved = in_array($case->status, ['resolved', 'closed'], true);
        $currentMeta = (array) ($case->meta_json ?? []);

        $case->fill([
            'contact_id' => $contact->id,
            'store_id' => null,
            'source_type' => 'crm',
            'category' => 'crm_alert',
            'priority' => $rule['priority'],
            'status' => $wasResolved ? 'open' : ($case->status ?: 'open'),
            'subject_type' => $contact::class,
            'subject_id' => $contact->id,
            'title' => $rule['title'],
            'summary' => $rule['summary'],
            'sla_due_at' => $rule['sla_due_at'],
            'resolved_at' => null,
            'meta_json' => array_merge($rule['meta'], [
                'rule' => $rule['key'],
                'generated_at' => $currentMeta['generated_at'] ?? now()->toIso8601String(),
            ]),
        ]);

        $case->save();

        if ($wasNew) {
            $summary['created']++;
        } elseif ($case->wasChanged()) {
            $summary['updated']++;
        }
    }

    protected function operationalCases(Builder $query): Builder
    {
        return $query->where('source_type', '!=', 'crm');
    }
}
