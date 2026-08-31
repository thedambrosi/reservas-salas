<?php

namespace App\Services\Reservations;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\ReservationSeries;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Cancellation never deletes a row. It flips the status to `cancelled` and records
 * who did it and when, so the reservation stays on record.
 */
class ReservationCanceller
{
    public function cancelOccurrence(Reservation $reservation, User $actor, ?string $reason = null): Reservation
    {
        $reservation->forceFill([
            'status' => ReservationStatus::Cancelled,
            'cancelled_at' => CarbonImmutable::now(),
            'cancelled_by' => $actor->id,
            'cancellation_reason' => $reason,
        ])->save();

        return $reservation;
    }

    /**
     * Cancel the future occurrences of a series. Occurrences that have already
     * started are left untouched — they happened.
     *
     * @return int number of occurrences cancelled
     */
    public function cancelSeries(ReservationSeries $series, User $actor, ?string $reason = null): int
    {
        return DB::transaction(fn () => $series->reservations()
            ->where('status', ReservationStatus::Confirmed)
            ->where('starts_at', '>=', CarbonImmutable::now())
            ->update([
                'status' => ReservationStatus::Cancelled,
                'cancelled_at' => CarbonImmutable::now(),
                'cancelled_by' => $actor->id,
                'cancellation_reason' => $reason,
                'updated_at' => CarbonImmutable::now(),
            ]));
    }
}
