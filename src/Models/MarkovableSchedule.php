<?php

namespace VinkiusLabs\Markovable\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MarkovableSchedule extends Model
{
    protected $table = 'markovable_schedules';

    protected $guarded = [];

    protected $casts = [
        'enabled' => 'boolean',
        'options' => 'array',
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $schedule): void {
            if (! $schedule->uuid) {
                $schedule->uuid = (string) Str::uuid();
            }
        });

        static::saving(function (self $schedule): void {
            $schedule->next_run_at = $schedule->computeNextRun();
        });
    }

    public function computeNextRun(): ?Carbon
    {
        if (! $this->enabled) {
            return null;
        }

        $now = Carbon::now();
        $time = $this->time;

        return match ($this->frequency) {
            'hourly' => $this->nextHourly($now, $time),
            'daily' => $this->nextDaily($now, $time),
            'weekly' => $this->nextWeekly($now, $time),
            'monthly' => $this->nextMonthly($now, $time),
            'cron' => $this->nextCron($now, $this->cron_expression),
            default => $this->nextDaily($now, $time),
        };
    }

    private function nextHourly(Carbon $now, ?string $time): Carbon
    {
        $minute = 0;

        if ($time && preg_match('/^([0-5]\d)$/', $time)) {
            $minute = (int) $time;
        }

        $candidate = $now->copy()->startOfHour()->addHour()->setMinute($minute)->setSecond(0);

        if ($candidate <= $now) {
            $candidate->addHour();
        }

        return $candidate;
    }

    private function nextDaily(Carbon $now, ?string $time): Carbon
    {
        [$hour, $minute] = $this->parseTime($time) ?? [$now->hour, $now->minute];

        $candidate = $now->copy()->setTime($hour, $minute, 0);

        if ($candidate <= $now) {
            $candidate->addDay();
        }

        return $candidate;
    }

    private function nextWeekly(Carbon $now, ?string $time): Carbon
    {
        [$dayOfWeek, $hour, $minute] = $this->parseDayAndTime($time) ?? [$now->dayOfWeek, $now->hour, $now->minute];

        $candidate = $now->copy()->setTime($hour, $minute, 0)->nextOrSame($dayOfWeek);

        if ($candidate <= $now) {
            $candidate->addWeek();
        }

        return $candidate;
    }

    private function nextMonthly(Carbon $now, ?string $time): Carbon
    {
        [$day, $hour, $minute] = $this->parseDayOfMonthTime($time) ?? [$now->day, $now->hour, $now->minute];

        $candidate = $now->copy()->setTime($hour, $minute, 0)->setDay($day);

        if ($candidate <= $now) {
            $candidate->addMonth()->setDay(min($day, $candidate->daysInMonth));
        }

        return $candidate;
    }

    private function nextCron(Carbon $now, ?string $expression): ?Carbon
    {
        if (! $expression) {
            return null;
        }

        try {
            $cron = new \Cron\CronExpression($expression);

            return Carbon::instance($cron->getNextRunDate($now));
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function parseTime(?string $time): ?array
    {
        if ($time && preg_match('/^(\d{1,2}):(\d{2})$/', $time, $matches)) {
            $hour = (int) $matches[1];
            $minute = (int) $matches[2];

            return [$hour, $minute];
        }

        return null;
    }

    private function parseDayAndTime(?string $time): ?array
    {
        if (! $time) {
            return null;
        }

        if (preg_match('/^(mon|tue|wed|thu|fri|sat|sun)\s+(\d{1,2}):(\d{2})$/i', $time, $matches)) {
            $dayMap = [
                'sun' => Carbon::SUNDAY,
                'mon' => Carbon::MONDAY,
                'tue' => Carbon::TUESDAY,
                'wed' => Carbon::WEDNESDAY,
                'thu' => Carbon::THURSDAY,
                'fri' => Carbon::FRIDAY,
                'sat' => Carbon::SATURDAY,
            ];

            $day = $dayMap[strtolower($matches[1])] ?? Carbon::MONDAY;

            return [$day, (int) $matches[2], (int) $matches[3]];
        }

        return null;
    }

    private function parseDayOfMonthTime(?string $time): ?array
    {
        if (! $time) {
            return null;
        }

        if (preg_match('/^(\d{1,2})\s+(\d{1,2}):(\d{2})$/', $time, $matches)) {
            $day = max(1, min(31, (int) $matches[1]));

            return [$day, (int) $matches[2], (int) $matches[3]];
        }

        return null;
    }
}
