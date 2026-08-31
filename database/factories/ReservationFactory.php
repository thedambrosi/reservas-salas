<?php

namespace Database\Factories;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = CarbonImmutable::now()->addDays(fake()->numberBetween(1, 30))->setTime(fake()->numberBetween(8, 17), 0);

        return [
            'room_id' => Room::factory(),
            'user_id' => User::factory(),
            'reservation_series_id' => null,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addHour(),
            'status' => ReservationStatus::Confirmed,
        ];
    }

    /**
     * Place the reservation in a specific time window.
     */
    public function window(CarbonImmutable $startsAt, CarbonImmutable $endsAt): static
    {
        return $this->state(fn () => [
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => ReservationStatus::Cancelled,
            'cancelled_at' => CarbonImmutable::now(),
        ]);
    }

    public function past(): static
    {
        $startsAt = CarbonImmutable::now()->subDays(fake()->numberBetween(2, 20))->setTime(10, 0);

        return $this->state(fn () => [
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addHour(),
        ]);
    }
}
