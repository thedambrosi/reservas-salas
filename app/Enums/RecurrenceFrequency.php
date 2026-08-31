<?php

namespace App\Enums;

use Carbon\CarbonImmutable;

enum RecurrenceFrequency: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';

    /**
     * Advance the given date by one step of this frequency, honouring the interval.
     */
    public function advance(CarbonImmutable $date, int $interval): CarbonImmutable
    {
        return match ($this) {
            self::Daily => $date->addDays($interval),
            self::Weekly => $date->addWeeks($interval),
        };
    }
}
