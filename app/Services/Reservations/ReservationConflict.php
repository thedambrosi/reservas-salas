<?php

namespace App\Services\Reservations;

use App\Models\Reservation;

final readonly class ReservationConflict
{
    public function __construct(
        public TimeInterval $requested,
        public Reservation $existing,
    ) {}

    /**
     * @return array{
     *     requested_starts_at: string,
     *     requested_ends_at: string,
     *     conflicting_reservation_id: int,
     *     conflicting_starts_at: string,
     *     conflicting_ends_at: string,
     * }
     */
    public function toArray(): array
    {
        return [
            'requested_starts_at' => $this->requested->start->toIso8601String(),
            'requested_ends_at' => $this->requested->end->toIso8601String(),
            'conflicting_reservation_id' => $this->existing->id,
            'conflicting_starts_at' => $this->existing->starts_at->toIso8601String(),
            'conflicting_ends_at' => $this->existing->ends_at->toIso8601String(),
        ];
    }
}
