<?php

namespace App\Services\WhatsApp;

use App\Models\SlaDefinition;
use App\Models\SlaTrack;
use App\Models\SlaEvent;
use App\Models\SupportConversation;
use Carbon\Carbon;

class SLAService
{
    /**
     * Yeni konuşma için SLA başlat
     */
    public function startTracking(SupportConversation $conversation): ?SlaTrack
    {
        $definition = $this->findApplicableDefinition($conversation);
        if (!$definition) {
            return null;
        }

        $now = now();
        $firstResponseDeadline = $now->copy()->addMinutes($definition->first_response_minutes);
        $resolutionDeadline = $now->copy()->addMinutes($definition->resolution_minutes);

        // İş saatleri varsa hesaba kat
        if ($definition->business_hours_only) {
            $firstResponseDeadline = $this->addBusinessHours($now, $definition->first_response_minutes);
            $resolutionDeadline = $this->addBusinessHours($now, $definition->resolution_minutes);
        }

        $track = SlaTrack::create([
            'sla_definition_id' => $definition->id,
            'conversation_id' => $conversation->id,
            'store_id' => $conversation->store_id,
            'status' => 'active',
            'started_at' => $now,
            'first_response_deadline' => $firstResponseDeadline,
            'resolution_deadline' => $resolutionDeadline,
        ]);

        SlaEvent::create([
            'sla_track_id' => $track->id,
            'event_type' => 'started',
            'details_json' => ['conversation_id' => $conversation->id],
        ]);

        return $track;
    }

    /**
     * İlk yanıt kaydı
     */
    public function recordFirstResponse(SlaTrack $track): void
    {
        if ($track->first_response_at) {
            return; // Zaten kaydedilmiş
        }

        $track->update(['first_response_at' => now()]);

        if ($track->first_response_deadline && now()->isAfter($track->first_response_deadline)) {
            $track->update(['first_response_breached' => true]);
        }

        SlaEvent::create([
            'sla_track_id' => $track->id,
            'event_type' => 'first_response',
            'details_json' => ['breached' => $track->first_response_breached],
        ]);
    }

    /**
     * Çözüm kaydı
     */
    public function recordResolution(SlaTrack $track): void
    {
        $track->update([
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);

        if ($track->resolution_deadline && now()->isAfter($track->resolution_deadline)) {
            $track->update(['resolution_breached' => true]);
        }

        SlaEvent::create([
            'sla_track_id' => $track->id,
            'event_type' => 'resolved',
            'details_json' => ['breached' => $track->resolution_breached],
        ]);
    }

    /**
     * Süresi dolan SLA'ları kontrol et
     */
    public function checkBreaches(): array
    {
        $breachedTracks = SlaTrack::active()
            ->where('resolution_deadline', '<', now())
            ->with('definition', 'conversation')
            ->get();

        $breached = [];
        foreach ($breachedTracks as $track) {
            $track->update(['resolution_breached' => true, 'status' => 'breached']);

            SlaEvent::create([
                'sla_track_id' => $track->id,
                'event_type' => 'breached',
                'details_json' => ['type' => 'resolution'],
            ]);

            $breached[] = $track;
        }

        // İlk yanıt ihlali kontrolü
        $firstResponseBreached = SlaTrack::active()
            ->whereNull('first_response_at')
            ->where('first_response_deadline', '<', now())
            ->with('definition', 'conversation')
            ->get();

        foreach ($firstResponseBreached as $track) {
            $track->update(['first_response_breached' => true]);

            SlaEvent::create([
                'sla_track_id' => $track->id,
                'event_type' => 'breached',
                'details_json' => ['type' => 'first_response'],
            ]);
        }

        return ['resolution_breached' => $breachedTracks->count(), 'first_response_breached' => $firstResponseBreached->count()];
    }

    /**
     * SLA istatistikleri
     */
    public function getStats(int $storeId, int $days = 30): array
    {
        $tracks = SlaTrack::where('store_id', $storeId)
            ->where('created_at', '>=', now()->subDays($days))
            ->get();

        $total = $tracks->count();
        $resolved = $tracks->where('status', 'resolved')->count();
        $breached = $tracks->where('resolution_breached', true)->count();
        $firstResponseBreached = $tracks->where('first_response_breached', true)->count();

        $avgResolutionTime = $tracks->where('resolved_at')
            ->map(fn ($t) => $t->started_at->diffInMinutes($t->resolved_at))
            ->avg();

        return [
            'total' => $total,
            'resolved' => $resolved,
            'breached' => $breached,
            'first_response_breached' => $firstResponseBreached,
            'compliance_rate' => $total > 0 ? round((($total - $breached) / $total) * 100, 1) : 0,
            'avg_resolution_minutes' => round($avgResolutionTime ?? 0, 1),
        ];
    }

    private function findApplicableDefinition(SupportConversation $conversation): ?SlaDefinition
    {
        return SlaDefinition::where('store_id', $conversation->store_id)
            ->where('is_active', true)
            ->where(function ($q) use ($conversation) {
                $q->where('channel', $conversation->source_type)
                    ->orWhere('channel', 'all');
            })
            ->where(function ($q) use ($conversation) {
                $q->where('priority', $conversation->priority)
                    ->orWhere('priority', 'normal');
            })
            ->first();
    }

    private function addBusinessHours(Carbon $start, int $minutes): Carbon
    {
        // Basitleştirilmiş: 09:00-18:00 work hours
        $end = $start->copy()->addMinutes($minutes);
        $workStart = 9 * 60; // 09:00
        $workEnd = 18 * 60;  // 18:00

        $current = $start->copy();
        $remainingMinutes = $minutes;

        while ($remainingMinutes > 0) {
            $dayMinutes = $this->minutesInWorkDay($current, $workStart, $workEnd);

            if ($dayMinutes <= 0) {
                $current->addDay();
                continue;
            }

            if ($remainingMinutes <= $dayMinutes) {
                $current = $current->copy()->addMinutes($remainingMinutes);
                $remainingMinutes = 0;
            } else {
                $remainingMinutes -= $dayMinutes;
                $current->addDay()->setTime(intdiv($workStart, 60), $workStart % 60);
            }
        }

        return $current;
    }

    private function minutesInWorkDay(Carbon $date, int $workStart, int $workEnd): int
    {
        if ($date->isWeekend()) {
            return 0;
        }

        $currentMinutes = $date->hour * 60 + $date->minute;

        if ($currentMinutes >= $workEnd) {
            return 0;
        }

        $start = max($currentMinutes, $workStart);
        return $workEnd - $start;
    }
}
