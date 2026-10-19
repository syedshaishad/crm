<?php

namespace Modules\Core\Recurring;

use Carbon\CarbonInterface;

/**
 * Recurring-cadence math, shared by invoice and expense schedules.
 * Month-based steps use addMonthsNoOverflow so e.g. Jan 31 → Feb 28.
 */
class Schedule
{
    public const FREQUENCIES = ['weekly' => 'Weekly', 'monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'yearly' => 'Yearly'];

    public static function frequencies(): array
    {
        return self::FREQUENCIES;
    }

    public static function next(CarbonInterface $from, string $frequency): string
    {
        $next = $from->copy();
        match ($frequency) {
            'weekly' => $next->addWeek(),
            'quarterly' => $next->addMonthsNoOverflow(3),
            'yearly' => $next->addYear(),
            default => $next->addMonthNoOverflow(),
        };

        return $next->toDateString();
    }
}
