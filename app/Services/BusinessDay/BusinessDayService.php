<?php

namespace App\Services\BusinessDay;

use Carbon\Carbon;
use DateTimeInterface;
use Spatie\Holidays\Holidays;

class BusinessDayService
{
    private array $cache = [];

    public function isHoliday(DateTimeInterface $date): bool
    {
        $carbon = Carbon::parse($date);
        $year = $carbon->year;

        if (! isset($this->cache[$year])) {
            $holidays = Holidays::for('be', year: $year)->get();
            $this->cache[$year] = array_map(
                fn ($h) => $h->date->format('Y-m-d'),
                $holidays
            );
        }

        return in_array($carbon->format('Y-m-d'), $this->cache[$year], true);
    }

    public function isBusinessDay(DateTimeInterface $date): bool
    {
        $carbon = Carbon::parse($date);

        return ! $carbon->isWeekend() && ! $this->isHoliday($carbon);
    }

    public function addBusinessDays(DateTimeInterface $date, int $days): Carbon
    {
        $current = Carbon::parse($date)->copy();
        $added = 0;

        while ($added < $days) {
            $current->addDay();
            if ($this->isBusinessDay($current)) {
                $added++;
            }
        }

        return $current;
    }

    public function subBusinessDays(DateTimeInterface $date, int $days): Carbon
    {
        $current = Carbon::parse($date)->copy();
        $subtracted = 0;

        while ($subtracted < $days) {
            $current->subDay();
            if ($this->isBusinessDay($current)) {
                $subtracted++;
            }
        }

        return $current;
    }

    public function businessDaysBetween(DateTimeInterface $from, DateTimeInterface $to): int
    {
        $current = Carbon::parse($from)->startOfDay()->copy();
        $end = Carbon::parse($to)->startOfDay();
        $count = 0;

        while ($current->lt($end)) {
            if ($this->isBusinessDay($current)) {
                $count++;
            }
            $current->addDay();
        }

        return $count;
    }

    // Alias de compatibilité — utilisé par CancelRegistrationAction et les anciens tests
    public function subtractBusinessDays(DateTimeInterface $date, int $days): Carbon
    {
        return $this->subBusinessDays($date, $days);
    }
}
