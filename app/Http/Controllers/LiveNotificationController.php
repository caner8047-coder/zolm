<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Services\NotificationCenterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LiveNotificationController extends Controller
{
    public function feed(NotificationCenterService $notificationCenter): JsonResponse
    {
        $userId = (int) Auth::id();
        $preference = $notificationCenter->preferencesForUser($userId);
        $available = $notificationCenter->isAvailable();

        return response()->json([
            'notifications' => $notificationCenter->feedForUser($userId),
            'unread_count' => $notificationCenter->unreadCountForUser($userId),
            'preferences' => [
                'sound_enabled' => (bool) $preference->sound_enabled,
            ],
            'latest_id' => $available
                ? (AppNotification::query()
                    ->where('user_id', $userId)
                    ->max('id') ?? 0)
                : 0,
            'available' => $available,
            'stream_enabled' => $available && PHP_SAPI !== 'cli-server',
        ]);
    }

    public function stream(Request $request, NotificationCenterService $notificationCenter): StreamedResponse
    {
        $userId = (int) Auth::id();
        $lastId = max(0, (int) $request->query('last_id', 0));

        return response()->stream(function () use ($userId, $lastId, $notificationCenter): void {
            if (function_exists('session_write_close')) {
                @session_write_close();
            }

            $cursor = $lastId;
            $startedAt = time();

            $this->sendStreamEvent('connected', [
                'latest_id' => $cursor,
                'unread_count' => $notificationCenter->unreadCountForUser($userId),
            ]);

            while (!connection_aborted() && (time() - $startedAt) < 70) {
                $notifications = AppNotification::query()
                    ->with('store:id,store_name,marketplace')
                    ->where('user_id', $userId)
                    ->where('id', '>', $cursor)
                    ->orderBy('id')
                    ->limit(10)
                    ->get();

                foreach ($notifications as $notification) {
                    $cursor = max($cursor, (int) $notification->id);

                    $this->sendStreamEvent('notification', [
                        'notification' => $notificationCenter->toPayload($notification),
                        'latest_id' => $cursor,
                        'unread_count' => $notificationCenter->unreadCountForUser($userId),
                    ]);
                }

                if ($notifications->isEmpty()) {
                    $this->sendStreamEvent('heartbeat', [
                        'latest_id' => $cursor,
                        'unread_count' => $notificationCenter->unreadCountForUser($userId),
                    ]);
                }

                sleep(2);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function markRead(AppNotification $notification, NotificationCenterService $notificationCenter): JsonResponse
    {
        abort_unless((int) $notification->user_id === (int) Auth::id(), 403);

        if ($notification->read_at === null) {
            $notification->forceFill([
                'read_at' => now(),
                'seen_at' => $notification->seen_at ?: now(),
            ])->save();
        }

        return response()->json([
            'ok' => true,
            'unread_count' => $notificationCenter->unreadCountForUser((int) Auth::id()),
        ]);
    }

    public function markAllRead(NotificationCenterService $notificationCenter): JsonResponse
    {
        $userId = (int) Auth::id();

        AppNotification::query()
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->update([
                'read_at' => now(),
                'seen_at' => now(),
            ]);

        return response()->json([
            'ok' => true,
            'unread_count' => $notificationCenter->unreadCountForUser($userId),
        ]);
    }

    public function preferences(Request $request, NotificationCenterService $notificationCenter): JsonResponse
    {
        $validated = $request->validate([
            'sound_enabled' => ['required', 'boolean'],
        ]);

        $preference = $notificationCenter->setSoundEnabled(
            (int) Auth::id(),
            (bool) $validated['sound_enabled'],
        );

        return response()->json([
            'ok' => true,
            'preferences' => [
                'sound_enabled' => (bool) $preference->sound_enabled,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function sendStreamEvent(string $event, array $payload): void
    {
        echo "event: {$event}\n";
        echo 'data: ' . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";

        if (ob_get_level() > 0) {
            @ob_flush();
        }

        flush();
    }
}
