<?php

namespace App\Services\BusinessDay;

use Carbon\Carbon;
use Carbon\CarbonInterface;

class BusinessDayService
{
    /**
     * Jours fériés belges fixes (jour/mois)
     */
    private const FIXED_HOLIDAYS = [
        '01-01', // Jour de l'an
        '05-01', // Fête du Travail
        '07-21', // Fête nationale
        '08-15', // Assomption
        '11-01', // Toussaint
        '11-11', // Armistice
        '12-25', // Noël
    ];

    public function subtractBusinessDays(CarbonInterface $date, int $days): Carbon
    {
        $current = Carbon::parse($date);
        $subtracted = 0;

        while ($subtracted < $days) {
            $current->subDay();
            if ($this->isBusinessDay($current)) {
                $subtracted++;
            }
        }

        return $current;
    }

    public function addBusinessDays(CarbonInterface $date, int $days): Carbon
    {
        $current = Carbon::parse($date);
        $added = 0;

        while ($added < $days) {
            $current->addDay();
            if ($this->isBusinessDay($current)) {
                $added++;
            }
        }

        return $current;
    }

    public function isBusinessDay(CarbonInterface $date): bool
    {
        if ($date->isWeekend()) {
            return false;
        }

        return ! $this->isBelgianHoliday($date);
    }

    public function countBusinessDaysBetween(CarbonInterface $start, CarbonInterface $end): int
    {
        $count = 0;
        $current = Carbon::parse($start);
        $end = Carbon::parse($end);

        while ($current->lt($end)) {
            if ($this->isBusinessDay($current)) {
                $count++;
            }
            $current->addDay();
        }

        return $count;
    }

    private function isBelgianHoliday(CarbonInterface $date): bool
    {
        $formatted = $date->format('m-d');

        if (in_array($formatted, self::FIXED_HOLIDAYS)) {
            return true;
        }

        $year = $date->year;

        $movableHolidays = $this->getMovableHolidays($year);

        return in_array($date->toDateString(), $movableHolidays);
    }

    private function getMovableHolidays(int $year): array
    {
        $easter = Carbon::createFromTimestamp(easter_date($year));

        return [
            $easter->toDateString(),
            $easter->copy()->addDay()->toDateString(),
            $easter->copy()->addDays(39)->toDateString(),
            $easter->copy()->addDays(49)->toDateString(),
            $easter->copy()->addDays(50)->toDateString(),
        ];
    }
}
