<?php

namespace Database\Factories;

use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Room>
 */
class RoomFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Sala '.fake()->unique()->word().' '.fake()->numberBetween(1, 999),
            'capacity' => fake()->numberBetween(2, 30),
        ];
    }
}
