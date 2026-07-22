<?php

namespace App\Modules\Hr\Attendance\Services;

use App\Modules\Hr\Attendance\Enums\AttendanceEventType;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AttendanceIntervalCalculator
{
    /**
     * PDKS olaylarını giriş-çıkış oturumlarına dönüştürür. Çıkış ile sonraki
     * giriş arasındaki boşluk çalışma süresine eklenmez.
     */
    public function calculate(Collection $events): array
    {
        $activeIn = null;
        $workSegmentStart = null;
        $activeBreak = null;
        $sessionBreakMinutes = 0;
        $workedMinutes = 0;
        $breakMinutes = 0;
        $firstIn = null;
        $lastOut = null;
        $sessions = [];
        $workIntervals = [];
        $flags = [];

        foreach ($events->sortBy('occurred_at') as $event) {
            $occurredAt = Carbon::parse($event->occurred_at);

            if ($event->event_type === AttendanceEventType::CheckIn) {
                $firstIn ??= $occurredAt->copy();
                if ($activeIn) {
                    $flags[] = 'duplicate_check_in';
                    continue;
                }
                $activeIn = $occurredAt->copy();
                $workSegmentStart = $occurredAt->copy();
                $activeBreak = null;
                $sessionBreakMinutes = 0;
                continue;
            }

            if ($event->event_type === AttendanceEventType::BreakStart) {
                if (! $activeIn || $activeBreak) {
                    $flags[] = 'invalid_break_start';
                    continue;
                }
                if ($workSegmentStart && $occurredAt->gt($workSegmentStart)) {
                    $workIntervals[] = ['starts_at' => $workSegmentStart->toIso8601String(), 'ends_at' => $occurredAt->toIso8601String()];
                }
                $activeBreak = $occurredAt->copy();
                $workSegmentStart = null;
                continue;
            }

            if ($event->event_type === AttendanceEventType::BreakEnd) {
                if (! $activeIn || ! $activeBreak || $occurredAt->lte($activeBreak)) {
                    $flags[] = 'unmatched_break_end';
                    continue;
                }
                $minutes = $activeBreak->diffInMinutes($occurredAt);
                $sessionBreakMinutes += $minutes;
                $breakMinutes += $minutes;
                $activeBreak = null;
                $workSegmentStart = $occurredAt->copy();
                continue;
            }

            if ($event->event_type !== AttendanceEventType::CheckOut) {
                continue;
            }

            if (! $activeIn || $occurredAt->lte($activeIn)) {
                $flags[] = 'unmatched_check_out';
                continue;
            }

            if ($activeBreak) {
                $minutes = $activeBreak->diffInMinutes($occurredAt);
                $sessionBreakMinutes += $minutes;
                $breakMinutes += $minutes;
                $flags[] = 'unclosed_break';
                $activeBreak = null;
            }

            if ($workSegmentStart && $occurredAt->gt($workSegmentStart)) {
                $workIntervals[] = ['starts_at' => $workSegmentStart->toIso8601String(), 'ends_at' => $occurredAt->toIso8601String()];
            }

            $grossMinutes = $activeIn->diffInMinutes($occurredAt);
            $netMinutes = max(0, $grossMinutes - $sessionBreakMinutes);
            $workedMinutes += $netMinutes;
            $lastOut = $occurredAt->copy();
            $sessions[] = [
                'check_in_at' => $activeIn->toIso8601String(),
                'check_out_at' => $occurredAt->toIso8601String(),
                'break_minutes' => $sessionBreakMinutes,
                'worked_minutes' => $netMinutes,
            ];
            $activeIn = null;
            $workSegmentStart = null;
            $sessionBreakMinutes = 0;
        }

        if ($activeIn) {
            $flags[] = 'missing_check_out';
        }

        return [
            'worked_minutes' => $workedMinutes,
            'break_minutes' => $breakMinutes,
            'first_in_at' => $firstIn,
            'last_out_at' => $lastOut,
            'sessions' => $sessions,
            'work_intervals' => $workIntervals,
            'event_ids' => $events->pluck('id')->filter()->values()->all(),
            'flags' => array_values(array_unique($flags)),
        ];
    }
}
