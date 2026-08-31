<?php

namespace App\Services\Reservations;

use App\Models\Room;
use App\Models\User;
use Carbon\CarbonImmutable;

final readonly class BookReservationData
{
    public function __construct(
        public Room $room,
        public User $responsible,
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
        public ?RecurrenceRule $recurrence = null,
    ) {}
}
