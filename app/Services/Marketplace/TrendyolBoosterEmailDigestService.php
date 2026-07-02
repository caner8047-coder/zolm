<?php

namespace App\Services\Marketplace;

use App\Mail\TrendyolBoosterDigestMail;
use App\Models\AppNotification;
use App\Models\User;
use App\Services\NotificationCenterService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class TrendyolBoosterEmailDigestService
{
    public function __construct(
        protected NotificationCenterService $notificationCenter,
    ) {
    }

    /**
     * @var array<int, string>
     */
    public const BOOSTER_NOTIFICATION_TYPES = [
        'booster_price_drop',
        'booster_price_rise',
        'booster_stock_sales',
        'booster_stock_change',
        'booster_store_change',
        'booster_keyword_change',
    ];

    /**
     * @return array{processed: int, sent: int, skipped: int, failed: int, notifications: int}
     */
    public function sendPending(?int $userId = null, int $limit = 100, bool $dryRun = false): array
    {
        if (! $this->tablesReady()) {
            return [
                'processed' => 0,
                'sent' => 0,
                'skipped' => 0,
                'failed' => 0,
                'notifications' => 0,
            ];
        }

        $pending = $this->excludeMutedNotifications($this->pendingNotifications($userId, $limit));
        $result = [
            'processed' => 0,
            'sent' => 0,
            'skipped' => 0,
            'failed' => 0,
            'notifications' => $pending->count(),
        ];

        foreach ($pending->groupBy('user_id') as $groupedUserId => $notifications) {
            $result['processed']++;
            $user = User::query()->find((int) $groupedUserId);

            if (! $user || trim((string) $user->email) === '') {
                $result['skipped']++;

                continue;
            }

            if ($dryRun) {
                $result['skipped']++;

                continue;
            }

            $sendResult = $this->sendForUser($user, $notifications);
            $result[$sendResult ? 'sent' : 'failed']++;
        }

        return $result;
    }

    /**
     * @param  Collection<int, AppNotification>  $notifications
     */
    public function sendForUser(User $user, Collection $notifications): bool
    {
        $notifications = $notifications
            ->filter(fn (AppNotification $notification): bool => $notification->email_digest_sent_at === null)
            ->values();

        if ($notifications->isEmpty()) {
            return true;
        }

        try {
            Mail::to((string) $user->email)->send(new TrendyolBoosterDigestMail(
                $this->buildPayload($user, $notifications)
            ));

            AppNotification::query()
                ->whereIn('id', $notifications->pluck('id')->all())
                ->update(['email_digest_sent_at' => now()]);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param  Collection<int, AppNotification>  $notifications
     * @return array<string, mixed>
     */
    public function buildPayload(User $user, Collection $notifications): array
    {
        $notifications = $notifications
            ->sortByDesc(fn (AppNotification $notification) => $notification->triggered_at ?: $notification->created_at)
            ->values();
        $first = $notifications->min(fn (AppNotification $notification) => $notification->triggered_at ?: $notification->created_at);
        $last = $notifications->max(fn (AppNotification $notification) => $notification->triggered_at ?: $notification->created_at);
        $counts = $this->counts($notifications);

        return [
            'subject' => 'ZOLM Trendyol Booster Özeti - ' . now()->format('d.m.Y H:i'),
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
            ],
            'period' => [
                'start' => $first instanceof Carbon ? $first->toDateTimeString() : null,
                'end' => $last instanceof Carbon ? $last->toDateTimeString() : null,
                'label' => $this->periodLabel($first, $last),
            ],
            'counts' => $counts,
            'notifications' => $notifications
                ->take(20)
                ->map(fn (AppNotification $notification): array => [
                    'id' => $notification->id,
                    'type' => $notification->type,
                    'severity' => $notification->severity,
                    'title' => $notification->title,
                    'body' => $notification->body,
                    'action_url' => $notification->action_url,
                    'created_at' => $notification->created_at?->toDateTimeString(),
                    'created_at_label' => $notification->created_at?->format('d.m.Y H:i'),
                    'data' => $notification->data_json ?? [],
                ])
                ->all(),
            'links' => [
                'booster' => route('mp.trendyol-booster', ['booster' => 'history']),
            ],
            'generated_at' => now()->format('d.m.Y H:i'),
        ];
    }

    /**
     * @return Collection<int, AppNotification>
     */
    public function pendingNotifications(?int $userId = null, int $limit = 100): Collection
    {
        return AppNotification::query()
            ->whereIn('type', self::BOOSTER_NOTIFICATION_TYPES)
            ->whereNull('email_digest_sent_at')
            ->when($userId !== null, fn (Builder $query) => $query->where('user_id', $userId))
            ->oldest('created_at')
            ->limit(max(1, min(1000, $limit)))
            ->get();
    }

    /**
     * @param  Collection<int, AppNotification>  $notifications
     * @return Collection<int, AppNotification>
     */
    protected function excludeMutedNotifications(Collection $notifications): Collection
    {
        $mutedByUser = [];

        return $notifications
            ->reject(function (AppNotification $notification) use (&$mutedByUser): bool {
                $userId = (int) $notification->user_id;

                if (! array_key_exists($userId, $mutedByUser)) {
                    $mutedByUser[$userId] = $this->notificationCenter->mutedTypesForUser($userId);
                }

                return in_array((string) $notification->type, $mutedByUser[$userId], true);
            })
            ->values();
    }

    public function tablesReady(): bool
    {
        return Schema::hasTable('app_notifications')
            && Schema::hasColumn('app_notifications', 'email_digest_sent_at')
            && Schema::hasTable('users');
    }

    /**
     * @param  Collection<int, AppNotification>  $notifications
     * @return array{total: int, price: int, stock: int, competitor: int, keyword: int, warning: int}
     */
    protected function counts(Collection $notifications): array
    {
        return [
            'total' => $notifications->count(),
            'price' => $notifications->whereIn('type', ['booster_price_drop', 'booster_price_rise'])->count(),
            'stock' => $notifications->whereIn('type', ['booster_stock_sales', 'booster_stock_change'])->count(),
            'competitor' => $notifications->where('type', 'booster_store_change')->count(),
            'keyword' => $notifications->where('type', 'booster_keyword_change')->count(),
            'warning' => $notifications->filter(fn (AppNotification $notification): bool => in_array($notification->severity, ['warning', 'critical'], true))->count(),
        ];
    }

    protected function periodLabel(mixed $first, mixed $last): string
    {
        if (! $first instanceof Carbon || ! $last instanceof Carbon) {
            return 'Son Booster sinyalleri';
        }

        if ($first->isSameDay($last)) {
            return $first->format('d.m.Y');
        }

        return Str::of($first->format('d.m.Y'))
            ->append(' - ')
            ->append($last->format('d.m.Y'))
            ->toString();
    }
}
