<?php

namespace Database\Seeders;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::factory()->admin()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
        ]);

        $user = User::factory()->create([
            'name' => 'Usuário Comum',
            'email' => 'user@example.com',
        ]);

        $rooms = collect([
            ['name' => 'Sala Rio', 'capacity' => 6],
            ['name' => 'Sala São Paulo', 'capacity' => 12],
            ['name' => 'Sala Auditório', 'capacity' => 40],
        ])->map(fn (array $attributes) => Room::create($attributes));

        $tomorrow = CarbonImmutable::tomorrow()->setTime(14, 0);

        Reservation::create([
            'room_id' => $rooms[0]->id,
            'user_id' => $user->id,
            'starts_at' => $tomorrow,
            'ends_at' => $tomorrow->addHour(),
            'status' => ReservationStatus::Confirmed,
        ]);

        Reservation::create([
            'room_id' => $rooms[1]->id,
            'user_id' => $admin->id,
            'starts_at' => $tomorrow->addHours(2),
            'ends_at' => $tomorrow->addHours(3),
            'status' => ReservationStatus::Confirmed,
        ]);
    }
}
