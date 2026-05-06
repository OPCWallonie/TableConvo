<?php

namespace App\Services\BusinessDay;

use Carbon\Carbon;
use Carbon\CarbonInterface;

class BusinessDayService
{
    public function subtractBusinessDays(CarbonInterface $date, int $days): Carbon
    {
        return Carbon::parse($date)->subBusinessDays($days);
    }

    public function addBusinessDays(CarbonInterface $date, int $days): Carbon
    {
        return Carbon::parse($date)->addBusinessDays($days);
    }

    public function isBusinessDay(CarbonInterface $date): bool
    {
        return Carbon::parse($date)->isBusinessDay();
    }

    public function countBusinessDaysBetween(CarbonInterface $start, CarbonInterface $end): int
    {
        return Carbon::parse($start)->diffInBusinessDays(Carbon::parse($end));
    }
}
