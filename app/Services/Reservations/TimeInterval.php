<?php

namespace App\Services\Reservations;

use Carbon\CarbonImmutable;

/**
 * A half-open time interval `[start, end)`.
 *
 * Half-open semantics mean that a reservation ending at 15:00 and another starting
 * at 15:00 do NOT overlap, which is the behaviour we want for back-to-back bookings.
 */
final readonly class TimeInterval
{
    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
    ) {}

    /**
     * Two half-open intervals overlap when each starts before the other ends.
     *
     * This single expression covers every case: partial overlap on either side,
     * one interval fully containing the other, and identical intervals.
     */
    public function overlaps(self $other): bool
    {
        return $this->start->lt($other->end) && $other->start->lt($this->end);
    }

    public function durationInMinutes(): int
    {
        return (int) $this->start->diffInMinutes($this->end);
    }
}
