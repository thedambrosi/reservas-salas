<?php

namespace Database\Factories;

use App\Enums\RecurrenceFrequency;
use App\Models\ReservationSeries;
use App\Models\Room;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReservationSeries>
 */
class ReservationSeriesFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = CarbonImmutable::now()->addWeek()->setTime(9, 0);

        return [
            'room_id' => Room::factory(),
            'user_id' => User::factory(),
            'frequency' => RecurrenceFrequency::Weekly,
            'repeat_interval' => 1,
            'occurrences' => 4,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addHour(),
        ];
    }
}
