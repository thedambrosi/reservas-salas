<?php

namespace App\Services\Reservations;

use App\Models\Reservation;
use Carbon\CarbonImmutable;

class ReservationConflictChecker
{
    /**
     * Find every confirmed reservation in the room that overlaps one of the
     * requested intervals.
     *
     * Runs a single query for the whole requested span, then matches each
     * interval precisely in PHP. Must be called inside a transaction: the rows
     * are locked with `FOR UPDATE` so a concurrent request cannot slip an
     * overlapping reservation in between this check and the insert.
     *
     * @param  list<TimeInterval>  $intervals
     * @param  list<int>  $ignoreReservationIds  reservations to exclude (e.g. the series being rebooked)
     * @return list<ReservationConflict>
     */
    public function conflicts(int $roomId, array $intervals, array $ignoreReservationIds = []): array
    {
        if ($intervals === []) {
            return [];
        }

        $spanStart = $this->min(array_map(fn (TimeInterval $i) => $i->start, $intervals));
        $spanEnd = $this->max(array_map(fn (TimeInterval $i) => $i->end, $intervals));

        $existing = Reservation::query()
            ->where('room_id', $roomId)
            ->confirmed()
            ->when($ignoreReservationIds !== [], fn ($query) => $query->whereNotIn('id', $ignoreReservationIds))
            ->where('starts_at', '<', $spanEnd)
            ->where('ends_at', '>', $spanStart)
            ->lockForUpdate()
            ->get();

        $conflicts = [];

        foreach ($intervals as $interval) {
            foreach ($existing as $reservation) {
                $reservationInterval = new TimeInterval(
                    CarbonImmutable::parse($reservation->starts_at),
                    CarbonImmutable::parse($reservation->ends_at),
                );

                if ($interval->overlaps($reservationInterval)) {
                    $conflicts[] = new ReservationConflict($interval, $reservation);
                }
            }
        }

        return $conflicts;
    }

    /**
     * @param  list<CarbonImmutable>  $dates
     */
    private function min(array $dates): CarbonImmutable
    {
        return array_reduce(
            $dates,
            fn (?CarbonImmutable $carry, CarbonImmutable $date) => $carry === null || $date->lt($carry) ? $date : $carry,
        ) ?? $dates[0];
    }

    /**
     * @param  list<CarbonImmutable>  $dates
     */
    private function max(array $dates): CarbonImmutable
    {
        return array_reduce(
            $dates,
            fn (?CarbonImmutable $carry, CarbonImmutable $date) => $carry === null || $date->gt($carry) ? $date : $carry,
        ) ?? $dates[0];
    }
}
