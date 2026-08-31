<?php

namespace App\Policies;

use App\Models\Reservation;
use App\Models\User;

class ReservationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Reservation $reservation): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Only the person responsible for the reservation, or an administrator, may
     * cancel it. This covers cancelling a single occurrence and the whole series
     * (every occurrence of a series shares the same responsible user).
     */
    public function cancel(User $user, Reservation $reservation): bool
    {
        return $user->id === $reservation->user_id || $user->isAdmin();
    }
}
