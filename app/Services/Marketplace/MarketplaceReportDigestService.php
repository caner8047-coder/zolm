<?php

namespace App\Services\Marketplace;

use App\Mail\MarketplaceReportDigestMail;
use App\Models\MarketplaceReportDigestRun;
use App\Models\MarketplaceReportSubscription;
use App\Models\MarketplaceStore;
use App\Models\Report;
use App\Models\User;
use App\Services\CampaignDecisionCenterQueryService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class MarketplaceReportDigestService
{
    public function __construct(
        protected MarketplaceProfitCenterQueryService $profitCenter,
        protected MarketplaceRiskSignalService $riskSignals,
        protected CampaignDecisionCenterQueryService $campaigns,
    ) {
    }

    /**
     * @return array<string, array{label: string, description: string}>
     */
    public function frequencyDefinitions(): array
    {
        return [
            'daily' => [
                'label' => 'Günlük',
                'description' => 'Her sabah bir önceki günün kâr, risk ve kampanya özeti.',
            ],
            'weekly' => [
                'label' => 'Haftalık',
                'description' => 'Haftanın ilk günü önceki haftanın yönetici özeti.',
            ],
        ];
    }

    /**
     * @return array<string, array{label: string, description: string}>
     */
    public function sectionDefinitions(): array
    {
        return [
            'profit' => [
                'label' => 'Kâr özeti',
                'description' => 'Ciro, net alacak, kâr, marj ve zarar baskısı.',
            ],
            'risk' => [
                'label' => 'Risk özeti',
                'description' => 'Risk skoru, kritik sinyaller ve finansal baskı.',
            ],
            'campaign' => [
                'label' => 'Kampanya etkisi',
                'description' => 'Güvenli fırsat ve riskli kampanya kararları.',
            ],
            'marketplace' => [
                'label' => 'Pazaryeri kırılımı',
                'description' => 'Kanal bazında ciro, kâr ve risk karşılaştırması.',
            ],
            'actions' => [
                'label' => 'Öncelikli aksiyonlar',
                'description' => 'Kâr Merkezi ve Risk Merkezi kaynaklı ilk adımlar.',
            ],
        ];
    }

    public function defaultSubscriptionForUser(int $userId): MarketplaceReportSubscription
    {
        $user = User::query()->findOrFail($userId);

        return MarketplaceReportSubscription::query()->firstOrCreate([
            'user_id' => $userId,
        ], [
            'name' => 'Pazaryeri Kâr Özeti',
            'frequency' => MarketplaceReportSubscription::FREQUENCY_DAILY,
            'channels_json' => ['email'],
            'recipients_json' => [$user->email],
            'filters_json' => [],
            'sections_json' => array_keys($this->sectionDefinitions()),
            'enabled' => true,
            'send_time' => (string) config('marketplace.report_digest.default_send_time', '08:30'),
            'timezone' => (string) config('app.timezone', 'Europe/Istanbul'),
            'next_run_at' => $this->nextRunAt(
                MarketplaceReportSubscription::FREQUENCY_DAILY,
                (string) config('marketplace.report_digest.default_send_time', '08:30'),
                (string) config('app.timezone', 'Europe/Istanbul'),
                now(),
            ),
        ]);
    }

    /**
     * @return array{processed: int, sent: int, skipped: int, failed: int}
     */
    public function sendDue(?Carbon $now = null, ?int $userId = null, bool $force = false, bool $dryRun = false): array
    {
        $now = $now ?: now();
        $subscriptions = $this->subscriptionsForRun($now, $userId, $force);
        $result = [
            'processed' => 0,
            'sent' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        foreach ($subscriptions as $subscription) {
            $result['processed']++;

            if ($dryRun) {
                $result['skipped']++;

                continue;
            }

            $sendResult = $this->sendSubscription($subscription, $now, $force);
            $result['sent'] += $sendResult['sent'];
            $result['skipped'] += $sendResult['skipped'];
            $result['failed'] += $sendResult['failed'];
        }

        return $result;
    }

    /**
     * @return array{sent: int, skipped: int, failed: int}
     */
    public function sendSubscription(MarketplaceReportSubscription $subscription, ?Carbon $runAt = null, bool $force = false): array
    {
        $runAt = $runAt ?: now();
        $subscription->loadMissing(['user', 'store']);

        if (! $force && ! $this->isDue($subscription, $runAt)) {
            return ['sent' => 0, 'skipped' => 1, 'failed' => 0];
        }

        if (! $subscription->enabled && ! $force) {
            return ['sent' => 0, 'skipped' => 1, 'failed' => 0];
        }

        $payload = $this->buildPayload($subscription, $runAt);
        $recipients = $this->recipients($subscription);

        if ($recipients === []) {
            $this->markSubscriptionFailure($subscription, 'Geçerli alıcı bulunamadı.', $runAt);

            return ['sent' => 0, 'skipped' => 0, 'failed' => 1];
        }

        $result = ['sent' => 0, 'skipped' => 0, 'failed' => 0];
        
        $channels = $subscription->channels_json ?? [];
        
        $webhookPayload = [
            'title' => $payload['subject'] ?? 'Marketplace Report Digest',
            'summary' => $payload['summary']['analysis'] ?? '',
            'total_profit' => collect($payload['summary']['profit_by_marketplace'] ?? [])->sum('profit_value'),
            'total_revenue' => collect($payload['summary']['profit_by_marketplace'] ?? [])->sum('net_receivable'),
            'order_count' => collect($payload['summary']['profit_by_marketplace'] ?? [])->sum('order_count'),
            'margin_percent' => $payload['summary']['overall_margin'] ?? 0,
        ];

        if (in_array('webhook', $channels) && !empty($subscription->webhook_url)) {
            app(ReportDigestWebhookChannel::class)->send($webhookPayload, $subscription->webhook_url);
            $result['sent']++;
        }
        
        if (in_array('telegram', $channels) && !empty($subscription->telegram_bot_token) && !empty($subscription->telegram_chat_id)) {
            app(ReportDigestTelegramChannel::class)->send($webhookPayload, $subscription->telegram_bot_token, $subscription->telegram_chat_id);
            $result['sent']++;
        }

        foreach ($recipients as $recipient) {
            $report = Report::query()->create([
                'user_id' => $subscription->user_id,
                'profile_id' => null,
                'original_filename' => $this->reportHistoryName($payload, $recipient),
                'status' => 'processing',
            ]);

            $run = MarketplaceReportDigestRun::query()->create([
                'marketplace_report_subscription_id' => $subscription->id,
                'report_id' => $report->id,
                'user_id' => $subscription->user_id,
                'store_id' => $subscription->store_id,
                'frequency' => $subscription->frequency,
                'period_start' => $payload['period']['start'],
                'period_end' => $payload['period']['end'],
                'recipient_email' => $recipient,
                'subject' => $payload['subject'],
                'status' => 'pending',
                'summary_json' => $payload['summary'],
                'payload_json' => $payload,
            ]);

            try {
                Mail::to($recipient)->send(new MarketplaceReportDigestMail($payload));

                $run->forceFill([
                    'status' => 'sent',
                    'sent_at' => now(),
                ])->save();
                $report->forceFill(['status' => 'success'])->save();
                $result['sent']++;
            } catch (Throwable $exception) {
                $run->forceFill([
                    'status' => 'failed',
                    'error_message' => Str::limit($exception->getMessage(), 1000),
                ])->save();
                $report->forceFill([
                    'status' => 'failed',
                    'error_message' => Str::limit($exception->getMessage(), 1000),
                ])->save();
                $result['failed']++;
            }
        }

        $subscription->forceFill([
            'last_sent_at' => $result['sent'] > 0 ? now() : $subscription->last_sent_at,
            'next_run_at' => $this->nextRunAt(
                (string) $subscription->frequency,
                (string) $subscription->send_time,
                (string) $subscription->timezone,
                $runAt,
            ),
            'last_status' => $result['failed'] > 0 ? 'failed' : 'sent',
            'last_error' => $result['failed'] > 0 ? 'Bazı alıcılara rapor gönderilemedi.' : null,
        ])->save();

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildPayload(MarketplaceReportSubscription $subscription, ?Carbon $runAt = null): array
    {
        $runAt = $runAt ?: now();
        $subscription->loadMissing(['user', 'store']);
        $period = $this->periodFor((string) $subscription->frequency, (string) $subscription->timezone, $runAt);
        $filters = array_merge((array) ($subscription->filters_json ?? []), [
            'date_from' => $period['start'],
            'date_to' => $period['end'],
        ]);

        if ($subscription->store_id) {
            $filters['store_id'] = (int) $subscription->store_id;
        }

        $sections = $this->enabledSections($subscription);
        $summary = $this->profitCenter->summary((int) $subscription->user_id, $filters);
        $executive = $this->profitCenter->executiveCommandSummary((int) $subscription->user_id, $filters);
        $priorityActions = $this->profitCenter->priorityRecommendations((int) $subscription->user_id, $filters, 5);
        $riskDashboard = $this->riskSignals->dashboard((int) $subscription->user_id, $filters, 5);
        $campaignImpact = $this->campaigns->profitCenterImpact((int) $subscription->user_id);
        $marketplaceBreakdown = $this->profitCenter->marketplaceBreakdown((int) $subscription->user_id, $filters, 6);
        $costReadiness = $this->profitCenter->costReadiness((int) $subscription->user_id, $filters);

        $payloadSummary = [
            'gross_revenue' => (float) $summary['gross_revenue'],
            'profit_value' => (float) $summary['profit_value'],
            'profit_margin_percent' => (float) $summary['profit_margin_percent'],
            'net_receivable' => (float) $summary['net_receivable'],
            'total_orders' => (int) $summary['total_orders'],
            'loss_order_count' => (int) $summary['loss_order_count'],
            'finance_waiting_order_count' => (int) $summary['finance_waiting_order_count'],
            'risk_open_count' => (int) $riskDashboard['summary']['open_count'],
            'risk_critical_count' => (int) $riskDashboard['summary']['critical_count'],
            'risk_impact_total' => (float) $riskDashboard['summary']['impact_total'],
            'campaign_potential_profit' => (float) $campaignImpact['potential_profit'],
            'campaign_risk_exposure' => (float) $campaignImpact['risk_exposure'],
            'command_score' => (float) $executive['score'],
            'command_label' => (string) $executive['score_label'],
        ];

        return [
            'subject' => $this->subject($subscription, $period),
            'subscription' => [
                'id' => $subscription->id,
                'name' => $subscription->name,
                'frequency' => $subscription->frequency,
                'frequency_label' => $this->frequencyDefinitions()[$subscription->frequency]['label'] ?? Str::headline($subscription->frequency),
                'store_name' => $subscription->store?->store_name,
            ],
            'user' => [
                'name' => $subscription->user?->name,
                'email' => $subscription->user?->email,
            ],
            'period' => $period,
            'sections' => $sections,
            'summary' => $payloadSummary,
            'executive' => $executive,
            'profit' => $summary,
            'risk' => $riskDashboard['summary'],
            'risk_items' => $riskDashboard['priority_actions'],
            'campaign' => $campaignImpact,
            'marketplaces' => $marketplaceBreakdown,
            'actions' => $priorityActions,
            'cost_readiness' => $costReadiness,
            'generated_at' => $runAt->copy()->timezone((string) $subscription->timezone)->format('d.m.Y H:i'),
            'links' => [
                'profit_center' => route('mp.profit-center'),
                'risk_center' => route('mp.risk-center'),
                'campaign_center' => route('campaigns.decision-center'),
                'report_settings' => route('mp.report-digests'),
            ],
        ];
    }

    /**
     * @return array<int, MarketplaceReportSubscription>
     */
    protected function subscriptionsForRun(Carbon $now, ?int $userId, bool $force): array
    {
        if ($userId !== null) {
            $this->defaultSubscriptionForUser($userId);
        }

        $query = MarketplaceReportSubscription::query()
            ->with(['user', 'store'])
            ->when($userId, fn ($builder) => $builder->where('user_id', $userId))
            ->orderBy('id');

        if (! $force) {
            $query->where('enabled', true);
        }

        return $query
            ->limit((int) config('marketplace.report_digest.max_subscriptions_per_run', 100))
            ->get()
            ->filter(fn (MarketplaceReportSubscription $subscription) => $force || $this->isDue($subscription, $now))
            ->values()
            ->all();
    }

    public function isDue(MarketplaceReportSubscription $subscription, Carbon $now): bool
    {
        if (! $subscription->enabled) {
            return false;
        }

        if ($subscription->next_run_at) {
            return $subscription->next_run_at->lessThanOrEqualTo($now);
        }

        return $this->nextRunAt(
            (string) $subscription->frequency,
            (string) $subscription->send_time,
            (string) $subscription->timezone,
            $subscription->last_sent_at ?: $now->copy()->subDay(),
        )->lessThanOrEqualTo($now);
    }

    /**
     * @return array{start: string, end: string, label: string}
     */
    public function periodFor(string $frequency, string $timezone, Carbon $runAt): array
    {
        $local = $runAt->copy()->timezone($timezone);

        if ($frequency === MarketplaceReportSubscription::FREQUENCY_WEEKLY) {
            $start = $local->copy()->subWeek()->startOfWeek(Carbon::MONDAY);
            $end = $local->copy()->subWeek()->endOfWeek(Carbon::SUNDAY);
        } else {
            $start = $local->copy()->subDay()->startOfDay();
            $end = $local->copy()->subDay()->endOfDay();
        }

        return [
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
            'label' => $start->isSameDay($end)
                ? $start->format('d.m.Y')
                : $start->format('d.m.Y') . ' - ' . $end->format('d.m.Y'),
        ];
    }

    public function nextRunAt(string $frequency, string $sendTime, string $timezone, Carbon $from): Carbon
    {
        [$hour, $minute] = array_pad(explode(':', $sendTime), 2, '0');
        $local = $from->copy()->timezone($timezone);
        $candidate = $local->copy()->setTime((int) $hour, (int) $minute);

        if ($frequency === MarketplaceReportSubscription::FREQUENCY_WEEKLY) {
            $candidate = $local->copy()
                ->startOfWeek(Carbon::MONDAY)
                ->setTime((int) $hour, (int) $minute);

            if ($candidate->lessThanOrEqualTo($local)) {
                $candidate->addWeek();
            }
        } elseif ($candidate->lessThanOrEqualTo($local)) {
            $candidate->addDay();
        }

        return $candidate->timezone(config('app.timezone', 'UTC'));
    }

    /**
     * @return array<int, string>
     */
    public function recipients(MarketplaceReportSubscription $subscription): array
    {
        $configured = collect((array) ($subscription->recipients_json ?? []))
            ->map(fn ($email) => Str::lower(trim((string) $email)))
            ->filter(fn (string $email) => filter_var($email, FILTER_VALIDATE_EMAIL) !== false)
            ->unique()
            ->values();

        if ($configured->isNotEmpty()) {
            return $configured->all();
        }

        $fallback = Str::lower(trim((string) $subscription->user?->email));

        return filter_var($fallback, FILTER_VALIDATE_EMAIL) !== false ? [$fallback] : [];
    }

    /**
     * @return array<int, string>
     */
    protected function enabledSections(MarketplaceReportSubscription $subscription): array
    {
        $selected = collect((array) ($subscription->sections_json ?? []))
            ->filter(fn ($section) => array_key_exists((string) $section, $this->sectionDefinitions()))
            ->map(fn ($section) => (string) $section)
            ->values();

        return $selected->isNotEmpty()
            ? $selected->all()
            : array_keys($this->sectionDefinitions());
    }

    /**
     * @param  array{start: string, end: string, label: string}  $period
     */
    protected function subject(MarketplaceReportSubscription $subscription, array $period): string
    {
        $frequencyLabel = $this->frequencyDefinitions()[$subscription->frequency]['label'] ?? 'Periyodik';
        $storeLabel = $subscription->store?->store_name ? ' · ' . $subscription->store->store_name : '';

        return "ZOLM {$frequencyLabel} Kâr Özeti{$storeLabel} · {$period['label']}";
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function reportHistoryName(array $payload, string $recipient): string
    {
        $period = (string) data_get($payload, 'period.label', now()->format('d.m.Y'));

        return 'Otomatik Pazaryeri Raporu - ' . $period . ' - ' . $recipient;
    }

    protected function markSubscriptionFailure(MarketplaceReportSubscription $subscription, string $message, Carbon $runAt): void
    {
        $subscription->forceFill([
            'last_status' => 'failed',
            'last_error' => $message,
            'next_run_at' => $this->nextRunAt(
                (string) $subscription->frequency,
                (string) $subscription->send_time,
                (string) $subscription->timezone,
                $runAt,
            ),
        ])->save();
    }
}
