<?php

namespace App\Services\Reservations;

use App\Enums\ReservationStatus;
use App\Exceptions\ReservationConflictException;
use App\Models\Reservation;
use App\Models\ReservationSeries;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReservationBooker
{
    /** Postgres SQLSTATE for an exclusion-constraint violation. */
    private const EXCLUSION_VIOLATION = '23P01';

    public function __construct(
        private readonly RecurrenceExpander $expander,
        private readonly ReservationConflictChecker $conflictChecker,
    ) {}

    /**
     * Book a single reservation, or a whole recurring series, atomically.
     *
     * Every occurrence is checked for conflicts up front; if any one of them
     * clashes, nothing is persisted and a {@see ReservationConflictException} is
     * thrown listing the offending occurrences.
     *
     * @return Collection<int, Reservation>
     */
    public function book(BookReservationData $data): Collection
    {
        $first = new TimeInterval($data->startsAt, $data->endsAt);

        $intervals = $data->recurrence !== null
            ? $this->expander->expand($first, $data->recurrence)
            : [$first];

        try {
            return DB::transaction(function () use ($data, $intervals) {
                $conflicts = $this->conflictChecker->conflicts($data->room->id, $intervals);

                if ($conflicts !== []) {
                    throw new ReservationConflictException($conflicts);
                }

                $series = $data->recurrence !== null
                    ? ReservationSeries::create([
                        'room_id' => $data->room->id,
                        'user_id' => $data->responsible->id,
                        'frequency' => $data->recurrence->frequency,
                        'repeat_interval' => $data->recurrence->interval,
                        'occurrences' => $data->recurrence->occurrences,
                        'starts_at' => $data->startsAt,
                        'ends_at' => $data->endsAt,
                    ])
                    : null;

                return collect($intervals)->map(fn (TimeInterval $interval) => Reservation::create([
                    'room_id' => $data->room->id,
                    'user_id' => $data->responsible->id,
                    'reservation_series_id' => $series?->id,
                    'starts_at' => $interval->start,
                    'ends_at' => $interval->end,
                    'status' => ReservationStatus::Confirmed,
                ]))->values();
            });
        } catch (QueryException $e) {
            // Lost the race against a concurrent booking: the database exclusion
            // constraint rejected the insert. Surface it as a normal conflict.
            if ($e->getCode() === self::EXCLUSION_VIOLATION) {
                throw new ReservationConflictException([]);
            }

            throw $e;
        }
    }
}
